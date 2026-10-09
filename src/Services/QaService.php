<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use RuntimeException;
use SimpleXMLElement;
use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Support\Labels;
use Vitaliq\Validation\Rules;
use Vitaliq\Validation\Validator;

/**
 * STQA laboratory: test-case catalogue, executions and summaries.
 *
 * Catalogue definitions (database/qa/*.json) carry no results. Results only
 * exist after a run: the regex suite executed here, a manual execution
 * recorded by a QA analyst, or a PHPUnit JUnit report imported from the CLI.
 */
final class QaService
{
    public const MAX_INPUT = 500;

    public function __construct(private readonly App $app)
    {
    }

    // ------------------------------------------------------------------
    // Rule evaluation (tester + suite)
    // ------------------------------------------------------------------

    /** @return array{rule:string,input_length:int,normalised:string,regex_match:bool,accepted:bool,message:?string,duration_ms:float} */
    public function evaluate(string $rule, string $input): array
    {
        if (!Rules::exists($rule)) {
            throw new WorkflowException('Unknown rule.');
        }
        if (mb_strlen($input) > self::MAX_INPUT) {
            throw new ValidationFailed(['value' => 'Test values are limited to ' . self::MAX_INPUT . ' characters.']);
        }
        $start = hrtime(true);
        $normalised = Rules::normalise($rule, $input);
        $regex = Rules::matches($rule, $normalised);
        if ($rule === 'required') {
            $message = $regex ? null : 'Required field is empty.';
        } elseif ($rule === 'password') {
            $message = \Vitaliq\Auth\Passwords::policyError($normalised);
        } else {
            $message = Rules::matches('required', $normalised) ? Rules::check($rule, $normalised) : Rules::get($rule)['label'] . ' is required.';
        }
        $duration = (hrtime(true) - $start) / 1e6;
        return [
            'rule' => $rule,
            'input_length' => mb_strlen($input),
            'normalised' => $normalised,
            'regex_match' => $regex,
            'accepted' => $message === null,
            'message' => $message,
            'duration_ms' => round($duration, 3),
        ];
    }

    // ------------------------------------------------------------------
    // Catalogue
    // ------------------------------------------------------------------

    /** Insert catalogue cases that are not in the database yet. Returns inserted count. */
    public function syncCatalogue(?string $directory = null): int
    {
        $directory ??= VQ_ROOT . '/database/qa';
        $files = ['regex-cases.json', 'manual-cases.json'];
        $inserted = 0;
        $seen = [];
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            $cases = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            foreach ($cases as $c) {
                // A repeated code would otherwise be skipped silently below (see DEF-003).
                if (isset($seen[$c['code']])) {
                    throw new RuntimeException('Duplicate test case code in catalogue: ' . $c['code']);
                }
                $seen[$c['code']] = true;
                $exists = $this->app->db->value('SELECT 1 FROM qa_test_cases WHERE case_code = :c', ['c' => $c['code']]);
                if ($exists !== null) {
                    continue;
                }
                $isRegex = isset($c['rule']);
                if ($isRegex && !Rules::exists($c['rule'])) {
                    throw new RuntimeException('Catalogue case ' . $c['code'] . ' references unknown rule ' . $c['rule']);
                }
                $this->app->db->insert(
                    'INSERT INTO qa_test_cases (case_code, module, title, kind, case_type, rule_key, preconditions, input_value,
                        expected_match, expected_result, origin, notes, created_at)
                     VALUES (:code, :m, :t, :k, :ct, :r, :pre, :in, :em, :er, \'catalogue\', :n, :at)',
                    [
                        'code' => $c['code'], 'm' => $c['module'], 't' => $c['title'], 'k' => $isRegex ? 'regex' : 'manual',
                        'ct' => $c['type'], 'r' => $c['rule'] ?? null, 'pre' => $c['preconditions'] ?? null,
                        'in' => $c['input'] ?? null, 'em' => $isRegex ? (int) $c['expected'] : null,
                        'er' => $isRegex ? ($c['expected'] ? 'Accepted by the validator' : 'Rejected by the validator') : $c['expected'],
                        'n' => $c['notes'] ?? null, 'at' => $this->app->clock->nowSql(),
                    ]
                );
                $inserted++;
            }
        }
        return $inserted;
    }

    private const LATEST = '(SELECT MAX(rr.id) FROM qa_run_results rr WHERE rr.test_case_id = c.id)';

    /**
     * @param array<string,string> $f module, kind, type, status, q
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function catalogue(array $f, int $page, int $perPage = 40): array
    {
        $where = [];
        $params = [];
        if (($f['module'] ?? '') !== '') {
            $where[] = 'c.module = :m';
            $params['m'] = $f['module'];
        }
        if (in_array($f['kind'] ?? '', ['regex', 'manual'], true)) {
            $where[] = 'c.kind = :k';
            $params['k'] = $f['kind'];
        }
        if (isset(Labels::CASE_TYPES[$f['type'] ?? ''])) {
            $where[] = 'c.case_type = :t';
            $params['t'] = $f['type'];
        }
        $status = $f['status'] ?? '';
        if (in_array($status, ['pass', 'fail', 'blocked'], true)) {
            $where[] = 'lr.status = :s';
            $params['s'] = $status;
        } elseif ($status === 'not_run') {
            $where[] = 'lr.id IS NULL';
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(c.case_code LIKE :q1 OR c.title LIKE :q2)';
            $like = '%' . Database::likeEscape($f['q']) . '%';
            $params += ['q1' => $like, 'q2' => $like];
        }
        $sql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $from = 'FROM qa_test_cases c LEFT JOIN qa_run_results lr ON lr.id = ' . self::LATEST . ' LEFT JOIN defects d ON d.id = c.defect_id';
        $total = (int) $this->app->db->value("SELECT COUNT(*) {$from} {$sql}", $params);
        $rows = $this->app->db->all(
            "SELECT c.*, lr.status AS last_status, lr.actual_result AS last_actual, lr.executed_at AS last_executed_at,
                    lr.run_id AS last_run_id, d.code AS defect_code
               {$from} {$sql} ORDER BY c.module, c.case_code LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return list<string> */
    public function modules(): array
    {
        return array_column($this->app->db->all('SELECT DISTINCT module FROM qa_test_cases ORDER BY module'), 'module');
    }

    /** @return array<string,mixed>|null */
    public function findCase(int $id): ?array
    {
        $case = $this->app->db->one(
            'SELECT c.*, d.code AS defect_code, d.title AS defect_title FROM qa_test_cases c
               LEFT JOIN defects d ON d.id = c.defect_id WHERE c.id = :id',
            ['id' => $id]
        );
        if ($case !== null) {
            $case['executions'] = $this->app->db->all(
                'SELECT rr.*, r.run_type, r.triggered_from, u.full_name AS executed_by
                   FROM qa_run_results rr JOIN qa_runs r ON r.id = rr.run_id
                   LEFT JOIN users u ON u.id = r.executed_by_user_id
                  WHERE rr.test_case_id = :id ORDER BY rr.id DESC LIMIT 50',
                ['id' => $id]
            );
        }
        return $case;
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationFailed
     */
    public function createCase(array $input, int $userId): int
    {
        $v = new Validator($input);
        $kind = $v->choice('kind', ['regex' => 'Regex', 'manual' => 'Manual'], 'Case kind');
        $module = $v->text('module', 'Module', 40, true, 2);
        $title = $v->text('title', 'Title', 200, true, 5);
        $type = $v->choice('case_type', Labels::CASE_TYPES, 'Case type');
        $pre = $v->text('preconditions', 'Preconditions', 300, false);
        $notes = $v->text('notes', 'Notes', 500, false);
        $rule = null;
        $inputValue = null;
        $expectedMatch = null;
        $expected = null;
        if ($kind === 'regex') {
            $rule = $v->choice('rule_key', array_map(static fn ($r) => $r['label'], Rules::all()), 'Rule');
            $raw = (string) ($input['input_value'] ?? '');
            if (mb_strlen($raw) > self::MAX_INPUT) {
                $v->addError('input_value', 'Test inputs are limited to ' . self::MAX_INPUT . ' characters.');
            }
            $inputValue = $raw;
            $expectedMatch = $v->choice('expected_match', ['1' => 'Accept', '0' => 'Reject'], 'Expected outcome');
            $expected = $expectedMatch === '1' ? 'Accepted by the validator' : 'Rejected by the validator';
        } else {
            $inputValue = $v->text('input_value', 'Steps / input', 500, true, 3);
            $expected = $v->text('expected_result', 'Expected result', 300, true, 3);
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $this->app->db->transaction(function (Database $db) use ($kind, $module, $title, $type, $pre, $notes, $rule, $inputValue, $expectedMatch, $expected, $userId): int {
            $next = (int) $db->value("SELECT COUNT(*) FROM qa_test_cases WHERE origin = 'user' FOR UPDATE") + 1;
            $code = sprintf('TC-USR-%03d', $next);
            while ($db->value('SELECT 1 FROM qa_test_cases WHERE case_code = :c', ['c' => $code]) !== null) {
                $code = sprintf('TC-USR-%03d', ++$next);
            }
            $id = $db->insert(
                'INSERT INTO qa_test_cases (case_code, module, title, kind, case_type, rule_key, preconditions, input_value,
                    expected_match, expected_result, origin, notes, created_by_user_id, created_at)
                 VALUES (:code, :m, :t, :k, :ct, :r, :pre, :in, :em, :er, \'user\', :n, :u, :at)',
                [
                    'code' => $code, 'm' => $module, 't' => $title, 'k' => $kind, 'ct' => $type, 'r' => $rule, 'pre' => $pre,
                    'in' => $inputValue, 'em' => $expectedMatch === null ? null : (int) $expectedMatch, 'er' => $expected,
                    'n' => $notes, 'u' => $userId, 'at' => $this->app->clock->nowSql(),
                ]
            );
            $this->app->service(AuditLog::class)->record('qa.case_created', 'qa_test_case', $id, ['code' => $code]);
            return $id;
        });
    }

    public function linkDefect(int $caseId, ?int $defectId): void
    {
        if ($defectId !== null && $this->app->db->value('SELECT 1 FROM defects WHERE id = :id', ['id' => $defectId]) === null) {
            throw new WorkflowException('Defect not found.');
        }
        $this->app->db->execute('UPDATE qa_test_cases SET defect_id = :d WHERE id = :id', ['d' => $defectId, 'id' => $caseId]);
    }

    // ------------------------------------------------------------------
    // Execution
    // ------------------------------------------------------------------

    private function environment(): string
    {
        $db = (string) $this->app->db->value('SELECT VERSION()');
        return 'PHP ' . PHP_VERSION . ' · ' . PHP_OS_FAMILY . ' · DB ' . $db;
    }

    /** Execute every regex case in the catalogue and record actual outcomes. */
    public function runRegexSuite(?int $userId, string $from): int
    {
        $cases = $this->app->db->all("SELECT id, case_code, module, title, rule_key, input_value, expected_match FROM qa_test_cases WHERE kind = 'regex' ORDER BY id");
        $started = $this->app->clock->nowSql();
        return $this->app->db->transaction(function (Database $db) use ($cases, $userId, $from, $started): int {
            $runId = $db->insert(
                'INSERT INTO qa_runs (run_type, triggered_from, executed_by_user_id, started_at, environment)
                 VALUES (\'regex_suite\', :f, :u, :s, :e)',
                ['f' => $from, 'u' => $userId, 's' => $started, 'e' => $this->environment()]
            );
            $passed = $failed = $blocked = 0;
            foreach ($cases as $case) {
                $executedAt = $this->app->clock->nowSql();
                if (!Rules::exists((string) $case['rule_key']) || $case['expected_match'] === null) {
                    $status = 'blocked';
                    $actual = 'Rule no longer exists or expected outcome missing';
                    $match = null;
                    $ms = null;
                    $blocked++;
                } else {
                    $result = $this->evaluate((string) $case['rule_key'], (string) $case['input_value']);
                    $match = $result['accepted'] ? 1 : 0;
                    $status = $match === (int) $case['expected_match'] ? 'pass' : 'fail';
                    $actual = $result['accepted'] ? 'Accepted by the validator' : 'Rejected: ' . $result['message'];
                    $ms = $result['duration_ms'];
                    $status === 'pass' ? $passed++ : $failed++;
                }
                $db->insert(
                    'INSERT INTO qa_run_results (run_id, test_case_id, test_name, module, status, actual_result, actual_match, duration_ms, executed_at)
                     VALUES (:r, :c, :n, :m, :s, :a, :am, :d, :t)',
                    ['r' => $runId, 'c' => (int) $case['id'], 'n' => $case['case_code'] . ' ' . $case['title'], 'm' => $case['module'],
                     's' => $status, 'a' => mb_substr($actual, 0, 500), 'am' => $match, 'd' => $ms, 't' => $executedAt]
                );
            }
            $db->execute(
                'UPDATE qa_runs SET finished_at = :f, total = :t, passed = :p, failed = :fa, blocked = :b WHERE id = :id',
                ['f' => $this->app->clock->nowSql(), 't' => count($cases), 'p' => $passed, 'fa' => $failed, 'b' => $blocked, 'id' => $runId]
            );
            $this->app->service(AuditLog::class)->record('qa.run', 'qa_run', $runId, ['type' => 'regex_suite', 'passed' => $passed, 'failed' => $failed], $userId);
            return $runId;
        });
    }

    /**
     * Record the outcome of a manual execution performed by a QA analyst.
     *
     * @param array<string,mixed> $input status, actual_result
     * @throws ValidationFailed
     */
    public function recordManual(int $caseId, array $input, int $userId): int
    {
        $case = $this->app->db->one('SELECT id, case_code, title, module FROM qa_test_cases WHERE id = :id', ['id' => $caseId]);
        if ($case === null) {
            throw new WorkflowException('Test case not found.');
        }
        $v = new Validator($input);
        $status = $v->choice('status', ['pass' => 'Pass', 'fail' => 'Fail', 'blocked' => 'Blocked'], 'Outcome');
        $actual = $v->text('actual_result', 'Actual result', 500, true, 3);
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        return $this->app->db->transaction(function (Database $db) use ($case, $status, $actual, $userId): int {
            $now = $this->app->clock->nowSql();
            $runId = $db->insert(
                'INSERT INTO qa_runs (run_type, triggered_from, executed_by_user_id, started_at, finished_at, total, passed, failed, blocked, environment)
                 VALUES (\'manual\', \'web\', :u, :s, :f, 1, :p, :fa, :b, :e)',
                ['u' => $userId, 's' => $now, 'f' => $now, 'p' => (int) ($status === 'pass'), 'fa' => (int) ($status === 'fail'),
                 'b' => (int) ($status === 'blocked'), 'e' => $this->environment()]
            );
            $db->insert(
                'INSERT INTO qa_run_results (run_id, test_case_id, test_name, module, status, actual_result, executed_at)
                 VALUES (:r, :c, :n, :m, :s, :a, :t)',
                ['r' => $runId, 'c' => (int) $case['id'], 'n' => $case['case_code'] . ' ' . $case['title'], 'm' => $case['module'],
                 's' => $status, 'a' => $actual, 't' => $now]
            );
            $this->app->service(AuditLog::class)->record('qa.manual_result', 'qa_test_case', (int) $case['id'], ['status' => $status], $userId);
            return $runId;
        });
    }

    /**
     * Import a PHPUnit JUnit XML report as a run. Each <testcase> becomes a
     * result row; timings and outcomes are copied from the report.
     */
    public function importJunit(string $path, ?int $userId = null): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('JUnit report not found: ' . $path);
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new RuntimeException('Could not parse JUnit report.');
        }
        $cases = $xml->xpath('//testcase') ?: [];
        $started = date('Y-m-d H:i:s', (int) filemtime($path));
        return $this->app->db->transaction(function (Database $db) use ($cases, $started, $userId): int {
            $runId = $db->insert(
                'INSERT INTO qa_runs (run_type, triggered_from, executed_by_user_id, started_at, environment, notes)
                 VALUES (\'phpunit\', \'cli\', :u, :s, :e, :n)',
                ['u' => $userId, 's' => $started, 'e' => $this->environment(), 'n' => 'Imported from PHPUnit JUnit report']
            );
            $passed = $failed = $blocked = 0;
            foreach ($cases as $tc) {
                $class = (string) $tc['class'];
                $module = preg_match('~\\\\(Unit|Integration|Functional)\\\\~', $class, $m) ? $m[1] : 'PHPUnit';
                if (isset($tc->failure) || isset($tc->error)) {
                    $status = 'fail';
                    $node = isset($tc->failure) ? $tc->failure : $tc->error;
                    $actual = mb_substr(trim(strtok((string) $node, "\n") ?: 'Failed'), 0, 500);
                    $failed++;
                } elseif (isset($tc->skipped)) {
                    $status = 'skipped';
                    $actual = 'Skipped';
                    $blocked++;
                } else {
                    $status = 'pass';
                    $actual = (int) $tc['assertions'] . ' assertion(s) passed';
                    $passed++;
                }
                $db->insert(
                    'INSERT INTO qa_run_results (run_id, test_case_id, test_name, module, status, actual_result, duration_ms, executed_at)
                     VALUES (:r, NULL, :n, :m, :s, :a, :d, :t)',
                    ['r' => $runId, 'n' => mb_substr(substr(strrchr($class, '\\') ?: $class, 1) . '::' . $tc['name'], 0, 255),
                     'm' => $module, 's' => $status, 'a' => $actual, 'd' => round((float) $tc['time'] * 1000, 3), 't' => $started]
                );
            }
            $db->execute('UPDATE qa_runs SET finished_at = :f, total = :t, passed = :p, failed = :fa, blocked = :b WHERE id = :id', [
                'f' => $started, 't' => count($cases), 'p' => $passed, 'fa' => $failed, 'b' => $blocked, 'id' => $runId,
            ]);
            return $runId;
        });
    }

    /**
     * Record catalogue (manual/functional) cases that are automated by
     * PHPUnit, using database/qa/automation-map.json. A case passes only if
     * every mapped test appears in the JUnit report and passed; it fails if
     * any mapped test failed, and is blocked if a mapped test is missing.
     */
    public function recordCoverage(string $junitPath, ?string $mapPath = null): int
    {
        $mapPath ??= VQ_ROOT . '/database/qa/automation-map.json';
        $map = json_decode((string) file_get_contents($mapPath), true, 512, JSON_THROW_ON_ERROR);
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($junitPath, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            throw new RuntimeException('Could not parse JUnit report.');
        }
        /** @var array<string,list<string>> $outcomes "Class::method" => statuses (one per data set) */
        $outcomes = [];
        foreach ($xml->xpath('//testcase') ?: [] as $tc) {
            $class = (string) $tc['class'];
            $method = preg_replace('/ with data set .*$/', '', (string) $tc['name']);
            $key = substr(strrchr($class, '\\') ?: $class, 1) . '::' . $method;
            $outcomes[$key][] = isset($tc->failure) || isset($tc->error) ? 'fail' : (isset($tc->skipped) ? 'skipped' : 'pass');
        }
        $started = date('Y-m-d H:i:s', (int) filemtime($junitPath));

        return $this->app->db->transaction(function (Database $db) use ($map, $outcomes, $started): int {
            $runId = $db->insert(
                'INSERT INTO qa_runs (run_type, triggered_from, started_at, environment, notes)
                 VALUES (\'phpunit\', \'cli\', :s, :e, :n)',
                ['s' => $started, 'e' => $this->environment(), 'n' => 'Catalogue cases evaluated from the PHPUnit JUnit report via automation-map.json']
            );
            $passed = $failed = $blocked = 0;
            foreach ($map as $code => $tests) {
                $case = $db->one('SELECT id, case_code, title, module FROM qa_test_cases WHERE case_code = :c', ['c' => $code]);
                if ($case === null) {
                    continue;
                }
                $missing = array_values(array_filter($tests, static fn ($t) => !isset($outcomes[$t])));
                $failing = array_values(array_filter($tests, static fn ($t) => isset($outcomes[$t]) && in_array('fail', $outcomes[$t], true)));
                if ($failing !== []) {
                    $status = 'fail';
                    $actual = 'Failed in PHPUnit: ' . implode(', ', $failing);
                    $failed++;
                } elseif ($missing !== []) {
                    $status = 'blocked';
                    $actual = 'Mapped test not found in the report: ' . implode(', ', $missing);
                    $blocked++;
                } else {
                    $status = 'pass';
                    $actual = 'Automated: ' . implode(', ', $tests) . ' passed';
                    $passed++;
                }
                $db->insert(
                    'INSERT INTO qa_run_results (run_id, test_case_id, test_name, module, status, actual_result, executed_at)
                     VALUES (:r, :c, :n, :m, :s, :a, :t)',
                    ['r' => $runId, 'c' => (int) $case['id'], 'n' => $case['case_code'] . ' ' . $case['title'], 'm' => $case['module'],
                     's' => $status, 'a' => mb_substr($actual, 0, 500), 't' => $started]
                );
            }
            $db->execute('UPDATE qa_runs SET finished_at = :f, total = :t, passed = :p, failed = :fa, blocked = :b WHERE id = :id', [
                'f' => $started, 't' => $passed + $failed + $blocked, 'p' => $passed, 'fa' => $failed, 'b' => $blocked, 'id' => $runId,
            ]);
            return $runId;
        });
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function runs(int $page, int $perPage = 20): array
    {
        $total = (int) $this->app->db->value('SELECT COUNT(*) FROM qa_runs');
        $rows = $this->app->db->all(
            'SELECT r.*, u.full_name AS executed_by FROM qa_runs r LEFT JOIN users u ON u.id = r.executed_by_user_id
              ORDER BY r.id DESC LIMIT :lim OFFSET :off',
            ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public function run(int $id, string $status = ''): ?array
    {
        $run = $this->app->db->one(
            'SELECT r.*, u.full_name AS executed_by FROM qa_runs r LEFT JOIN users u ON u.id = r.executed_by_user_id WHERE r.id = :id',
            ['id' => $id]
        );
        if ($run === null) {
            return null;
        }
        $filter = in_array($status, ['pass', 'fail', 'blocked', 'skipped'], true) ? ' AND rr.status = :s' : '';
        $params = ['id' => $id] + ($filter !== '' ? ['s' => $status] : []);
        $run['results'] = $this->app->db->all(
            "SELECT rr.*, c.case_code, c.input_value, c.expected_result, c.rule_key, c.defect_id, d.code AS defect_code
               FROM qa_run_results rr
               LEFT JOIN qa_test_cases c ON c.id = rr.test_case_id
               LEFT JOIN defects d ON d.id = c.defect_id
              WHERE rr.run_id = :id{$filter} ORDER BY FIELD(rr.status, 'fail', 'blocked', 'skipped', 'pass'), rr.id",
            $params
        );
        $run['modules'] = $this->app->db->all(
            "SELECT module, COUNT(*) AS total, SUM(status = 'pass') AS passed, SUM(status = 'fail') AS failed
               FROM qa_run_results WHERE run_id = :id GROUP BY module ORDER BY module",
            ['id' => $id]
        );
        return $run;
    }

    /** @return array<string,mixed> */
    public function summary(): array
    {
        $db = $this->app->db;
        $latest = $db->all(
            'SELECT c.module, c.kind, lr.status FROM qa_test_cases c LEFT JOIN qa_run_results lr ON lr.id = ' . self::LATEST
        );
        $totals = ['cases' => count($latest), 'executed' => 0, 'pass' => 0, 'fail' => 0, 'blocked' => 0, 'not_run' => 0];
        $modules = [];
        foreach ($latest as $row) {
            $s = $row['status'] ?? 'not_run';
            $s = $s === 'skipped' ? 'blocked' : $s;
            $totals[$s]++;
            if ($s !== 'not_run') {
                $totals['executed']++;
            }
            $modules[$row['module']] ??= ['module' => $row['module'], 'cases' => 0, 'pass' => 0, 'fail' => 0, 'blocked' => 0, 'not_run' => 0];
            $modules[$row['module']]['cases']++;
            $modules[$row['module']][$s]++;
        }
        ksort($modules);
        $lastRuns = [];
        foreach (['regex_suite', 'phpunit', 'manual'] as $type) {
            $lastRuns[$type] = $db->one(
                'SELECT * FROM qa_runs WHERE run_type = :t ORDER BY id DESC LIMIT 1',
                ['t' => $type]
            );
        }
        return [
            'totals' => $totals,
            'modules' => array_values($modules),
            'last_runs' => $lastRuns,
            'run_count' => (int) $db->value('SELECT COUNT(*) FROM qa_runs'),
            'open_defects' => (int) $db->value("SELECT COUNT(*) FROM defects WHERE status IN ('open','in_progress')"),
            'recent_failures' => $db->all(
                "SELECT rr.*, c.case_code, c.id AS case_id FROM qa_run_results rr
                   LEFT JOIN qa_test_cases c ON c.id = rr.test_case_id
                  WHERE rr.status = 'fail' ORDER BY rr.id DESC LIMIT 8"
            ),
        ];
    }
}

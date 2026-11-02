<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use RuntimeException;
use Vitaliq\Core\App;
use Vitaliq\Core\Database;
use Vitaliq\Validation\Validator;

/**
 * Laboratory workflow (features 11, 13, 14, 16, 17, 18): the test catalogue
 * and reference ranges, multi-test orders, sample barcodes and lifecycle,
 * result entry with range validation, two-person verification, rejection
 * and release. Every state change runs in a transaction on a locked row.
 */
final class LaboratoryService
{
    public const SECTIONS = ['haematology' => 'Haematology', 'biochemistry' => 'Biochemistry', 'microbiology' => 'Microbiology', 'serology' => 'Serology', 'molecular' => 'Molecular'];
    public const SPECIMENS = ['whole_blood' => 'Whole blood (EDTA)', 'serum' => 'Serum', 'plasma' => 'Plasma (fluoride)', 'urine' => 'Urine', 'swab' => 'Nasopharyngeal swab', 'saliva' => 'Saliva'];
    public const PRIORITIES = ['routine' => 'Routine', 'urgent' => 'Urgent', 'stat' => 'STAT'];
    public const ORDER_STATUSES = ['ordered' => 'Ordered', 'in_progress' => 'In progress', 'verified' => 'Verified', 'released' => 'Released', 'cancelled' => 'Cancelled'];
    public const SAMPLE_STATUSES = [
        'awaiting_collection' => 'Awaiting collection', 'collected' => 'Collected', 'in_transit' => 'In transit', 'received' => 'Received',
        'in_analysis' => 'In analysis', 'stored' => 'Stored', 'disposed' => 'Disposed', 'rejected' => 'Rejected',
    ];
    /** Allowed sample moves. Rejection is possible until analysis starts. */
    public const SAMPLE_FLOW = [
        'awaiting_collection' => ['collected', 'rejected'],
        'collected' => ['in_transit', 'received', 'rejected'],
        'in_transit' => ['received', 'rejected'],
        'received' => ['in_analysis', 'rejected'],
        'in_analysis' => ['stored'],
        'stored' => ['disposed'],
        'disposed' => [],
        'rejected' => [],
    ];
    public const REJECTION_REASONS = [
        'haemolysed' => 'Haemolysed', 'clotted' => 'Clotted', 'insufficient_volume' => 'Insufficient volume', 'wrong_container' => 'Wrong container',
        'unlabelled' => 'Unlabelled', 'mislabelled' => 'Mislabelled', 'leaked' => 'Leaked in transit', 'delayed_transport' => 'Delayed transport', 'other' => 'Other',
    ];
    public const FLAGS = ['normal' => 'Normal', 'low' => 'Low', 'high' => 'High', 'critical_low' => 'Critically low', 'critical_high' => 'Critically high', 'abnormal' => 'Abnormal'];

    public function __construct(private readonly App $app)
    {
    }

    /* ----------------------------------------------------------- catalogue */

    /** Load database/seeds/lab-tests.json; existing codes are left untouched. Returns tests added. */
    public function syncCatalogue(?string $file = null): int
    {
        $data = json_decode((string) file_get_contents($file ?? VQ_ROOT . '/database/seeds/lab-tests.json'), true, 512, JSON_THROW_ON_ERROR);
        $added = 0;
        $now = $this->app->clock->nowSql();
        foreach ($data['tests'] as $t) {
            if ($this->app->db->value('SELECT id FROM lab_tests WHERE code = :c', ['c' => $t['code']]) !== null) {
                continue;
            }
            $id = $this->app->db->insert(
                'INSERT INTO lab_tests (code, name, section, specimen, result_type, unit, decimals, options, abnormal_options, turnaround_hours, created_at)
                 VALUES (:c, :n, :s, :sp, :rt, :u, :d, :o, :ao, :tat, :t)',
                ['c' => $t['code'], 'n' => $t['name'], 's' => $t['section'], 'sp' => $t['specimen'], 'rt' => $t['result_type'], 'u' => $t['unit'] ?? null,
                 'd' => (int) ($t['decimals'] ?? 1), 'o' => $t['options'] ?? null, 'ao' => $t['abnormal_options'] ?? null, 'tat' => (int) $t['turnaround_hours'], 't' => $now]
            );
            foreach ($t['ranges'] as $r) {
                $this->app->db->insert(
                    'INSERT INTO lab_reference_ranges (test_id, sex, low, high, critical_low, critical_high, updated_at) VALUES (:t, :s, :l, :h, :cl, :ch, :at)',
                    ['t' => $id, 's' => $r['sex'], 'l' => self::dec($r['low'] ?? null), 'h' => self::dec($r['high'] ?? null),
                     'cl' => self::dec($r['critical_low'] ?? null), 'ch' => self::dec($r['critical_high'] ?? null), 'at' => $now]
                );
            }
            $added++;
        }
        return $added;
    }

    private static function dec(mixed $v): ?string
    {
        return $v === null ? null : (string) $v;
    }

    /** @return list<array<string,mixed>> */
    public function tests(bool $activeOnly = true): array
    {
        return $this->app->db->all('SELECT * FROM lab_tests' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY section, name');
    }

    /** @return list<array<string,mixed>> */
    public function ranges(int $testId): array
    {
        return $this->app->db->all('SELECT * FROM lab_reference_ranges WHERE test_id = :t ORDER BY sex, age_min_years', ['t' => $testId]);
    }

    /** @param array<string,mixed> $input */
    public function saveRange(int $testId, int $rangeId, array $input, int $actorId): void
    {
        $num = static function (string $k) use ($input): ?string {
            $v = trim((string) ($input[$k] ?? ''));
            return $v === '' ? null : $v;
        };
        $vals = ['low' => $num('low'), 'high' => $num('high'), 'critical_low' => $num('critical_low'), 'critical_high' => $num('critical_high')];
        $errors = [];
        foreach ($vals as $k => $v) {
            if ($v !== null && !preg_match('/^\d{1,8}(\.\d{1,4})?$/', $v)) {
                $errors[$k] = 'Enter a number such as 4.5, or leave it blank.';
            }
        }
        $sex = (string) ($input['sex'] ?? 'any');
        if (!in_array($sex, ['any', 'female', 'male'], true)) {
            $errors['sex'] = 'Choose who the range applies to.';
        }
        $ageMin = (int) ($input['age_min_years'] ?? 0);
        $ageMax = (int) ($input['age_max_years'] ?? 120);
        if ($ageMin < 0 || $ageMax > 120 || $ageMin > $ageMax) {
            $errors['age_min_years'] = 'Ages must run from 0 to 120, lowest first.';
        }
        if ($errors === []) {
            $f = static fn (?string $v): ?float => $v === null ? null : (float) $v;
            if ($vals['low'] !== null && $vals['high'] !== null && $f($vals['low']) > $f($vals['high'])) {
                $errors['low'] = 'The lower limit must not be above the upper limit.';
            }
            if ($vals['critical_low'] !== null && $vals['low'] !== null && $f($vals['critical_low']) > $f($vals['low'])) {
                $errors['critical_low'] = 'The critical low must be at or below the lower limit.';
            }
            if ($vals['critical_high'] !== null && $vals['high'] !== null && $f($vals['critical_high']) < $f($vals['high'])) {
                $errors['critical_high'] = 'The critical high must be at or above the upper limit.';
            }
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        $params = $vals + ['sex' => $sex, 'amin' => $ageMin, 'amax' => $ageMax, 'u' => $actorId, 'at' => $this->app->clock->nowSql(), 't' => $testId];
        if ($rangeId > 0) {
            $n = $this->app->db->execute(
                'UPDATE lab_reference_ranges SET sex = :sex, age_min_years = :amin, age_max_years = :amax, low = :low, high = :high,
                   critical_low = :critical_low, critical_high = :critical_high, updated_by_user_id = :u, updated_at = :at
                 WHERE id = :id AND test_id = :t',
                $params + ['id' => $rangeId]
            );
            if ($n === 0 && $this->app->db->value('SELECT id FROM lab_reference_ranges WHERE id = :id AND test_id = :t', ['id' => $rangeId, 't' => $testId]) === null) {
                throw new WorkflowException('Range not found.');
            }
        } else {
            $this->app->db->insert(
                'INSERT INTO lab_reference_ranges (test_id, sex, age_min_years, age_max_years, low, high, critical_low, critical_high, updated_by_user_id, updated_at)
                 VALUES (:t, :sex, :amin, :amax, :low, :high, :critical_low, :critical_high, :u, :at)',
                $params
            );
        }
        $this->app->service(AuditLog::class)->record('lab.range_saved', 'lab_test', $testId, ['sex' => $sex], $actorId);
    }

    /**
     * The most specific range for a patient: a sex-specific row beats "any",
     * then the narrowest age band.
     *
     * @return array<string,mixed>|null
     */
    public function resolveRange(int $testId, string $sex, int $ageYears): ?array
    {
        return $this->app->db->one(
            "SELECT * FROM lab_reference_ranges
              WHERE test_id = :t AND (sex = 'any' OR sex = :s) AND :a1 BETWEEN age_min_years AND age_max_years
              ORDER BY (sex = 'any') ASC, (age_max_years - age_min_years) ASC, id ASC LIMIT 1",
            ['t' => $testId, 's' => $sex, 'a1' => $ageYears]
        );
    }

    /**
     * Validate a raw result against the test definition and range.
     *
     * @param array<string,mixed> $test
     * @param array<string,mixed>|null $range
     * @return array{value_numeric:?string,value_text:?string,flag:string}
     */
    public static function evaluate(array $test, ?array $range, string $raw): array
    {
        $raw = trim($raw);
        if ($test['result_type'] === 'qualitative') {
            $options = explode('|', (string) $test['options']);
            if (!in_array($raw, $options, true)) {
                throw new ValidationFailed(['value' => 'Choose one of: ' . implode(', ', $options) . '.']);
            }
            $abnormal = explode('|', (string) ($test['abnormal_options'] ?? ''));
            return ['value_numeric' => null, 'value_text' => $raw, 'flag' => in_array($raw, $abnormal, true) ? 'abnormal' : 'normal'];
        }
        $decimals = (int) $test['decimals'];
        $pattern = $decimals === 0 ? '/^\d{1,7}$/' : '/^\d{1,7}(\.\d{1,' . $decimals . '})?$/';
        if (!preg_match($pattern, $raw)) {
            throw new ValidationFailed(['value' => 'Enter a number with at most ' . $decimals . ' decimal ' . ($decimals === 1 ? 'place' : 'places') . '.']);
        }
        $v = (float) $raw;
        $flag = 'normal';
        if ($range !== null) {
            $lt = static fn (?string $lim): bool => $lim !== null && $v < (float) $lim;
            $gt = static fn (?string $lim): bool => $lim !== null && $v > (float) $lim;
            $flag = match (true) {
                $lt($range['critical_low']) => 'critical_low',
                $gt($range['critical_high']) => 'critical_high',
                $lt($range['low']) => 'low',
                $gt($range['high']) => 'high',
                default => 'normal',
            };
        }
        return ['value_numeric' => number_format($v, $decimals, '.', ''), 'value_text' => null, 'flag' => $flag];
    }

    /* ---------------------------------------------------------- barcodes */

    public static function luhnCheckDigit(string $digits): int
    {
        $sum = 0;
        $double = true;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $d = (int) $digits[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $double = !$double;
        }
        return (10 - $sum % 10) % 10;
    }

    public static function barcodeValid(string $code): bool
    {
        return (bool) preg_match('/^\d{12}$/', $code) && self::luhnCheckDigit(substr($code, 0, 11)) === (int) $code[11];
    }

    private function newBarcode(Database $db): string
    {
        for ($i = 0; $i < 10; $i++) {
            $body = (string) random_int(1, 9) . str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
            $code = $body . self::luhnCheckDigit($body);
            if ($db->value('SELECT id FROM lab_samples WHERE barcode = :b', ['b' => $code]) === null) {
                return $code;
            }
        }
        throw new RuntimeException('Could not allocate a sample barcode.');
    }

    /* -------------------------------------------------------------- orders */

    /**
     * @param array<string,mixed> $input patient_id, tests[], priority, clinical_notes, request_id
     * @param array{id:int,role:string} $actor
     */
    public function createOrder(array $input, array $actor): int
    {
        $v = new Validator($input);
        $priority = $v->choice('priority', self::PRIORITIES, 'Priority', false) ?? 'routine';
        $notes = $v->text('clinical_notes', 'Clinical notes', 500, false);
        $patientId = (int) ($input['patient_id'] ?? 0);
        $patient = $patientId > 0 ? $this->app->db->one('SELECT id, sex, date_of_birth FROM patients WHERE id = :id', ['id' => $patientId]) : null;
        if ($patient === null) {
            $v->addError('patient_id', 'Choose the patient.');
        }
        $ids = array_values(array_unique(array_map('intval', is_array($input['tests'] ?? null) ? $input['tests'] : [])));
        $tests = [];
        if ($ids !== []) {
            $in = implode(',', $ids);
            $tests = $this->app->db->all("SELECT * FROM lab_tests WHERE is_active = 1 AND id IN ({$in})");
        }
        if ($ids === [] || count($tests) !== count($ids)) {
            $v->addError('tests', 'Choose at least one active test.');
        }
        if (count($ids) > 20) {
            $v->addError('tests', 'An order can hold up to 20 tests.');
        }
        $requestId = (int) ($input['request_id'] ?? 0);
        if ($requestId > 0 && $patient !== null
            && (int) $this->app->db->value('SELECT patient_id FROM test_requests WHERE id = :id', ['id' => $requestId]) !== $patientId) {
            $v->addError('request_id', 'That testing request belongs to another patient.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }

        return $this->app->db->transaction(function (Database $db) use ($patientId, $tests, $priority, $notes, $requestId, $actor): int {
            $now = $this->app->clock->now();
            $prefix = 'LO-' . $now->format('ymd') . '-';
            $seq = (int) $db->value("SELECT COUNT(*) FROM lab_orders WHERE order_no LIKE :p FOR UPDATE", ['p' => $prefix . '%']) + 1;
            $orderNo = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $orderId = $db->insert(
                'INSERT INTO lab_orders (order_no, patient_id, request_id, facility_id, ordered_by_user_id, priority, clinical_notes, created_at)
                 VALUES (:n, :p, :r, :f, :u, :pr, :c, :t)',
                ['n' => $orderNo, 'p' => $patientId, 'r' => $requestId > 0 ? $requestId : null,
                 'f' => $this->app->service(GovernanceService::class)->homeFacility($actor['id']), 'u' => $actor['id'], 'pr' => $priority,
                 'c' => $notes ?: null, 't' => $now->format('Y-m-d H:i:s')]
            );
            $samples = [];
            foreach ($tests as $t) {
                if (!isset($samples[$t['specimen']])) {
                    $samples[$t['specimen']] = $db->insert(
                        'INSERT INTO lab_samples (order_id, barcode, specimen, created_at) VALUES (:o, :b, :s, :t)',
                        ['o' => $orderId, 'b' => $this->newBarcode($db), 's' => $t['specimen'], 't' => $now->format('Y-m-d H:i:s')]
                    );
                    $this->sampleEvent($db, $samples[$t['specimen']], null, 'awaiting_collection', $actor['id'], 'Label printed');
                }
                $db->insert('INSERT INTO lab_order_items (order_id, test_id, sample_id, unit) VALUES (:o, :t, :s, :u)',
                    ['o' => $orderId, 't' => $t['id'], 's' => $samples[$t['specimen']], 'u' => $t['unit']]);
            }
            $this->app->service(AuditLog::class)->record('lab.order_created', 'lab_order', $orderId, ['tests' => count($tests), 'priority' => $priority], $actor['id']);
            return $orderId;
        });
    }

    /** @return array<string,mixed>|null */
    public function order(int $id): ?array
    {
        $o = $this->app->db->one(
            'SELECT o.*, p.full_name, p.sex, p.date_of_birth, p.mobile, p.user_id, p.managed_by_user_id, u.full_name AS ordered_by_name, r.reference AS request_reference
               FROM lab_orders o JOIN patients p ON p.id = o.patient_id LEFT JOIN users u ON u.id = o.ordered_by_user_id
               LEFT JOIN test_requests r ON r.id = o.request_id WHERE o.id = :id',
            ['id' => $id]
        );
        if ($o === null) {
            return null;
        }
        $o['items'] = $this->app->db->all(
            'SELECT i.*, t.code, t.name, t.section, t.result_type, t.options, t.decimals, s.barcode, s.status AS sample_status,
                    ue.full_name AS entered_by_name, uv.full_name AS verified_by_name
               FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id LEFT JOIN lab_samples s ON s.id = i.sample_id
               LEFT JOIN users ue ON ue.id = i.entered_by_user_id LEFT JOIN users uv ON uv.id = i.verified_by_user_id
              WHERE i.order_id = :o ORDER BY t.section, t.name',
            ['o' => $id]
        );
        $o['samples'] = $this->app->db->all('SELECT * FROM lab_samples WHERE order_id = :o ORDER BY id', ['o' => $id]);
        return $o;
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function orders(string $status = '', string $q = '', int $page = 1, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];
        if (isset(self::ORDER_STATUSES[$status])) {
            $where[] = 'o.status = :st';
            $params['st'] = $status;
        }
        $q = trim($q);
        if ($q !== '') {
            $where[] = '(o.order_no LIKE :q1 OR p.full_name LIKE :q2 OR EXISTS (SELECT 1 FROM lab_samples s WHERE s.order_id = o.id AND s.barcode = :q3))';
            $like = '%' . \Vitaliq\Core\Database::likeEscape($q) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $q];
        }
        $w = implode(' AND ', $where);
        $total = (int) $this->app->db->value("SELECT COUNT(*) FROM lab_orders o JOIN patients p ON p.id = o.patient_id WHERE {$w}", $params);
        $rows = $this->app->db->all(
            "SELECT o.*, p.full_name,
                    (SELECT COUNT(*) FROM lab_order_items i WHERE i.order_id = o.id) AS tests,
                    (SELECT COUNT(*) FROM lab_order_items i WHERE i.order_id = o.id AND i.status IN ('verified','rejected')) AS done,
                    (SELECT COUNT(*) FROM lab_order_items i WHERE i.order_id = o.id AND i.flag IN ('critical_low','critical_high')) AS critical
               FROM lab_orders o JOIN patients p ON p.id = o.patient_id
              WHERE {$w}
              ORDER BY FIELD(o.priority, 'stat', 'urgent', 'routine'), o.created_at DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @param array{id:int,role:string} $actor */
    public function cancelOrder(int $orderId, array $actor, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new ValidationFailed(['reason' => 'Give a reason of at least 5 characters.']);
        }
        $this->app->db->transaction(function (Database $db) use ($orderId, $actor, $reason): void {
            $o = $db->one('SELECT * FROM lab_orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
            if ($o === null || in_array($o['status'], ['released', 'cancelled'], true)) {
                throw new WorkflowException('Only an open order can be cancelled.');
            }
            $db->execute("UPDATE lab_orders SET status = 'cancelled', clinical_notes = CONCAT_WS(' | ', clinical_notes, :r) WHERE id = :id", ['r' => 'Cancelled: ' . mb_substr($reason, 0, 200), 'id' => $orderId]);
            $this->app->service(AuditLog::class)->record('lab.order_cancelled', 'lab_order', $orderId, [], $actor['id']);
        });
    }

    /* ------------------------------------------------------------- samples */

    /** @return array<string,mixed>|null */
    public function sampleByBarcode(string $barcode): ?array
    {
        $barcode = preg_replace('/\D/', '', $barcode) ?? '';
        return $this->app->db->one(
            'SELECT s.*, o.order_no, o.priority, o.status AS order_status, p.full_name FROM lab_samples s
               JOIN lab_orders o ON o.id = s.order_id JOIN patients p ON p.id = o.patient_id WHERE s.barcode = :b',
            ['b' => $barcode]
        );
    }

    /** @return list<array<string,mixed>> */
    public function sampleEvents(int $sampleId): array
    {
        return $this->app->db->all(
            'SELECT e.*, u.full_name FROM lab_sample_events e LEFT JOIN users u ON u.id = e.actor_user_id WHERE e.sample_id = :s ORDER BY e.id',
            ['s' => $sampleId]
        );
    }

    /** @return array{rows:list<array<string,mixed>>,counts:array<string,int>} */
    public function samples(string $status = ''): array
    {
        $params = [];
        $where = '';
        if (isset(self::SAMPLE_STATUSES[$status])) {
            $where = 'WHERE s.status = :st';
            $params['st'] = $status;
        }
        $rows = $this->app->db->all(
            "SELECT s.*, o.order_no, o.priority, p.full_name FROM lab_samples s JOIN lab_orders o ON o.id = s.order_id
               JOIN patients p ON p.id = o.patient_id {$where}
              ORDER BY FIELD(o.priority, 'stat', 'urgent', 'routine'), s.created_at DESC LIMIT 200",
            $params
        );
        $counts = array_fill_keys(array_keys(self::SAMPLE_STATUSES), 0);
        foreach ($this->app->db->all('SELECT status, COUNT(*) AS n FROM lab_samples GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        return ['rows' => $rows, 'counts' => $counts];
    }

    /**
     * @param array{id:int,role:string} $actor
     * @param array<string,mixed> $extra storage_location, rejection_reason, rejection_note
     */
    public function moveSample(int $sampleId, string $to, array $actor, string $note = '', array $extra = []): void
    {
        $this->app->db->transaction(function (Database $db) use ($sampleId, $to, $actor, $note, $extra): void {
            $s = $db->one('SELECT * FROM lab_samples WHERE id = :id FOR UPDATE', ['id' => $sampleId]);
            if ($s === null) {
                throw new WorkflowException('Sample not found.');
            }
            if (!in_array($to, self::SAMPLE_FLOW[$s['status']] ?? [], true)) {
                throw new WorkflowException('A sample that is ' . strtolower(self::SAMPLE_STATUSES[$s['status']]) . ' cannot move to ' . strtolower(self::SAMPLE_STATUSES[$to] ?? $to) . '.');
            }
            $order = $db->one('SELECT status FROM lab_orders WHERE id = :id FOR UPDATE', ['id' => $s['order_id']]);
            if ($order !== null && $order['status'] === 'cancelled' && $to !== 'disposed' && $to !== 'rejected') {
                throw new WorkflowException('The order was cancelled.');
            }
            $set = ['status = :to'];
            $params = ['to' => $to, 'id' => $sampleId];
            $now = $this->app->clock->nowSql();
            if ($to === 'collected') {
                $set[] = 'collected_at = :t';
                $params['t'] = $now;
            } elseif ($to === 'received') {
                $set[] = 'received_at = :t';
                $params['t'] = $now;
            } elseif ($to === 'stored') {
                $loc = trim((string) ($extra['storage_location'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9 .\/-]{2,40}$/', $loc)) {
                    throw new ValidationFailed(['storage_location' => 'Give a storage location such as "Fridge 2 / Rack B4".']);
                }
                $set[] = 'storage_location = :loc';
                $params['loc'] = $loc;
            } elseif ($to === 'rejected') {
                $reason = (string) ($extra['rejection_reason'] ?? '');
                if (!isset(self::REJECTION_REASONS[$reason])) {
                    throw new ValidationFailed(['rejection_reason' => 'Choose why the sample is rejected.']);
                }
                $rnote = mb_substr(trim((string) ($extra['rejection_note'] ?? '')), 0, 300);
                if ($reason === 'other' && mb_strlen($rnote) < 5) {
                    throw new ValidationFailed(['rejection_note' => 'Describe the problem.']);
                }
                $set[] = 'rejection_reason = :rr';
                $set[] = 'rejection_note = :rn';
                $params += ['rr' => $reason, 'rn' => $rnote !== '' ? $rnote : null];
                // Results not yet verified wait for a recollected sample.
                $db->execute("UPDATE lab_order_items SET status = 'pending', value_numeric = NULL, value_text = NULL, flag = NULL, entered_by_user_id = NULL, entered_at = NULL
                               WHERE sample_id = :s AND status IN ('pending','entered')", ['s' => $sampleId]);
                $note = $note !== '' ? $note : self::REJECTION_REASONS[$reason];
            }
            $db->execute('UPDATE lab_samples SET ' . implode(', ', $set) . ' WHERE id = :id', $params);
            if (in_array($to, ['received', 'in_analysis'], true)) {
                $db->execute("UPDATE lab_orders SET status = 'in_progress' WHERE id = :id AND status = 'ordered'", ['id' => $s['order_id']]);
            }
            $this->sampleEvent($db, $sampleId, (string) $s['status'], $to, $actor['id'], $note);
            if ($to === 'rejected') {
                $this->app->service(AlertEngine::class)->raise('lab.sample_rejected', ['reason' => (string) ($extra['rejection_reason'] ?? '')],
                    'Sample ' . $s['barcode'] . ' rejected: ' . $note . '.', '/lab/samples/' . $sampleId);
                $by = $db->value('SELECT ordered_by_user_id FROM lab_orders WHERE id = :id', ['id' => $s['order_id']]);
                if ($by !== null && (int) $by !== $actor['id']) {
                    $this->app->service(NotificationService::class)->notify((int) $by, 'lab_rejection', 'Sample rejected',
                        'Sample ' . $s['barcode'] . ' was rejected: ' . $note . '. A new sample is needed.', '/lab/orders/' . $s['order_id']);
                }
            }
        });
    }

    /**
     * Create a replacement sample for a rejected one and move its open tests to it.
     *
     * @param array{id:int,role:string} $actor
     */
    public function recollect(int $sampleId, array $actor): int
    {
        return $this->app->db->transaction(function (Database $db) use ($sampleId, $actor): int {
            $s = $db->one('SELECT * FROM lab_samples WHERE id = :id FOR UPDATE', ['id' => $sampleId]);
            if ($s === null || $s['status'] !== 'rejected') {
                throw new WorkflowException('Only a rejected sample can be recollected.');
            }
            if ($db->value('SELECT id FROM lab_samples WHERE replaces_sample_id = :id', ['id' => $sampleId]) !== null) {
                throw new WorkflowException('A replacement sample already exists.');
            }
            $new = $db->insert(
                'INSERT INTO lab_samples (order_id, barcode, specimen, replaces_sample_id, created_at) VALUES (:o, :b, :sp, :r, :t)',
                ['o' => $s['order_id'], 'b' => $this->newBarcode($db), 'sp' => $s['specimen'], 'r' => $sampleId, 't' => $this->app->clock->nowSql()]
            );
            $db->execute("UPDATE lab_order_items SET sample_id = :n WHERE sample_id = :o AND status = 'pending'", ['n' => $new, 'o' => $sampleId]);
            $this->sampleEvent($db, $new, null, 'awaiting_collection', $actor['id'], 'Recollection of ' . $s['barcode']);
            return $new;
        });
    }

    private function sampleEvent(Database $db, int $sampleId, ?string $from, string $to, ?int $actorId, string $note): void
    {
        $db->insert(
            'INSERT INTO lab_sample_events (sample_id, from_status, to_status, actor_user_id, note, created_at) VALUES (:s, :f, :t, :a, :n, :c)',
            ['s' => $sampleId, 'f' => $from, 't' => $to, 'a' => $actorId, 'n' => $note !== '' ? mb_substr($note, 0, 300) : null, 'c' => $this->app->clock->nowSql()]
        );
    }

    /* ------------------------------------------------------------- results */

    /** @param array{id:int,role:string} $actor */
    public function enterResult(int $itemId, string $raw, string $comment, array $actor): string
    {
        return $this->app->db->transaction(function (Database $db) use ($itemId, $raw, $comment, $actor): string {
            $item = $db->one('SELECT * FROM lab_order_items WHERE id = :id FOR UPDATE', ['id' => $itemId]);
            if ($item === null) {
                throw new WorkflowException('Test not found on this order.');
            }
            if (!in_array($item['status'], ['pending', 'entered'], true)) {
                throw new WorkflowException('This result is already ' . $item['status'] . ' and cannot be changed.');
            }
            $sample = $item['sample_id'] !== null ? $db->one('SELECT * FROM lab_samples WHERE id = :id FOR UPDATE', ['id' => $item['sample_id']]) : null;
            if ($sample === null || !in_array($sample['status'], ['received', 'in_analysis'], true)) {
                throw new WorkflowException('Results can be entered only after the sample is received by the laboratory.');
            }
            $order = $db->one('SELECT o.status, p.sex, p.date_of_birth FROM lab_orders o JOIN patients p ON p.id = o.patient_id WHERE o.id = :id', ['id' => $item['order_id']]);
            if ($order === null || $order['status'] === 'cancelled') {
                throw new WorkflowException('The order was cancelled.');
            }
            $test = $db->one('SELECT * FROM lab_tests WHERE id = :id', ['id' => $item['test_id']]);
            $age = PatientService::age((string) $order['date_of_birth'], $this->app->clock->now());
            $sex = in_array($order['sex'], ['female', 'male'], true) ? (string) $order['sex'] : 'any';
            $range = $test !== null && $test['result_type'] === 'numeric' ? $this->resolveRange((int) $test['id'], $sex, $age) : null;
            $result = self::evaluate((array) $test, $range, $raw);

            if ($sample['status'] === 'received') {
                $db->execute("UPDATE lab_samples SET status = 'in_analysis' WHERE id = :id", ['id' => $sample['id']]);
                $this->sampleEvent($db, (int) $sample['id'], 'received', 'in_analysis', $actor['id'], 'Analysis started');
            }
            $db->execute(
                "UPDATE lab_order_items SET status = 'entered', value_numeric = :vn, value_text = :vt, flag = :f, ref_low = :rl, ref_high = :rh,
                   comment = :c, entered_by_user_id = :u, entered_at = :t WHERE id = :id",
                ['vn' => $result['value_numeric'], 'vt' => $result['value_text'], 'f' => $result['flag'], 'rl' => $range['low'] ?? null, 'rh' => $range['high'] ?? null,
                 'c' => mb_substr(trim($comment), 0, 300) ?: null, 'u' => $actor['id'], 't' => $this->app->clock->nowSql(), 'id' => $itemId]
            );
            $db->execute("UPDATE lab_orders SET status = 'in_progress' WHERE id = :id AND status = 'ordered'", ['id' => $item['order_id']]);
            return $result['flag'];
        });
    }

    /**
     * Second-person verification (feature 17).
     *
     * @param array{id:int,role:string} $actor
     */
    public function verifyItem(int $itemId, array $actor): void
    {
        $this->app->db->transaction(function (Database $db) use ($itemId, $actor): void {
            $item = $db->one('SELECT i.*, t.name, t.code FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id WHERE i.id = :id FOR UPDATE', ['id' => $itemId]);
            if ($item === null || $item['status'] !== 'entered') {
                throw new WorkflowException('Only an entered result can be verified.');
            }
            if ((int) $item['entered_by_user_id'] === $actor['id']) {
                throw new WorkflowException('A result must be verified by someone other than the person who entered it.');
            }
            $db->execute("UPDATE lab_order_items SET status = 'verified', verified_by_user_id = :u, verified_at = :t WHERE id = :id",
                ['u' => $actor['id'], 't' => $this->app->clock->nowSql(), 'id' => $itemId]);
            $this->app->service(AuditLog::class)->record('lab.result_verified', 'lab_order', (int) $item['order_id'], ['item' => $itemId, 'flag' => (string) $item['flag']], $actor['id']);
            if ($item['flag'] !== null && $item['flag'] !== 'normal') {
                $this->app->service(AlertEngine::class)->raise('lab.result_flagged', ['flag' => (string) $item['flag'], 'test' => (string) $item['code']],
                    $item['name'] . ' verified as ' . strtolower(self::FLAGS[$item['flag']] ?? (string) $item['flag']) . '.', '/lab/orders/' . $item['order_id']);
            }
            if (in_array($item['flag'], ['critical_low', 'critical_high'], true)) {
                $order = $db->one('SELECT order_no, ordered_by_user_id FROM lab_orders WHERE id = :id', ['id' => $item['order_id']]);
                $title = 'Critical result on ' . ($order['order_no'] ?? '');
                $body = $item['name'] . ' is ' . strtolower(self::FLAGS[$item['flag']]) . '. Contact the requesting clinician.';
                $this->app->service(NotificationService::class)->notifyRole('admin', 'critical_result', $title, $body, '/lab/orders/' . $item['order_id']);
                if (($order['ordered_by_user_id'] ?? null) !== null) {
                    $this->app->service(NotificationService::class)->notify((int) $order['ordered_by_user_id'], 'critical_result', $title, $body, '/lab/orders/' . $item['order_id']);
                }
            }
            $this->settleOrder($db, (int) $item['order_id']);
        });
    }

    /**
     * Send an entered result back for re-entry (feature 18).
     *
     * @param array{id:int,role:string} $actor
     */
    public function returnItem(int $itemId, array $actor, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new ValidationFailed(['reason' => 'Say why the result is sent back (at least 5 characters).']);
        }
        $this->app->db->transaction(function (Database $db) use ($itemId, $actor, $reason): void {
            $item = $db->one('SELECT * FROM lab_order_items WHERE id = :id FOR UPDATE', ['id' => $itemId]);
            if ($item === null || $item['status'] !== 'entered') {
                throw new WorkflowException('Only an entered result can be sent back.');
            }
            $db->execute("UPDATE lab_order_items SET status = 'pending', value_numeric = NULL, value_text = NULL, flag = NULL,
                            comment = :c, returned_count = returned_count + 1, entered_by_user_id = NULL, entered_at = NULL WHERE id = :id",
                ['c' => 'Returned: ' . mb_substr($reason, 0, 280), 'id' => $itemId]);
            $this->app->service(AuditLog::class)->record('lab.result_returned', 'lab_order', (int) $item['order_id'], ['item' => $itemId], $actor['id']);
            if ($item['entered_by_user_id'] !== null) {
                $this->app->service(NotificationService::class)->notify((int) $item['entered_by_user_id'], 'lab_return', 'Result sent back for re-entry', $reason, '/lab/orders/' . $item['order_id']);
            }
        });
    }

    /**
     * The test cannot be reported on this order.
     *
     * @param array{id:int,role:string} $actor
     */
    public function rejectItem(int $itemId, array $actor, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new ValidationFailed(['reason' => 'Say why the test cannot be reported (at least 5 characters).']);
        }
        $this->app->db->transaction(function (Database $db) use ($itemId, $actor, $reason): void {
            $item = $db->one('SELECT * FROM lab_order_items WHERE id = :id FOR UPDATE', ['id' => $itemId]);
            if ($item === null || !in_array($item['status'], ['pending', 'entered'], true)) {
                throw new WorkflowException('Only an open test can be marked not reportable.');
            }
            $db->execute("UPDATE lab_order_items SET status = 'rejected', comment = :c WHERE id = :id", ['c' => 'Not reported: ' . mb_substr($reason, 0, 280), 'id' => $itemId]);
            $this->app->service(AuditLog::class)->record('lab.result_rejected', 'lab_order', (int) $item['order_id'], ['item' => $itemId], $actor['id']);
            $this->settleOrder($db, (int) $item['order_id']);
        });
    }

    private function settleOrder(Database $db, int $orderId): void
    {
        $open = (int) $db->value("SELECT COUNT(*) FROM lab_order_items WHERE order_id = :o AND status IN ('pending','entered')", ['o' => $orderId]);
        if ($open === 0) {
            $db->execute("UPDATE lab_orders SET status = 'verified' WHERE id = :id AND status IN ('ordered','in_progress')", ['id' => $orderId]);
        }
    }

    /** @param array{id:int,role:string} $actor */
    public function releaseOrder(int $orderId, array $actor): void
    {
        $this->app->db->transaction(function (Database $db) use ($orderId, $actor): void {
            $o = $db->one('SELECT o.*, p.user_id, p.managed_by_user_id, p.full_name FROM lab_orders o JOIN patients p ON p.id = o.patient_id WHERE o.id = :id FOR UPDATE', ['id' => $orderId]);
            if ($o === null || $o['status'] !== 'verified') {
                throw new WorkflowException('An order can be released only when every test is verified or marked not reportable.');
            }
            if ((int) $db->value("SELECT COUNT(*) FROM lab_order_items WHERE order_id = :o AND status = 'verified'", ['o' => $orderId]) === 0) {
                throw new WorkflowException('No test on this order has a verified result to release.');
            }
            $db->execute("UPDATE lab_orders SET status = 'released', released_at = :t, released_by_user_id = :u WHERE id = :id",
                ['t' => $this->app->clock->nowSql(), 'u' => $actor['id'], 'id' => $orderId]);
            $this->app->service(AuditLog::class)->record('lab.order_released', 'lab_order', $orderId, [], $actor['id']);
            $owner = $o['user_id'] ?? $o['managed_by_user_id'];
            if ($owner !== null) {
                $this->app->service(NotificationService::class)->notify((int) $owner, 'lab_result', 'Lab results ready',
                    'Results for ' . $o['full_name'] . ' (order ' . $o['order_no'] . ') are ready to view.', '/patient/lab-results/' . $orderId);
            }
        });
    }

    /** @return list<array<string,mixed>> results awaiting a second person */
    public function verificationQueue(int $excludeEnteredBy = 0): array
    {
        return $this->app->db->all(
            "SELECT i.*, t.name, t.code, t.result_type, o.order_no, o.priority, p.full_name, u.full_name AS entered_by_name,
                    (i.entered_by_user_id = :me) AS mine
               FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id JOIN lab_orders o ON o.id = i.order_id
               JOIN patients p ON p.id = o.patient_id LEFT JOIN users u ON u.id = i.entered_by_user_id
              WHERE i.status = 'entered'
              ORDER BY FIELD(i.flag, 'critical_low', 'critical_high') DESC, FIELD(o.priority, 'stat', 'urgent', 'routine'), i.entered_at",
            ['me' => $excludeEnteredBy]
        );
    }

    /**
     * Released orders for the patient portal.
     *
     * @param list<int> $patientIds
     * @return list<array<string,mixed>>
     */
    public function releasedForPatients(array $patientIds): array
    {
        if ($patientIds === []) {
            return [];
        }
        $in = implode(',', array_map('intval', $patientIds));
        return $this->app->db->all(
            "SELECT o.id, o.order_no, o.released_at, p.full_name,
                    (SELECT COUNT(*) FROM lab_order_items i WHERE i.order_id = o.id AND i.status = 'verified') AS tests,
                    (SELECT COUNT(*) FROM lab_order_items i WHERE i.order_id = o.id AND i.status = 'verified' AND i.flag <> 'normal') AS flagged
               FROM lab_orders o JOIN patients p ON p.id = o.patient_id
              WHERE o.patient_id IN ({$in}) AND o.status = 'released' ORDER BY o.released_at DESC"
        );
    }

    /** @param array<string,mixed> $item */
    public static function formatValue(array $item): string
    {
        if ($item['value_text'] !== null) {
            return (string) $item['value_text'];
        }
        if ($item['value_numeric'] === null) {
            return '—';
        }
        return number_format((float) $item['value_numeric'], (int) ($item['decimals'] ?? 1));
    }
}

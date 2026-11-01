<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use Vitaliq\Core\App;
use Vitaliq\Support\Stats;
use Vitaliq\Validation\Validator;

/**
 * Outbreak threshold alerts (feature 23). Three rule types, each evaluated
 * per state over a rolling window that ends yesterday (today is still
 * filling):
 *  - count:       positives in the window >= threshold;
 *  - positivity:  positive share (%) >= threshold, with at least min_tests;
 *  - baseline:    positives exceed the mean of the previous four windows by
 *                 more than threshold standard deviations (an EARS-style
 *                 comparison; at least one positive above the mean).
 * An alert is raised once per rule, state and window end, and notifies
 * public-health officers and administrators.
 */
final class OutbreakService
{
    public const METHODS = ['count' => 'Positive count', 'positivity' => 'Positivity (%)', 'baseline' => 'Above recent baseline (SDs)'];

    public function __construct(private readonly App $app)
    {
    }

    /** @return list<array<string,mixed>> */
    public function thresholds(): array
    {
        return $this->app->db->all('SELECT t.*, s.name AS state_name FROM surveillance_thresholds t LEFT JOIN states s ON s.code = t.state_code ORDER BY t.is_active DESC, t.disease, t.id');
    }

    /** @param array<string,mixed> $input */
    public function createThreshold(array $input, int $actorId): int
    {
        $v = new Validator($input);
        $disease = $v->choice('disease', SurveillanceService::DISEASES, 'Disease');
        $method = $v->choice('method', self::METHODS, 'Rule');
        $state = trim((string) ($input['state_code'] ?? ''));
        if ($state !== '' && $this->app->db->value('SELECT code FROM states WHERE code = :c', ['c' => $state]) === null) {
            $v->addError('state_code', 'Choose a state, or every state.');
        }
        $window = (int) ($input['window_days'] ?? 7);
        if (!in_array($window, [7, 14, 28], true)) {
            $v->addError('window_days', 'Choose a 7, 14 or 28-day window.');
        }
        $raw = trim((string) ($input['threshold'] ?? ''));
        $limits = ['count' => [1, 100000], 'positivity' => [0.1, 100], 'baseline' => [1, 5]];
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $raw) || ($method !== null && ((float) $raw < $limits[$method][0] || (float) $raw > $limits[$method][1]))) {
            $v->addError('threshold', $method === null ? 'Enter a threshold.' : 'Enter a threshold from ' . $limits[$method][0] . ' to ' . $limits[$method][1] . '.');
        }
        $minTests = (int) ($input['min_tests'] ?? 20);
        if ($minTests < 1 || $minTests > 100000) {
            $v->addError('min_tests', 'Minimum tests must be from 1 to 100,000.');
        }
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $id = $this->app->db->insert(
            'INSERT INTO surveillance_thresholds (disease, state_code, method, window_days, threshold, min_tests, created_by_user_id, created_at)
             VALUES (:d, :s, :m, :w, :t, :mt, :u, :at)',
            ['d' => $disease, 's' => $state !== '' ? $state : null, 'm' => $method, 'w' => $window, 't' => $raw, 'mt' => $minTests, 'u' => $actorId, 'at' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('surveillance.threshold_created', 'threshold', $id, ['disease' => (string) $disease, 'method' => (string) $method], $actorId);
        return $id;
    }

    public function toggleThreshold(int $id, int $actorId): void
    {
        if ($this->app->db->execute('UPDATE surveillance_thresholds SET is_active = 1 - is_active WHERE id = :id', ['id' => $id]) !== 1) {
            throw new WorkflowException('Rule not found.');
        }
        $this->app->service(AuditLog::class)->record('surveillance.threshold_toggled', 'threshold', $id, [], $actorId);
    }

    /**
     * Evaluate every active rule for the window ending on $end (default
     * yesterday). Returns the alerts raised in this run.
     *
     * @return list<array<string,mixed>>
     */
    public function evaluate(?DateTimeImmutable $end = null): array
    {
        $end ??= $this->app->clock->now()->modify('-1 day');
        $svc = $this->app->service(SurveillanceService::class);
        $raised = [];
        foreach ($this->app->db->all('SELECT * FROM surveillance_thresholds WHERE is_active = 1') as $rule) {
            $days = (int) $rule['window_days'];
            $w = $svc->window($days, $end);
            $current = [];
            foreach ($svc->counts((string) $rule['disease'], $w['from'], $w['to'], ['state_code'], (string) ($rule['state_code'] ?? '')) as $r) {
                $current[$r['state_code']] = $r;
            }
            $history = [];
            if ($rule['method'] === 'baseline') {
                for ($k = 1; $k <= 4; $k++) {
                    $hw = $svc->window($days, $end->modify('-' . ($k * $days) . ' days'));
                    foreach ($svc->counts((string) $rule['disease'], $hw['from'], $hw['to'], ['state_code'], (string) ($rule['state_code'] ?? '')) as $r) {
                        $history[$r['state_code']][$k] = $r['positives'];
                    }
                }
            }
            foreach ($current as $state => $c) {
                $threshold = (float) $rule['threshold'];
                $expected = null;
                $fire = false;
                $observed = (float) $c['positives'];
                if ($rule['method'] === 'count') {
                    $fire = $c['positives'] >= $threshold;
                } elseif ($rule['method'] === 'positivity') {
                    $observed = $c['tests'] > 0 ? $c['positives'] / $c['tests'] * 100 : 0.0;
                    $fire = $c['tests'] >= (int) $rule['min_tests'] && $observed >= $threshold;
                } else {
                    $past = array_values(array_replace([1 => 0, 2 => 0, 3 => 0, 4 => 0], $history[$state] ?? []));
                    $ms = Stats::meanSd($past);
                    $expected = $ms['mean'];
                    // A floor of 1 on the SD stops a quiet history from turning one case into an alert.
                    $fire = $c['positives'] >= $ms['mean'] + 1 && $c['positives'] > $ms['mean'] + $threshold * max(1.0, $ms['sd']);
                }
                if (!$fire) {
                    continue;
                }
                $exists = $this->app->db->value('SELECT id FROM outbreak_alerts WHERE threshold_id = :t AND state_code = :s AND window_end = :e',
                    ['t' => $rule['id'], 's' => $state, 'e' => $w['to']]);
                if ($exists !== null) {
                    continue;
                }
                $id = $this->app->db->insert(
                    'INSERT INTO outbreak_alerts (threshold_id, disease, state_code, window_start, window_end, tests, positives, observed, expected, created_at)
                     VALUES (:t, :d, :s, :ws, :we, :tests, :pos, :o, :e, :at)',
                    ['t' => $rule['id'], 'd' => $rule['disease'], 's' => $state, 'ws' => $w['from'], 'we' => $w['to'], 'tests' => $c['tests'],
                     'pos' => $c['positives'], 'o' => round($observed, 3), 'e' => $expected === null ? null : round($expected, 3), 'at' => $this->app->clock->nowSql()]
                );
                $alert = $this->alert($id);
                $raised[] = $alert;
                $title = SurveillanceService::DISEASES[$rule['disease']] . ' alert: ' . ($alert['state_name'] ?? $state);
                $body = $c['positives'] . ' positive of ' . $c['tests'] . ' tests in the ' . $days . ' days to ' . $w['to'] . ' (' . self::METHODS[$rule['method']] . ' rule).';
                foreach (['officer', 'admin'] as $role) {
                    $this->app->service(NotificationService::class)->notifyRole($role, 'outbreak_alert', $title, $body, '/surveillance/alerts');
                }
            }
        }
        return $raised;
    }

    /** @return array<string,mixed>|null */
    public function alert(int $id): ?array
    {
        return $this->app->db->one(
            'SELECT a.*, s.name AS state_name, t.method, t.threshold, t.window_days, u.full_name AS acknowledged_by_name
               FROM outbreak_alerts a JOIN surveillance_thresholds t ON t.id = a.threshold_id JOIN states s ON s.code = a.state_code
               LEFT JOIN users u ON u.id = a.acknowledged_by_user_id WHERE a.id = :id',
            ['id' => $id]
        );
    }

    /** @return list<array<string,mixed>> */
    public function alerts(string $status = ''): array
    {
        $where = in_array($status, ['open', 'acknowledged', 'closed'], true) ? 'WHERE a.status = :s' : '';
        return $this->app->db->all(
            "SELECT a.*, s.name AS state_name, t.method, t.threshold, t.window_days, u.full_name AS acknowledged_by_name
               FROM outbreak_alerts a JOIN surveillance_thresholds t ON t.id = a.threshold_id JOIN states s ON s.code = a.state_code
               LEFT JOIN users u ON u.id = a.acknowledged_by_user_id {$where}
              ORDER BY FIELD(a.status, 'open', 'acknowledged', 'closed'), a.window_end DESC, a.id DESC LIMIT 200",
            $where !== '' ? ['s' => $status] : []
        );
    }

    public function setAlertStatus(int $id, string $status, string $note, int $actorId): void
    {
        $allowed = ['open' => ['acknowledged', 'closed'], 'acknowledged' => ['closed']];
        $this->app->db->transaction(function ($db) use ($id, $status, $note, $actorId, $allowed): void {
            $a = $db->one('SELECT status FROM outbreak_alerts WHERE id = :id FOR UPDATE', ['id' => $id]);
            if ($a === null) {
                throw new WorkflowException('Alert not found.');
            }
            if (!in_array($status, $allowed[$a['status']] ?? [], true)) {
                throw new WorkflowException('This alert cannot move to ' . $status . '.');
            }
            $note = mb_substr(trim($note), 0, 500);
            if ($status === 'closed' && mb_strlen($note) < 5) {
                throw new ValidationFailed(['note' => 'Record what was done before closing the alert.']);
            }
            $db->execute('UPDATE outbreak_alerts SET status = :s, note = COALESCE(:n, note), acknowledged_by_user_id = COALESCE(acknowledged_by_user_id, :u),
                            acknowledged_at = COALESCE(acknowledged_at, :t) WHERE id = :id',
                ['s' => $status, 'n' => $note !== '' ? $note : null, 'u' => $actorId, 't' => $this->app->clock->nowSql(), 'id' => $id]);
            $this->app->service(AuditLog::class)->record('surveillance.alert_' . $status, 'outbreak_alert', $id, [], $actorId);
        });
    }
}

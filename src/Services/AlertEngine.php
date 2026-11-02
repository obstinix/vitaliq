<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * Configurable alert rules (feature 55). Workflow code raises events; every
 * active rule for that event whose match value fits sends an in-app
 * notification to a role or a person (outside channels follow each
 * person's preferences). Built-in alerts (critical results to the orderer,
 * outbreak alerts to officers) still go out regardless of rules.
 */
final class AlertEngine
{
    /** event => [label, what match_value means] */
    public const EVENTS = [
        'lab.result_flagged' => ['A verified lab result is outside its range', 'flag: low, high, critical_low, critical_high, abnormal'],
        'lab.sample_rejected' => ['A lab sample is rejected', 'rejection reason, e.g. haemolysed'],
        'lab.stock_low' => ['Lab stock falls to its reorder level', 'item SKU'],
        'appointment.missed' => ['A patient misses an appointment', 'appointment type, e.g. consultation'],
        'surveillance.alert' => ['An outbreak alert is raised', 'disease, e.g. dengue'],
        'covid.positive_released' => ['A positive COVID-19 result is released', 'state code, e.g. KA'],
    ];
    public const ROLES = ['admin' => 'Administrators', 'lab' => 'Laboratory staff', 'officer' => 'Public-health officers', 'phlebotomist' => 'Phlebotomists', 'qa' => 'QA analysts'];

    public function __construct(private readonly App $app)
    {
    }

    /**
     * Raise an event. Returns the number of rules that fired.
     *
     * @param array<string,string> $match values the rule's match_value may equal (e.g. ['flag' => 'critical_high', 'test' => 'HB'])
     */
    public function raise(string $event, array $match, string $summary, ?string $link = null): int
    {
        $rules = $this->app->db->all('SELECT * FROM alert_rules WHERE event = :e AND is_active = 1', ['e' => $event]);
        $fired = 0;
        $values = array_map('strtolower', array_values($match));
        foreach ($rules as $r) {
            if ($r['match_value'] !== null && !in_array(strtolower((string) $r['match_value']), $values, true)) {
                continue;
            }
            $notes = $this->app->service(NotificationService::class);
            $title = 'Alert: ' . $r['name'];
            if ($r['recipient_user_id'] !== null) {
                $notes->notify((int) $r['recipient_user_id'], 'alert_rule', $title, $summary, $link);
                $n = 1;
            } else {
                $n = $notes->notifyRole((string) $r['recipient_role'], 'alert_rule', $title, $summary, $link);
            }
            $this->app->db->insert('INSERT INTO alert_rule_hits (rule_id, summary, link_path, recipients, created_at) VALUES (:r, :s, :l, :n, :t)',
                ['r' => $r['id'], 's' => mb_substr($summary, 0, 300), 'l' => $link, 'n' => $n, 't' => $this->app->clock->nowSql()]);
            $fired++;
        }
        return $fired;
    }

    /** @return list<array<string,mixed>> */
    public function rules(): array
    {
        return $this->app->db->all(
            'SELECT r.*, u.full_name AS recipient_name, (SELECT COUNT(*) FROM alert_rule_hits h WHERE h.rule_id = r.id) AS hits,
                    (SELECT MAX(created_at) FROM alert_rule_hits h WHERE h.rule_id = r.id) AS last_hit
               FROM alert_rules r LEFT JOIN users u ON u.id = r.recipient_user_id ORDER BY r.is_active DESC, r.event, r.name'
        );
    }

    /** @return list<array<string,mixed>> */
    public function recentHits(int $limit = 20): array
    {
        return $this->app->db->all('SELECT h.*, r.name FROM alert_rule_hits h JOIN alert_rules r ON r.id = h.rule_id ORDER BY h.id DESC LIMIT :l', ['l' => $limit]);
    }

    /** @param array<string,mixed> $input */
    public function create(array $input, int $actorId): int
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            $errors['name'] = 'Name the rule (3 to 100 characters).';
        }
        $event = (string) ($input['event'] ?? '');
        if (!isset(self::EVENTS[$event])) {
            $errors['event'] = 'Choose what triggers the rule.';
        }
        $match = trim((string) ($input['match_value'] ?? ''));
        if ($match !== '' && !preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $match)) {
            $errors['match_value'] = 'Use letters, digits, dots, hyphens or underscores.';
        }
        $role = (string) ($input['recipient_role'] ?? '');
        $user = (int) ($input['recipient_user_id'] ?? 0);
        if ($user > 0 && $this->app->db->value("SELECT id FROM users WHERE id = :u AND role <> 'patient' AND is_active = 1", ['u' => $user]) === null) {
            $errors['recipient_user_id'] = 'Choose an active staff member.';
        }
        if ($user === 0 && !isset(self::ROLES[$role])) {
            $errors['recipient_role'] = 'Choose who is told: a role or one person.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        $id = $this->app->db->insert(
            'INSERT INTO alert_rules (name, event, match_value, recipient_role, recipient_user_id, created_by_user_id, created_at) VALUES (:n, :e, :m, :r, :u, :by, :t)',
            ['n' => $name, 'e' => $event, 'm' => $match !== '' ? $match : null, 'r' => $user > 0 ? null : $role, 'u' => $user > 0 ? $user : null, 'by' => $actorId, 't' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('alert_rule.created', 'alert_rule', $id, ['event' => $event], $actorId);
        return $id;
    }

    public function toggle(int $id, int $actorId): void
    {
        if ($this->app->db->execute('UPDATE alert_rules SET is_active = 1 - is_active WHERE id = :id', ['id' => $id]) !== 1) {
            throw new WorkflowException('Rule not found.');
        }
        $this->app->service(AuditLog::class)->record('alert_rule.toggled', 'alert_rule', $id, [], $actorId);
    }
}

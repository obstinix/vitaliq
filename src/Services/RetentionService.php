<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * Data retention and account deletion (feature 49).
 *
 * Retention removes operational records after a configurable number of
 * days. Clinical records (testing requests, results, lab orders) are never
 * purged by this job: how long they must be kept is a legal decision for
 * the operator, not a default.
 *
 * Deleting an account removes what the person put into it (documents,
 * passport, contacts, family links, preferences, sessions, notifications,
 * 2FA) and anonymises the login. Clinical records stay, detached from any
 * login, so results already issued remain traceable.
 */
final class RetentionService
{
    /** setting => [label, default days, minimum days] */
    public const POLICIES = [
        'retention_notifications_days' => ['Read notifications', 365, 30],
        'retention_deliveries_days' => ['Email, SMS and WhatsApp delivery records', 90, 7],
        'retention_sessions_days' => ['Ended or idle sign-in sessions', 90, 7],
        'retention_audit_days' => ['Audit log', 730, 365],
        'retention_imports_days' => ['Discarded or rolled-back import files', 30, 1],
    ];

    public function __construct(private readonly App $app)
    {
    }

    /** @return array<string,int> */
    public function policies(): array
    {
        $out = [];
        foreach (self::POLICIES as $k => [, $default]) {
            $out[$k] = (int) ($this->app->db->value('SELECT value FROM app_settings WHERE name = :n', ['n' => $k]) ?? $default);
        }
        return $out;
    }

    /** @param array<string,mixed> $input */
    public function savePolicies(array $input, int $actorId): void
    {
        $errors = [];
        foreach (self::POLICIES as $k => [$label, , $min]) {
            $v = (string) ($input[$k] ?? '');
            if (!preg_match('/^\d{1,5}$/', $v) || (int) $v < $min || (int) $v > 36500) {
                $errors[$k] = $label . ': keep at least ' . $min . ' days (up to 36,500).';
            }
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        foreach (self::POLICIES as $k => $_) {
            $this->app->db->execute('INSERT INTO app_settings (name, value) VALUES (:n, :v) ON DUPLICATE KEY UPDATE value = VALUES(value)', ['n' => $k, 'v' => (string) (int) $input[$k]]);
        }
        $this->app->service(AuditLog::class)->record('retention.policies_changed', 'settings', null, [], $actorId);
    }

    /**

     * Delete records past their retention.

     *

     * @return array<string,int>

     */
    public function purge(?int $actorId = null): array
    {
        $p = $this->policies();
        $now = $this->app->clock->now();
        $cut = static fn (int $days): string => $now->modify("-{$days} days")->format('Y-m-d H:i:s');
        $db = $this->app->db;
        $out = [
            'notifications' => $db->execute('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < :c', ['c' => $cut($p['retention_notifications_days'])]),
            'deliveries' => $db->execute("DELETE FROM notification_deliveries WHERE status <> 'queued' AND created_at < :c", ['c' => $cut($p['retention_deliveries_days'])]),
            'sessions' => $db->execute('DELETE FROM user_sessions WHERE (revoked_at IS NOT NULL OR last_seen_at < :c1) AND last_seen_at < :c2',
                ['c1' => $cut($p['retention_sessions_days']), 'c2' => $cut($p['retention_sessions_days'])]),
            'audit' => $db->execute('DELETE FROM audit_events WHERE created_at < :c', ['c' => $cut($p['retention_audit_days'])]),
            'rate_limits' => $db->execute('DELETE FROM rate_limits WHERE window_ends_at < :c', ['c' => $now->format('Y-m-d H:i:s')]),
        ];
        $staged = 0;
        foreach (glob(VQ_ROOT . '/storage/imports/import-*.json') ?: [] as $file) {
            if (filemtime($file) < $now->getTimestamp() - $p['retention_imports_days'] * 86400) {
                $staged += @unlink($file) ? 1 : 0;
            }
        }
        $out['import_files'] = $staged;
        $this->app->service(AuditLog::class)->record('retention.purged', 'settings', null, $out, $actorId);
        return $out;
    }

    /* ---------------------------------------------------------- deletion */

    public function request(int $userId, string $reason): int
    {
        $user = $this->app->db->one('SELECT id, full_name, email, role FROM users WHERE id = :id', ['id' => $userId]);
        if ($user === null || $user['role'] === 'admin') {
            throw new WorkflowException('Administrator accounts are removed by another administrator.');
        }
        if ($this->app->db->value("SELECT id FROM deletion_requests WHERE user_id = :u AND status = 'requested'", ['u' => $userId]) !== null) {
            throw new WorkflowException('You have already asked for your account to be deleted. An administrator will deal with it.');
        }
        $id = $this->app->db->insert(
            'INSERT INTO deletion_requests (user_id, user_label, reason, created_at) VALUES (:u, :l, :r, :t)',
            ['u' => $userId, 'l' => mb_substr($user['full_name'] . ' <' . $user['email'] . '>', 0, 140), 'r' => mb_substr(trim($reason), 0, 500) ?: null, 't' => $this->app->clock->nowSql()]
        );
        $this->app->service(AuditLog::class)->record('account.deletion_requested', 'user', $userId, [], $userId);
        $this->app->service(NotificationService::class)->notifyRole('admin', 'account', 'Account deletion requested', 'A deletion request is waiting for review.', '/admin/retention');
        return $id;
    }

    /** @return list<array<string,mixed>> */
    public function requests(): array
    {
        return $this->app->db->all("SELECT d.*, u.full_name AS processed_by_name FROM deletion_requests d LEFT JOIN users u ON u.id = d.processed_by_user_id ORDER BY FIELD(d.status, 'requested', 'completed', 'declined'), d.id DESC LIMIT 100");
    }

    public function decline(int $requestId, string $note, int $actorId): void
    {
        if (mb_strlen(trim($note)) < 5) {
            throw new ValidationFailed(['note' => 'Say why the request is declined; the person is told.']);
        }
        $r = $this->app->db->one("SELECT * FROM deletion_requests WHERE id = :id AND status = 'requested'", ['id' => $requestId]);
        if ($r === null) {
            throw new WorkflowException('Request not found or already handled.');
        }
        $this->app->db->execute("UPDATE deletion_requests SET status = 'declined', decision_note = :n, processed_by_user_id = :a, processed_at = :t WHERE id = :id",
            ['n' => mb_substr(trim($note), 0, 500), 'a' => $actorId, 't' => $this->app->clock->nowSql(), 'id' => $requestId]);
        if ($r['user_id'] !== null) {
            $this->app->service(NotificationService::class)->notify((int) $r['user_id'], 'account', 'Deletion request declined', mb_substr(trim($note), 0, 280), '/account/security');
        }
    }

    /**

     * Carry out a deletion request.

     *

     * @return array<string,int> what was removed

     */
    public function complete(int $requestId, int $actorId): array
    {
        $r = $this->app->db->one("SELECT * FROM deletion_requests WHERE id = :id AND status = 'requested'", ['id' => $requestId]);
        if ($r === null || $r['user_id'] === null) {
            throw new WorkflowException('Request not found or already handled.');
        }
        $uid = (int) $r['user_id'];
        $vault = $this->app->service(DocumentVaultService::class);
        $profiles = $this->app->service(PatientPlatformService::class)->profiles($uid);
        $done = ['documents' => 0, 'profiles' => count($profiles)];
        foreach ($profiles as $p) {
            foreach ($vault->list((int) $p['id']) as $d) {
                $done['documents'] += $vault->delete((int) $p['id'], (int) $d['id']) ? 1 : 0;
            }
        }
        $this->app->db->transaction(function ($db) use ($uid, $profiles, $requestId, $actorId): void {
            foreach ($profiles as $p) {
                $pid = (int) $p['id'];
                $db->execute('DELETE FROM health_profiles WHERE patient_id = :p', ['p' => $pid]);
                $db->execute('DELETE FROM immunisations WHERE patient_id = :p', ['p' => $pid]);
                $db->execute('DELETE FROM emergency_contacts WHERE patient_id = :p', ['p' => $pid]);
                $db->execute('UPDATE health_id_tokens SET revoked_at = COALESCE(revoked_at, :t) WHERE patient_id = :p', ['t' => $this->app->clock->nowSql(), 'p' => $pid]);
                $db->execute("UPDATE appointments SET status = 'cancelled', cancel_reason = 'Account deleted' WHERE patient_id = :p AND status IN ('scheduled','confirmed')", ['p' => $pid]);
            }
            $db->execute('UPDATE patients SET managed_by_user_id = NULL WHERE managed_by_user_id = :u', ['u' => $uid]);
            $db->execute('UPDATE patients SET user_id = NULL, email = NULL WHERE user_id = :u', ['u' => $uid]);
            foreach (['notification_preferences', 'notifications', 'user_two_factor', 'user_sessions', 'user_permissions', 'user_facilities'] as $t) {
                $db->execute("DELETE FROM {$t} WHERE user_id = :u", ['u' => $uid]);
            }
            $db->execute('DELETE FROM password_tokens WHERE user_id = :u', ['u' => $uid]);
            $db->execute(
                "UPDATE users SET full_name = 'Deleted account', email = :e, mobile = NULL, password_hash = NULL, is_active = 0,
                   session_version = session_version + 1 WHERE id = :u",
                ['e' => 'deleted-' . $uid . '@invalid.example', 'u' => $uid]
            );
            $db->execute("UPDATE deletion_requests SET status = 'completed', processed_by_user_id = :a, processed_at = :t WHERE id = :id",
                ['a' => $actorId, 't' => $this->app->clock->nowSql(), 'id' => $requestId]);
        });
        $this->app->service(AuditLog::class)->record('account.deleted', 'user', $uid, $done, $actorId);
        return $done;
    }
}

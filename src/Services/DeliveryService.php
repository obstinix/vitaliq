<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use RuntimeException;
use Vitaliq\Core\App;
use Vitaliq\Messaging\Channels;

/**
 * Delivery of notifications outside the app (features 31-35). Every
 * notification is stored in-app; for each outside channel the user has
 * switched on for that category, a delivery is queued and later worked by
 * bin/notify-worker.php with retries (1, 2, 4, 8 minutes; five attempts).
 * Preferences are opt-in: nothing is sent outside the app by default.
 */
final class DeliveryService
{
    public const CATEGORIES = [
        'results' => 'Test results and reports',
        'appointments' => 'Appointments and reminders',
        'lab' => 'Laboratory work queue',
        'alerts' => 'Outbreak alerts',
        'broadcast' => 'Announcements from the team',
        'security' => 'Security notices about your account',
    ];
    public const CHANNELS = ['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'];
    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly App $app)
    {
    }

    public function channels(): Channels
    {
        return new Channels($this->app->config, VQ_ROOT . '/storage');
    }

    /** Map a notification type to a preference category. */
    public static function category(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'result'), $type === 'lab_result', str_starts_with($type, 'status') => 'results',
            in_array($type, ['appointment', 'reminder'], true) => 'appointments',
            in_array($type, ['critical_result', 'lab_rejection', 'lab_return', 'stock_low', 'equipment'], true) => 'lab',
            $type === 'outbreak_alert' => 'alerts',
            $type === 'broadcast' => 'broadcast',
            str_starts_with($type, 'security') => 'security',
            default => 'results',
        };
    }

    /** @return array<string,array<string,bool>> category => channel => enabled */
    public function preferences(int $userId): array
    {
        $out = [];
        foreach (self::CATEGORIES as $cat => $_) {
            foreach (self::CHANNELS as $ch => $__) {
                $out[$cat][$ch] = false;
            }
        }
        foreach ($this->app->db->all('SELECT category, channel, enabled FROM notification_preferences WHERE user_id = :u', ['u' => $userId]) as $r) {
            if (isset($out[$r['category']][$r['channel']])) {
                $out[$r['category']][$r['channel']] = (int) $r['enabled'] === 1;
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $input prefs[category][channel] = '1' */
    public function savePreferences(int $userId, array $input): void
    {
        $chosen = is_array($input['prefs'] ?? null) ? $input['prefs'] : [];
        $now = $this->app->clock->nowSql();
        $this->app->db->transaction(function ($db) use ($userId, $chosen, $now): void {
            foreach (self::CATEGORIES as $cat => $_) {
                foreach (self::CHANNELS as $ch => $__) {
                    $on = isset($chosen[$cat]) && is_array($chosen[$cat]) && ($chosen[$cat][$ch] ?? '') === '1';
                    $db->execute(
                        'INSERT INTO notification_preferences (user_id, category, channel, enabled, updated_at) VALUES (:u, :c, :ch, :e, :t)
                         ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = VALUES(updated_at)',
                        ['u' => $userId, 'c' => $cat, 'ch' => $ch, 'e' => $on ? 1 : 0, 't' => $now]
                    );
                }
            }
        });
    }

    /**

     * Where each channel would deliver for this user (null when unknown).

     *

     * @return array<string,?string>

     */
    public function recipients(int $userId): array
    {
        $u = $this->app->db->one(
            'SELECT u.email, COALESCE(u.mobile, p.mobile) AS mobile FROM users u LEFT JOIN patients p ON p.user_id = u.id WHERE u.id = :id',
            ['id' => $userId]
        );
        $mobile = $u !== null && $u['mobile'] !== null ? '+91' . $u['mobile'] : null;
        return ['email' => $u['email'] ?? null, 'sms' => $mobile, 'whatsapp' => $mobile];
    }

    /** Queue outside deliveries for a stored notification. Returns how many. */
    public function enqueue(int $notificationId, int $userId, string $type): int
    {
        $cat = self::category($type);
        $prefs = $this->preferences($userId)[$cat] ?? [];
        $channels = $this->channels();
        $to = $this->recipients($userId);
        $n = 0;
        foreach ($prefs as $ch => $on) {
            if (!$on || !$channels->enabled($ch) || ($to[$ch] ?? null) === null) {
                continue;
            }
            $this->app->db->insert(
                'INSERT INTO notification_deliveries (notification_id, user_id, channel, recipient, next_attempt_at, created_at) VALUES (:n, :u, :c, :r, :t, :t2)',
                ['n' => $notificationId, 'u' => $userId, 'c' => $ch, 'r' => $to[$ch], 't' => $this->app->clock->nowSql(), 't2' => $this->app->clock->nowSql()]
            );
            $n++;
        }
        return $n;
    }

    /**

     * Work the queue.

     *

     * @return array{sent:int,simulated:int,failed:int,retry:int}

     */
    public function process(int $limit = 100): array
    {
        $now = $this->app->clock->now();
        $rows = $this->app->db->all(
            "SELECT d.*, n.title, n.body, n.link_path FROM notification_deliveries d JOIN notifications n ON n.id = d.notification_id
              WHERE d.status = 'queued' AND d.next_attempt_at <= :t ORDER BY d.id LIMIT :lim",
            ['t' => $now->format('Y-m-d H:i:s'), 'lim' => $limit]
        );
        $stats = ['sent' => 0, 'simulated' => 0, 'failed' => 0, 'retry' => 0];
        $channels = $this->channels();
        foreach ($rows as $d) {
            $link = $d['link_path'] ? "\n" . $this->app->config['url'] . $d['link_path'] : '';
            $text = $d['body'] . $link . "\n\nVitaliq. Change which messages you get at " . $this->app->config['url'] . '/account/preferences';
            try {
                $r = $channels->send((string) $d['channel'], (string) $d['recipient'], 'Vitaliq: ' . $d['title'], $text);
                $this->app->db->execute('UPDATE notification_deliveries SET status = :s, attempts = attempts + 1, provider_ref = :r, sent_at = :t, last_error = NULL WHERE id = :id',
                    ['s' => $r['status'], 'r' => $r['ref'], 't' => $now->format('Y-m-d H:i:s'), 'id' => $d['id']]);
                $stats[$r['status'] === 'simulated' ? 'simulated' : ($r['status'] === 'sent' ? 'sent' : 'failed')]++;
            } catch (RuntimeException $e) {
                $attempts = (int) $d['attempts'] + 1;
                $final = $attempts >= self::MAX_ATTEMPTS;
                $this->app->db->execute(
                    'UPDATE notification_deliveries SET status = :s, attempts = :a, next_attempt_at = :n, last_error = :e WHERE id = :id',
                    ['s' => $final ? 'failed' : 'queued', 'a' => $attempts, 'n' => $now->modify('+' . (2 ** ($attempts - 1)) . ' minutes')->format('Y-m-d H:i:s'),
                     'e' => mb_substr($e->getMessage(), 0, 300), 'id' => $d['id']]
                );
                $stats[$final ? 'failed' : 'retry']++;
            }
        }
        return $stats;
    }

    /** @return list<array<string,mixed>> */
    public function recentFor(int $userId, int $limit = 20): array
    {
        return $this->app->db->all(
            'SELECT d.channel, d.status, d.attempts, d.created_at, d.sent_at, d.last_error, n.title FROM notification_deliveries d
               JOIN notifications n ON n.id = d.notification_id WHERE d.user_id = :u ORDER BY d.id DESC LIMIT :lim',
            ['u' => $userId, 'lim' => $limit]
        );
    }

    /** @return array<string,int> */
    public function queueSummary(): array
    {
        $out = ['queued' => 0, 'sent' => 0, 'simulated' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($this->app->db->all('SELECT status, COUNT(*) AS n FROM notification_deliveries GROUP BY status') as $r) {
            $out[$r['status']] = (int) $r['n'];
        }
        return $out;
    }
}

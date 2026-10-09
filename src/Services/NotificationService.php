<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * Persistent in-app notifications. Delivery to the browser is by polling
 * GET /api/notifications; there is no push channel.
 */
final class NotificationService
{
    public function __construct(private readonly App $app)
    {
    }

    public function notify(int $userId, string $type, string $title, string $body, ?string $link = null): int
    {
        return $this->app->db->insert(
            'INSERT INTO notifications (user_id, type, title, body, link_path, created_at)
             VALUES (:u, :t, :ti, :b, :l, :c)',
            [
                'u' => $userId,
                't' => $type,
                'ti' => mb_substr($title, 0, 140),
                'b' => mb_substr($body, 0, 300),
                'l' => $link,
                'c' => $this->app->clock->nowSql(),
            ]
        );
    }

    /** Fan out to every active user with the given role. Returns the count. */
    public function notifyRole(string $role, string $type, string $title, string $body, ?string $link = null): int
    {
        $ids = $this->app->db->all('SELECT id FROM users WHERE role = :r AND is_active = 1', ['r' => $role]);
        foreach ($ids as $row) {
            $this->notify((int) $row['id'], $type, $title, $body, $link);
        }
        return count($ids);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->app->db->value(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL',
            ['u' => $userId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function latest(int $userId, int $limit = 8): array
    {
        return $this->app->db->all(
            'SELECT id, type, title, body, link_path, read_at, created_at
               FROM notifications WHERE user_id = :u ORDER BY id DESC LIMIT :lim',
            ['u' => $userId, 'lim' => $limit]
        );
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function page(int $userId, int $page, int $perPage = 20, bool $unreadOnly = false): array
    {
        $filter = $unreadOnly ? ' AND read_at IS NULL' : '';
        $total = (int) $this->app->db->value(
            "SELECT COUNT(*) FROM notifications WHERE user_id = :u{$filter}",
            ['u' => $userId]
        );
        $rows = $this->app->db->all(
            "SELECT id, type, title, body, link_path, read_at, created_at FROM notifications
              WHERE user_id = :u{$filter} ORDER BY id DESC LIMIT :lim OFFSET :off",
            ['u' => $userId, 'lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Marks one notification read. Ownership is part of the WHERE clause. */
    public function markRead(int $userId, int $id): bool
    {
        $exists = $this->app->db->value(
            'SELECT 1 FROM notifications WHERE id = :id AND user_id = :u',
            ['id' => $id, 'u' => $userId]
        );
        if ($exists === null) {
            return false;
        }
        $this->app->db->execute(
            'UPDATE notifications SET read_at = :t WHERE id = :id AND user_id = :u AND read_at IS NULL',
            ['t' => $this->app->clock->nowSql(), 'id' => $id, 'u' => $userId]
        );
        return true;
    }

    /** @return array<string,mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        return $this->app->db->one(
            'SELECT * FROM notifications WHERE id = :id AND user_id = :u',
            ['id' => $id, 'u' => $userId]
        );
    }

    public function markAllRead(int $userId): int
    {
        return $this->app->db->execute(
            'UPDATE notifications SET read_at = :t WHERE user_id = :u AND read_at IS NULL',
            ['t' => $this->app->clock->nowSql(), 'u' => $userId]
        );
    }
}

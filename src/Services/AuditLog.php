<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Core\App;

/**
 * Append-only audit trail for security-relevant and administrative events.
 * Details must never contain passwords, tokens or full health results.
 */
final class AuditLog
{
    public function __construct(private readonly App $app)
    {
    }

    /** @param array<string,scalar|null> $details */
    public function record(string $action, string $entityType, ?int $entityId, array $details = [], ?int $actorId = -1): void
    {
        if ($actorId === -1) {
            $actorId = $this->app->auth()->id();
        }
        $this->app->db->insert(
            'INSERT INTO audit_events (actor_user_id, action, entity_type, entity_id, details, ip_hash, created_at)
             VALUES (:a, :act, :t, :id, :d, :ip, :c)',
            [
                'a' => $actorId,
                'act' => $action,
                't' => $entityType,
                'id' => $entityId,
                'd' => $details === [] ? null : mb_substr(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 0, 1000),
                'ip' => $this->app->ipHash(),
                'c' => $this->app->clock->nowSql(),
            ]
        );
    }

    /**
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function recent(int $page, int $perPage = 30, string $action = ''): array
    {
        $where = '';
        $params = [];
        if ($action !== '') {
            $where = 'WHERE a.action LIKE :act';
            $params['act'] = \Vitaliq\Core\Database::likeEscape($action) . '%';
        }
        $total = (int) $this->app->db->value("SELECT COUNT(*) FROM audit_events a {$where}", $params);
        $rows = $this->app->db->all(
            "SELECT a.*, u.full_name AS actor_name, u.role AS actor_role
               FROM audit_events a LEFT JOIN users u ON u.id = a.actor_user_id
               {$where}
              ORDER BY a.id DESC LIMIT :lim OFFSET :off",
            $params + ['lim' => $perPage, 'off' => max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }
}

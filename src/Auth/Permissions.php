<?php

declare(strict_types=1);

namespace Vitaliq\Auth;

use Vitaliq\Core\App;

/**
 * Granular permissions on top of roles. Each role has a default bundle
 * (below); administrators can grant or revoke single permissions per role
 * or per user in role_permissions / user_permissions (migration 0005).
 * Administrators always hold every permission.
 */
final class Permissions
{
    /** permission => description */
    public const ALL = [
        'lab.samples' => 'Receive, scan and move samples through the lab',
        'lab.orders' => 'Create test orders and enter results',
        'lab.verify' => 'Verify and release lab results',
        'lab.inventory' => 'Manage reagent and consumable stock',
        'lab.equipment' => 'Manage equipment and maintenance logs',
        'lab.analytics' => 'View lab workload and performance',
        'surveillance.view' => 'View surveillance dashboards and reports',
        'surveillance.alerts' => 'Manage outbreak thresholds and acknowledge alerts',
        'surveillance.import' => 'Import surveillance data files',
        'admin.rbac' => 'Change role and user permissions',
        'admin.facilities' => 'Manage facilities',
        'admin.schedule' => 'Manage staff shifts',
        'admin.broadcast' => 'Send broadcast messages',
        'admin.backups' => 'Create and download database backups',
        'admin.system' => 'View system health',
        'admin.retention' => 'Configure data retention and process deletions',
        'admin.alerts' => 'Configure alert rules',
        'admin.api' => 'Issue and revoke API keys',
    ];

    public const ROLE_DEFAULTS = [
        'lab' => ['lab.samples', 'lab.orders', 'lab.verify', 'lab.inventory', 'lab.equipment', 'lab.analytics'],
        'officer' => ['surveillance.view', 'surveillance.alerts', 'surveillance.import'],
        'qa' => [],
        'phlebotomist' => [],
        'patient' => [],
    ];

    /** @var array<int,array<string,bool>> */
    private array $cache = [];

    public function __construct(private readonly App $app)
    {
    }

    /** @param array<string,mixed>|null $user */
    public function allows(?array $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->forUser((int) $user['id'], (string) $user['role'])[$permission] ?? false;
    }

    /** @return array<string,bool> */
    public function forUser(int $userId, string $role): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }
        $set = array_fill_keys(self::ROLE_DEFAULTS[$role] ?? [], true);
        foreach ($this->overrides('SELECT permission, granted FROM role_permissions WHERE role = :k', ['k' => $role]) as $p => $g) {
            $set[$p] = $g;
        }
        foreach ($this->overrides('SELECT permission, granted FROM user_permissions WHERE user_id = :k', ['k' => $userId]) as $p => $g) {
            $set[$p] = $g;
        }
        return $this->cache[$userId] = array_filter($set);
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,bool>
     */
    private function overrides(string $sql, array $params): array
    {
        $out = [];
        foreach ($this->app->db->all($sql, $params) as $row) {
            if (isset(self::ALL[$row['permission']])) {
                $out[(string) $row['permission']] = (int) $row['granted'] === 1;
            }
        }
        return $out;
    }
}

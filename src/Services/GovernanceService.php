<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use DateTimeImmutable;
use Vitaliq\Auth\Permissions;
use Vitaliq\Core\App;
use Vitaliq\Validation\Validator;

/**
 * Role and user permissions (feature 39), facilities and staff assignment
 * (feature 40) and audit analytics (feature 41).
 */
final class GovernanceService
{
    public const ROLES = ['lab' => 'Laboratory staff', 'officer' => 'Public-health officer', 'phlebotomist' => 'Phlebotomist', 'qa' => 'QA analyst', 'patient' => 'Patient'];
    public const FACILITY_KINDS = ['laboratory' => 'Laboratory', 'collection_centre' => 'Collection centre', 'hospital' => 'Hospital', 'clinic' => 'Clinic'];

    public function __construct(private readonly App $app)
    {
    }

    /* ---------------------------------------------------------------- RBAC */

    /** @return array<string,array<string,array{default:bool,effective:bool,override:?bool}>> role => permission => state */
    public function roleMatrix(): array
    {
        $overrides = [];
        foreach ($this->app->db->all('SELECT role, permission, granted FROM role_permissions') as $r) {
            $overrides[$r['role']][$r['permission']] = (int) $r['granted'] === 1;
        }
        $out = [];
        foreach (self::ROLES as $role => $_) {
            foreach (Permissions::ALL as $perm => $__) {
                $default = in_array($perm, Permissions::ROLE_DEFAULTS[$role], true);
                $override = $overrides[$role][$perm] ?? null;
                $out[$role][$perm] = ['default' => $default, 'override' => $override, 'effective' => $override ?? $default];
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $input grant[role][perm] = '1' for every box ticked */
    public function saveRoleMatrix(array $input, int $actorId): int
    {
        $grant = is_array($input['grant'] ?? null) ? $input['grant'] : [];
        $changes = 0;
        $now = $this->app->clock->nowSql();
        $this->app->db->transaction(function ($db) use ($grant, $actorId, $now, &$changes): void {
            foreach ($this->roleMatrix() as $role => $perms) {
                foreach ($perms as $perm => $state) {
                    $want = isset($grant[$role]) && is_array($grant[$role]) && ($grant[$role][$perm] ?? '') === '1';
                    if ($want === $state['default']) {
                        $changes += $db->execute('DELETE FROM role_permissions WHERE role = :r AND permission = :p', ['r' => $role, 'p' => $perm]);
                    } elseif ($state['override'] !== $want) {
                        $db->execute(
                            'INSERT INTO role_permissions (role, permission, granted, updated_by_user_id, updated_at) VALUES (:r, :p, :g, :u, :t)
                             ON DUPLICATE KEY UPDATE granted = VALUES(granted), updated_by_user_id = VALUES(updated_by_user_id), updated_at = VALUES(updated_at)',
                            ['r' => $role, 'p' => $perm, 'g' => $want ? 1 : 0, 'u' => $actorId, 't' => $now]
                        );
                        $changes++;
                    }
                }
            }
        });
        if ($changes > 0) {
            $this->app->service(AuditLog::class)->record('rbac.roles_changed', 'role', null, ['changes' => $changes], $actorId);
        }
        $this->app->permissions()->forget();
        return $changes;
    }

    /** @return array<string,?bool> permission => override (null = follows the role) */
    public function userOverrides(int $userId): array
    {
        $out = array_fill_keys(array_keys(Permissions::ALL), null);
        foreach ($this->app->db->all('SELECT permission, granted FROM user_permissions WHERE user_id = :u', ['u' => $userId]) as $r) {
            $out[$r['permission']] = (int) $r['granted'] === 1;
        }
        return $out;
    }

    /** @param array<string,mixed> $input perm[permission] = 'inherit' | 'grant' | 'deny' */
    public function saveUserOverrides(int $userId, array $input, int $actorId): void
    {
        $user = $this->app->db->one('SELECT id, role FROM users WHERE id = :id', ['id' => $userId]);
        if ($user === null || $user['role'] === 'admin') {
            throw new WorkflowException('Administrators always hold every permission.');
        }
        $choices = is_array($input['perm'] ?? null) ? $input['perm'] : [];
        $now = $this->app->clock->nowSql();
        $this->app->db->transaction(function ($db) use ($userId, $choices, $actorId, $now): void {
            foreach (Permissions::ALL as $perm => $_) {
                $c = (string) ($choices[$perm] ?? 'inherit');
                if ($c === 'grant' || $c === 'deny') {
                    $db->execute(
                        'INSERT INTO user_permissions (user_id, permission, granted, updated_by_user_id, updated_at) VALUES (:u, :p, :g, :by, :t)
                         ON DUPLICATE KEY UPDATE granted = VALUES(granted), updated_by_user_id = VALUES(updated_by_user_id), updated_at = VALUES(updated_at)',
                        ['u' => $userId, 'p' => $perm, 'g' => $c === 'grant' ? 1 : 0, 'by' => $actorId, 't' => $now]
                    );
                } else {
                    $db->execute('DELETE FROM user_permissions WHERE user_id = :u AND permission = :p', ['u' => $userId, 'p' => $perm]);
                }
            }
        });
        $this->app->service(AuditLog::class)->record('rbac.user_changed', 'user', $userId, [], $actorId);
        $this->app->permissions()->forget();
    }

    /* ---------------------------------------------------------- facilities */

    /** @return list<array<string,mixed>> */
    public function facilities(): array
    {
        return $this->app->db->all(
            'SELECT f.*, s.name AS state_name,
                    (SELECT COUNT(*) FROM user_facilities uf WHERE uf.facility_id = f.id) AS staff,
                    (SELECT COUNT(*) FROM appointments a WHERE a.facility_id = f.id) AS appointments,
                    (SELECT COUNT(*) FROM lab_orders o WHERE o.facility_id = f.id) AS orders
               FROM facilities f JOIN states s ON s.code = f.state_code ORDER BY f.is_active DESC, f.name'
        );
    }

    /** @param array<string,mixed> $input */
    public function saveFacility(int $id, array $input, int $actorId): int
    {
        $v = new Validator($input);
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if (!preg_match('/^[A-Z0-9-]{3,12}$/', $code)) {
            $v->addError('code', 'Use 3–12 capital letters, digits or hyphens.');
        } elseif ($this->app->db->value('SELECT id FROM facilities WHERE code = :c AND id <> :id', ['c' => $code, 'id' => $id]) !== null) {
            $v->addError('code', 'Another facility uses that code.');
        }
        $name = $v->text('name', 'Name', 120, true, 3);
        $kind = $v->choice('kind', self::FACILITY_KINDS, 'Type');
        $city = $v->text('city', 'City', 80, true, 2);
        $state = $v->choice('state_code', array_column($this->app->db->all('SELECT code, name FROM states'), 'name', 'code'), 'State');
        if ($v->fails()) {
            throw new ValidationFailed($v->errors());
        }
        $params = ['c' => $code, 'n' => $name, 'k' => $kind, 'ci' => $city, 's' => $state];
        if ($id > 0) {
            $this->app->db->execute('UPDATE facilities SET code = :c, name = :n, kind = :k, city = :ci, state_code = :s WHERE id = :id', $params + ['id' => $id]);
        } else {
            $id = $this->app->db->insert('INSERT INTO facilities (code, name, kind, city, state_code, created_at) VALUES (:c, :n, :k, :ci, :s, :t)', $params + ['t' => $this->app->clock->nowSql()]);
        }
        $this->app->service(AuditLog::class)->record('facility.saved', 'facility', $id, ['code' => $code], $actorId);
        return $id;
    }

    public function toggleFacility(int $id, int $actorId): void
    {
        if ($this->app->db->execute('UPDATE facilities SET is_active = 1 - is_active WHERE id = :id', ['id' => $id]) !== 1) {
            throw new WorkflowException('Facility not found.');
        }
        $this->app->service(AuditLog::class)->record('facility.toggled', 'facility', $id, [], $actorId);
    }

    /** @return list<array<string,mixed>> */
    public function facilityStaff(int $facilityId): array
    {
        return $this->app->db->all('SELECT u.id, u.full_name, u.role FROM user_facilities uf JOIN users u ON u.id = uf.user_id WHERE uf.facility_id = :f ORDER BY u.full_name', ['f' => $facilityId]);
    }

    public function assignStaff(int $facilityId, int $userId, bool $assign, int $actorId): void
    {
        $u = $this->app->db->one("SELECT id, role FROM users WHERE id = :id AND role <> 'patient'", ['id' => $userId]);
        if ($u === null || $this->app->db->value('SELECT id FROM facilities WHERE id = :f', ['f' => $facilityId]) === null) {
            throw new WorkflowException('Choose a staff member and a facility.');
        }
        if ($assign) {
            $this->app->db->execute('INSERT IGNORE INTO user_facilities (user_id, facility_id) VALUES (:u, :f)', ['u' => $userId, 'f' => $facilityId]);
        } else {
            $this->app->db->execute('DELETE FROM user_facilities WHERE user_id = :u AND facility_id = :f', ['u' => $userId, 'f' => $facilityId]);
        }
        $this->app->service(AuditLog::class)->record($assign ? 'facility.staff_added' : 'facility.staff_removed', 'facility', $facilityId, ['user' => $userId], $actorId);
    }

    /** The single facility a user works at, if exactly one. */
    public function homeFacility(int $userId): ?int
    {
        $ids = array_map('intval', array_column($this->app->db->all('SELECT facility_id FROM user_facilities WHERE user_id = :u', ['u' => $userId]), 'facility_id'));
        return count($ids) === 1 ? $ids[0] : null;
    }

    /* --------------------------------------------------------- audit trail */

    /** @return array<string,mixed> */
    public function auditAnalytics(int $days = 30): array
    {
        $db = $this->app->db;
        $now = $this->app->clock->now();
        $since = $now->modify('-' . ($days - 1) . ' days')->format('Y-m-d 00:00:00');
        $daily = [];
        for ($d = new DateTimeImmutable(substr($since, 0, 10)); $d <= $now; $d = $d->modify('+1 day')) {
            $daily[$d->format('Y-m-d')] = ['all' => 0, 'failed' => 0];
        }
        foreach ($db->all("SELECT DATE(created_at) AS d, COUNT(*) AS n, SUM(action = 'auth.login_failed') AS f FROM audit_events WHERE created_at >= :s GROUP BY DATE(created_at)", ['s' => $since]) as $r) {
            $daily[(string) $r['d']] = ['all' => (int) $r['n'], 'failed' => (int) $r['f']];
        }
        $topActions = $db->all('SELECT action, COUNT(*) AS n FROM audit_events WHERE created_at >= :s GROUP BY action ORDER BY n DESC LIMIT 12', ['s' => $since]);
        $topActors = $db->all(
            'SELECT u.full_name, u.role, COUNT(*) AS n FROM audit_events a JOIN users u ON u.id = a.actor_user_id WHERE a.created_at >= :s GROUP BY u.id ORDER BY n DESC LIMIT 8',
            ['s' => $since]
        );
        // Unusual activity: many record downloads or exports by one person in a day.
        $unusual = $db->all(
            "SELECT u.full_name, u.role, DATE(a.created_at) AS d, COUNT(*) AS n FROM audit_events a JOIN users u ON u.id = a.actor_user_id
              WHERE a.created_at >= :s AND a.action IN ('document.downloaded', 'surveillance.report_exported', 'report.exported', 'backup.downloaded')
              GROUP BY u.id, DATE(a.created_at) HAVING n >= 20 ORDER BY n DESC LIMIT 10",
            ['s' => $since]
        );
        $failedByIp = (int) $db->value(
            "SELECT COUNT(*) FROM (SELECT ip_hash FROM audit_events WHERE action = 'auth.login_failed' AND created_at >= :s GROUP BY ip_hash HAVING COUNT(*) >= 10) x",
            ['s' => $since]
        );
        return [
            'days' => $days,
            'daily' => $daily,
            'total' => array_sum(array_column($daily, 'all')),
            'failed' => array_sum(array_column($daily, 'failed')),
            'topActions' => $topActions,
            'topActors' => $topActors,
            'unusual' => $unusual,
            'noisyConnections' => $failedByIp,
        ];
    }
}

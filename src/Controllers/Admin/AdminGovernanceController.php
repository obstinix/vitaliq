<?php

declare(strict_types=1);

namespace Vitaliq\Controllers\Admin;

use Vitaliq\Auth\Permissions;
use Vitaliq\Controllers\Controller;
use Vitaliq\Core\Request;
use Vitaliq\Core\Response;
use Vitaliq\Services\BackupService;
use Vitaliq\Services\GovernanceService;
use Vitaliq\Services\SystemHealthService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Support\SvgChart;

/** Administration: permissions, facilities, audit analytics, system health and backups (features 39-44). */
final class AdminGovernanceController extends Controller
{
    private function gov(): GovernanceService
    {
        return $this->app->service(GovernanceService::class);
    }

    /* ---------------------------------------------------------------- RBAC */

    public function roles(Request $request): Response
    {
        return $this->view('admin/roles', [
            'title' => 'Roles and permissions',
            'matrix' => $this->gov()->roleMatrix(),
            'roles' => GovernanceService::ROLES,
            'permissions' => Permissions::ALL,
            'staff' => $this->app->db->all("SELECT id, full_name, role FROM users WHERE role NOT IN ('patient','admin') ORDER BY role, full_name"),
        ], 200, 'app');
    }

    public function saveRoles(Request $request): Response
    {
        $n = $this->gov()->saveRoleMatrix($request->body, $this->userId());
        $this->flash('success', $n === 0 ? 'No changes.' : "{$n} permission " . ($n === 1 ? 'change' : 'changes') . ' saved. They apply from the next page each person opens.');
        return $this->redirect('/admin/roles');
    }

    public function userPermissions(Request $request, string $id): Response
    {
        $user = $this->app->db->one('SELECT id, full_name, email, role FROM users WHERE id = :id', ['id' => (int) $id]);
        if ($user === null) {
            $this->notFound();
        }
        $effective = $this->app->permissions()->forUser((int) $user['id'], (string) $user['role']);
        return $this->view('admin/user-permissions', [
            'title' => 'Permissions for ' . $user['full_name'],
            'subject' => $user,
            'overrides' => $this->gov()->userOverrides((int) $id),
            'effective' => $effective,
            'permissions' => Permissions::ALL,
        ], 200, 'app');
    }

    public function saveUserPermissions(Request $request, string $id): Response
    {
        try {
            $this->gov()->saveUserOverrides((int) $id, $request->body, $this->userId());
            $this->flash('success', 'Permissions saved.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/users/' . (int) $id . '/permissions');
    }

    /* ---------------------------------------------------------- facilities */

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function facilities(Request $request, array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->view('admin/facilities', [
            'title' => 'Facilities', 'rows' => $this->gov()->facilities(), 'kinds' => GovernanceService::FACILITY_KINDS,
            'states' => array_column($this->app->db->all('SELECT code, name FROM states ORDER BY name'), 'name', 'code'),
            'errors' => $errors, 'old' => $old,
        ], $status, 'app');
    }

    public function createFacility(Request $request): Response
    {
        try {
            $id = $this->gov()->saveFacility(0, $request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->facilities($request, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Facility added.');
        return $this->redirect('/admin/facilities/' . $id);
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    public function facility(Request $request, string $id, array $errors = [], array $old = [], int $status = 200): Response
    {
        $f = $this->app->db->one('SELECT * FROM facilities WHERE id = :id', ['id' => (int) $id]);
        if ($f === null) {
            $this->notFound();
        }
        return $this->view('admin/facility', [
            'title' => $f['name'], 'f' => $f, 'kinds' => GovernanceService::FACILITY_KINDS,
            'states' => array_column($this->app->db->all('SELECT code, name FROM states ORDER BY name'), 'name', 'code'),
            'staff' => $this->gov()->facilityStaff((int) $id),
            'candidates' => $this->app->db->all("SELECT id, full_name, role FROM users WHERE role NOT IN ('patient') AND is_active = 1 ORDER BY full_name"),
            'errors' => $errors, 'old' => $old,
        ], $status, 'app');
    }

    public function updateFacility(Request $request, string $id): Response
    {
        try {
            $this->gov()->saveFacility((int) $id, $request->body, $this->userId());
        } catch (ValidationFailed $e) {
            return $this->facility($request, $id, $e->errors, $request->body, 422);
        }
        $this->flash('success', 'Facility saved.');
        return $this->redirect('/admin/facilities/' . (int) $id);
    }

    public function toggleFacility(Request $request, string $id): Response
    {
        try {
            $this->gov()->toggleFacility((int) $id, $this->userId());
        } catch (WorkflowException $e) {
            $this->notFound();
        }
        return $this->redirect('/admin/facilities/' . (int) $id);
    }

    public function facilityStaff(Request $request, string $id): Response
    {
        try {
            $this->gov()->assignStaff((int) $id, (int) $request->input('user_id'), $request->input('action') !== 'remove', $this->userId());
            $this->flash('success', 'Staff list updated.');
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/facilities/' . (int) $id);
    }

    /* ------------------------------------------------------ audit analytics */

    public function auditAnalytics(Request $request): Response
    {
        $days = in_array((int) $request->query('days', '30'), [7, 30, 90], true) ? (int) $request->query('days', '30') : 30;
        $a = $this->gov()->auditAnalytics($days);
        $bars = [];
        foreach ($a['daily'] as $d => $v) {
            $bars[] = ['label' => date('j M', (int) strtotime($d)), 'value' => $v['all'], 'highlight' => true];
        }
        return $this->view('admin/audit-analytics', ['title' => 'Audit analytics', 'a' => $a, 'chart' => SvgChart::bars($bars, 'Audit events per day over ' . $days . ' days; ' . $a['total'] . ' in total.')], 200, 'app');
    }

    /* ------------------------------------------------------- system health */

    public function system(Request $request): Response
    {
        return $this->view('admin/system', ['title' => 'System health', 'checks' => $this->app->service(SystemHealthService::class)->checks()], 200, 'app');
    }

    /** Public health probe for uptime monitors: no details, just a status. */
    public function probe(Request $request): Response
    {
        try {
            $this->app->db->value('SELECT 1');
            return Response::json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return Response::json(['status' => 'unavailable'], 503);
        }
    }

    /* ------------------------------------------------------------- backups */

    public function backups(Request $request): Response
    {
        return $this->view('admin/backups', ['title' => 'Backups', 'rows' => $this->app->service(BackupService::class)->list(), 'keep' => BackupService::KEEP], 200, 'app');
    }

    public function createBackup(Request $request): Response
    {
        if (!$this->app->rateLimiter()->hit('backup:' . $this->userId(), 6, 3600)) {
            $this->flash('error', 'Six backups an hour is the limit.');
            return $this->redirect('/admin/backups');
        }
        try {
            $b = $this->app->service(BackupService::class)->create($this->userId());
            $this->flash('success', 'Backup written: ' . $b['tables'] . ' tables, ' . number_format($b['rows']) . ' rows, ' . number_format($b['bytes'] / 1024, 0) . ' KB encrypted.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
        return $this->redirect('/admin/backups');
    }

    public function downloadBackup(Request $request, string $id): Response
    {
        try {
            $f = $this->app->service(BackupService::class)->file((int) $id);
        } catch (WorkflowException $e) {
            $this->flash('error', $e->getMessage());
            return $this->redirect('/admin/backups');
        }
        $this->app->service(\Vitaliq\Services\AuditLog::class)->record('backup.downloaded', 'backup', (int) $id);
        return Response::download((string) file_get_contents($f['path']), 'application/octet-stream', $f['filename']);
    }
}

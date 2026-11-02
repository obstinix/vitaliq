<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use Vitaliq\Services\BackupService;
use Vitaliq\Services\GovernanceService;
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\ScheduleService;
use Vitaliq\Services\SearchService;
use Vitaliq\Services\SystemHealthService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Tests\HttpTestCase;

final class AdministrationTest extends HttpTestCase
{
    public function testRoleMatrixAndUserOverridesChangeAccess(): void
    {
        $admin = $this->createAdmin();
        $lab = $this->createUser('lab', 'lab@vitaliq.test');
        $gov = $this->app->service(GovernanceService::class);
        $grant = [];
        foreach ($gov->roleMatrix() as $role => $perms) {
            foreach ($perms as $perm => $s) {
                if ($s['effective']) {
                    $grant[$role][$perm] = '1';
                }
            }
        }
        $this->assertSame(0, $gov->saveRoleMatrix(['grant' => $grant], $admin), 'saving the defaults changes nothing');
        unset($grant['lab']['lab.inventory']);
        $this->assertSame(1, $gov->saveRoleMatrix(['grant' => $grant], $admin));
        $this->assertSame(0, (int) $this->db->value("SELECT granted FROM role_permissions WHERE role = 'lab' AND permission = 'lab.inventory'"));

        $this->signIn('lab@vitaliq.test');
        $this->assertSame(403, $this->get('/lab/inventory')->status);
        $gov->saveUserOverrides($lab, ['perm' => ['lab.inventory' => 'grant']], $admin);
        $this->assertSame(200, $this->get('/lab/inventory')->status, 'a personal grant beats the role');

        $grant['lab']['lab.inventory'] = '1';
        $gov->saveRoleMatrix(['grant' => $grant], $admin);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM role_permissions'), 'returning to the default removes the override');
        $this->expectException(WorkflowException::class);
        $gov->saveUserOverrides($admin, ['perm' => []], $admin);
    }

    public function testFacilitiesStaffAndLabOrdersFollowTheHomeFacility(): void
    {
        $admin = $this->createAdmin();
        $tech = $this->createUser('lab', 'lab@vitaliq.test');
        $gov = $this->app->service(GovernanceService::class);
        $id = $gov->saveFacility(0, ['code' => 'blr-lab', 'name' => 'Central Laboratory', 'kind' => 'laboratory', 'city' => 'Bengaluru', 'state_code' => 'KA'], $admin);
        try {
            $gov->saveFacility(0, ['code' => 'BLR-LAB', 'name' => 'Second', 'kind' => 'clinic', 'city' => 'Bengaluru', 'state_code' => 'KA'], $admin);
            $this->fail('Duplicate code accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('code', $e->errors);
        }
        $gov->assignStaff($id, $tech, true, $admin);
        $this->assertSame($id, $gov->homeFacility($tech));

        $lab = $this->app->service(LaboratoryService::class);
        $lab->syncCatalogue();
        $patient = $this->createPatient('kavya@example.com');
        $pid = (int) $this->db->value('SELECT id FROM patients WHERE user_id = :u', ['u' => $patient]);
        $order = $lab->createOrder(['patient_id' => (string) $pid, 'tests' => [(string) $this->db->value("SELECT id FROM lab_tests WHERE code = 'HB'")]], ['id' => $tech, 'role' => 'lab']);
        $this->assertSame($id, (int) $this->db->value('SELECT facility_id FROM lab_orders WHERE id = :id', ['id' => $order]));

        $this->signIn('lab@vitaliq.test');
        $this->assertStringContainsString('Central Laboratory', $this->get('/lab')->body, 'the shell names the facility');
        $this->expectException(WorkflowException::class);
        $gov->assignStaff($id, $patient, true, $admin);
    }

    public function testShiftsRefuseOverlapsAndSpanMidnight(): void
    {
        $admin = $this->createAdmin();
        $tech = $this->createUser('lab', 'lab@vitaliq.test');
        $other = $this->createUser('lab', 'lab2@vitaliq.test');
        $svc = $this->app->service(ScheduleService::class);
        $svc->create(['user_id' => (string) $tech, 'date' => '2026-10-12', 'start' => '08:00', 'end' => '16:00', 'duty' => 'bench'], $admin);
        try {
            $svc->create(['user_id' => (string) $tech, 'date' => '2026-10-12', 'start' => '15:00', 'end' => '20:00', 'duty' => 'bench'], $admin);
            $this->fail('Overlapping shift accepted');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('overlaps', $e->errors['start']);
        }
        $night = $svc->create(['user_id' => (string) $tech, 'date' => '2026-10-12', 'start' => '22:00', 'end' => '06:00', 'duty' => 'on_call'], $admin);
        $this->assertSame('2026-10-13 06:00:00', $this->db->value('SELECT ends_at FROM staff_shifts WHERE id = :id', ['id' => $night]));
        try {
            $svc->create(['user_id' => (string) $other, 'date' => '2026-10-12', 'start' => '06:00', 'end' => '22:00', 'duty' => 'bench'], $admin);
            $this->fail('A 16-hour shift was accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('end', $e->errors);
        }
        $this->assertSame(2, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'schedule' AND user_id = :u", ['u' => $tech]));
        $svc->create(['user_id' => (string) $other, 'date' => '2026-10-13', 'start' => '09:00', 'end' => '17:00', 'duty' => 'reception'], $admin);

        $this->signIn('lab2@vitaliq.test');
        $mine = $this->get('/staff/schedule', ['week' => '2026-10-12']);
        $this->assertSame(200, $mine->status);
        $this->assertStringContainsString('09:00–17:00', $mine->body);
        $this->assertStringNotContainsString('22:00–06:00', $mine->body, 'staff see only their own shifts');
        $this->assertSame(403, $this->get('/admin/schedule')->status);
    }

    public function testBackupsAreEncryptedVerifiedAndRestorable(): void
    {
        $admin = $this->createAdmin();
        $this->createPatient('kavya@example.com');
        $svc = $this->app->service(BackupService::class);
        $b = $svc->create($admin);
        $path = VQ_ROOT . '/storage/backups/' . $b['filename'];
        $this->assertFileExists($path);
        $raw = (string) file_get_contents($path);
        $this->assertStringNotContainsString('CREATE TABLE', $raw);
        $this->assertStringNotContainsString('kavya', $raw);

        $sql = BackupService::decryptFile($path, (string) $this->app->config['key']);
        $statements = BackupService::statements($sql);
        $this->assertContains('SET FOREIGN_KEY_CHECKS = 0', $statements);
        $this->assertNotEmpty(array_filter($statements, static fn ($s) => str_starts_with($s, 'CREATE TABLE `users`')));
        $this->assertStringContainsString(bin2hex('kavya@example.com'), $sql, 'strings are hex literals');
        $this->assertGreaterThan(20, $b['tables']);

        // A tampered file is refused.
        $this->assertSame($path, $svc->file($b['id'])['path']);
        file_put_contents($path, $raw . 'x');
        try {
            $svc->file($b['id']);
            $this->fail('Tampered backup served');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('cannot be trusted', $e->getMessage());
        }
        @unlink($path);
    }

    public function testSystemHealthSearchAndAuditAnalytics(): void
    {
        $checks = array_column($this->app->service(SystemHealthService::class)->checks(), null, 'name');
        $this->assertSame('ok', $checks['Database']['state']);
        $this->assertSame('ok', $checks['Migrations']['state']);
        $this->assertSame('ok', $checks['PHP']['state']);
        $this->assertSame('{"status":"ok"}', $this->get('/api/health')->body);

        $this->createAdmin();
        $kavya = $this->createPatient('kavya@example.com');
        $this->createPatient('arjun@example.com', ['full_name' => 'Arjun Rao']);
        $search = $this->app->service(SearchService::class);
        $this->assertSame([], $search->search(['id' => 1, 'role' => 'admin'], 'k'));
        $asAdmin = $search->search(['id' => 1, 'role' => 'admin'], 'Arjun');
        $this->assertSame('Arjun Rao', $asAdmin['Patients'][0]['label']);
        $asPatient = $search->search(['id' => $kavya, 'role' => 'patient'], 'Arjun');
        $this->assertArrayNotHasKey('Patients', $asPatient, 'patients never search other people');

        $this->signIn('admin@vitaliq.test');
        $page = $this->get('/search', ['q' => 'Arjun']);
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('Arjun Rao', $page->body);
        foreach (['/admin/roles', '/admin/facilities', '/admin/audit/analytics', '/admin/system', '/admin/backups', '/admin/schedule'] as $path) {
            $this->assertSame(200, $this->get($path)->status, $path);
        }
        $this->assertStringContainsString('auth.login', $this->get('/admin/audit/analytics')->body);
    }
}

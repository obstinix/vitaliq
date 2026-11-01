<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use DateTimeImmutable;
use Vitaliq\Services\AppointmentService;
use Vitaliq\Services\DocumentVaultService;
use Vitaliq\Services\HealthIdService;
use Vitaliq\Services\PatientPlatformService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Tests\HttpTestCase;

final class PatientPlatformTest extends HttpTestCase
{
    private function facility(string $code = 'BLR-C1'): int
    {
        return $this->db->insert(
            "INSERT INTO facilities (code, name, kind, city, state_code, created_at) VALUES (:c, :n, 'collection_centre', 'Bengaluru', 'KA', NOW())",
            ['c' => $code, 'n' => 'Indiranagar Collection Centre ' . $code]
        );
    }

    private function selfPatientId(int $userId): int
    {
        return (int) $this->db->value('SELECT id FROM patients WHERE user_id = :u', ['u' => $userId]);
    }

    public function testFamilyProfilesAreScopedToTheirHolder(): void
    {
        $holder = $this->createPatient('kavya@example.com');
        $other = $this->createPatient('other@example.com');
        $this->signIn('kavya@example.com');

        $this->get('/patient/family');
        $bad = $this->post('/patient/family', ['full_name' => 'A', 'relationship' => 'cousin', 'date_of_birth' => '2030-01-01']);
        $this->assertSame(422, $bad->status);

        $ok = $this->post('/patient/family', ['full_name' => 'Ira Menon', 'relationship' => 'child', 'date_of_birth' => '2019-04-02', 'sex' => 'female']);
        $this->assertSame(303, $ok->status);
        $childId = (int) $this->db->value("SELECT id FROM patients WHERE full_name = 'Ira Menon'");
        $this->assertSame($holder, (int) $this->db->value('SELECT managed_by_user_id FROM patients WHERE id = :id', ['id' => $childId]));

        $this->assertSame(200, $this->get('/patient/passport', ['for' => (string) $childId])->status);
        // Another account's own record is not reachable by id.
        $this->assertSame(404, $this->get('/patient/passport', ['for' => (string) $this->selfPatientId($other)])->status);

        $this->post('/patient/family/' . $childId . '/remove');
        $this->assertNull($this->db->value('SELECT managed_by_user_id FROM patients WHERE id = :id', ['id' => $childId]));
        $this->assertSame(404, $this->get('/patient/passport', ['for' => (string) $childId])->status);
    }

    public function testPassportValidatesAndSaves(): void
    {
        $this->createPatient('kavya@example.com');
        $this->signIn('kavya@example.com');
        $this->get('/patient/passport');
        $bad = $this->post('/patient/passport', ['blood_group' => 'Z+', 'height_cm' => '900', 'weight_kg' => 'abc']);
        $this->assertSame(422, $bad->status);
        $this->assertStringContainsString('Height must be a number from 30 to 250', $bad->body);

        $this->post('/patient/passport', ['blood_group' => 'B+', 'height_cm' => '162', 'weight_kg' => '58.5', 'allergies' => 'Penicillin', 'organ_donor' => 'yes']);
        $row = $this->db->one('SELECT * FROM health_profiles');
        $this->assertSame('B+', $row['blood_group']);
        $this->assertSame('Penicillin', $row['allergies']);

        $this->post('/patient/passport/immunisations', ['vaccine' => 'Hepatitis B', 'dose_label' => 'Dose 2', 'given_on' => '2026-01-12']);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM immunisations'));
        $future = $this->post('/patient/passport/immunisations', ['vaccine' => 'Hepatitis B', 'given_on' => '2027-01-12']);
        $this->assertSame(422, $future->status);
    }

    public function testEmergencyContactsKeepExactlyOnePrimary(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $pid = $this->selfPatientId($user);
        $svc = $this->app->service(PatientPlatformService::class);
        $a = $svc->addContact($pid, ['full_name' => 'Ravi Menon', 'relationship' => 'Brother', 'mobile' => '9845011111']);
        $b = $svc->addContact($pid, ['full_name' => 'Lata Menon', 'relationship' => 'Mother', 'mobile' => '9845022222', 'is_primary' => '1']);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM emergency_contacts WHERE is_primary = 1'));
        $this->assertSame($b, (int) $this->db->value('SELECT id FROM emergency_contacts WHERE is_primary = 1'));
        $svc->deleteContact($pid, $b);
        $this->assertSame($a, (int) $this->db->value('SELECT id FROM emergency_contacts WHERE is_primary = 1'), 'primary passes to a remaining contact');
        for ($i = 0; $i < 4; $i++) {
            $svc->addContact($pid, ['full_name' => 'Contact ' . chr(65 + $i), 'relationship' => 'Friend', 'mobile' => '98450' . (33333 + $i)]);
        }
        $this->expectException(ValidationFailed::class);
        $svc->addContact($pid, ['full_name' => 'Sixth Person', 'relationship' => 'Friend', 'mobile' => '9845099999']);
    }

    public function testAppointmentBookingRulesAndCalendar(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $pid = $this->selfPatientId($user);
        $fac = $this->facility();
        $svc = $this->app->service(AppointmentService::class);

        // Today 10:00 is "now": an earlier slot today is refused.
        try {
            $svc->book($user, $pid, ['kind' => 'consultation', 'facility_id' => (string) $fac, 'date' => '2026-10-10', 'slot' => '09:00']);
            $this->fail('A past slot was accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('slot', $e->errors);
        }
        $id = $svc->book($user, $pid, ['kind' => 'consultation', 'facility_id' => (string) $fac, 'date' => '2026-10-12', 'slot' => '09:00']);
        try {
            $svc->book($user, $pid, ['kind' => 'vaccination', 'facility_id' => (string) $fac, 'date' => '2026-10-12', 'slot' => '09:00']);
            $this->fail('An overlapping appointment was accepted');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('already has an appointment', $e->errors['slot']);
        }
        // Facility capacity: three per slot.
        for ($i = 0; $i < 2; $i++) {
            $u = $this->createPatient("p{$i}@example.com");
            $svc->book($u, $this->selfPatientId($u), ['kind' => 'consultation', 'facility_id' => (string) $fac, 'date' => '2026-10-12', 'slot' => '09:00']);
        }
        $u = $this->createPatient('p9@example.com');
        try {
            $svc->book($u, $this->selfPatientId($u), ['kind' => 'consultation', 'facility_id' => (string) $fac, 'date' => '2026-10-12', 'slot' => '09:00']);
            $this->fail('Capacity was exceeded');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('fully booked', $e->errors['slot']);
        }
        // Teleconsultation: no facility, a private room link.
        $tele = $svc->book($user, $pid, ['kind' => 'teleconsultation', 'date' => '2026-10-13', 'slot' => '18:00']);
        $this->assertMatchesRegularExpression('#^https://meet\.jit\.si/Vitaliq-[0-9a-f]{32}$#', (string) $svc->find($tele)['meeting_url']);

        $this->signIn('kavya@example.com');
        $cal = $this->get('/patient/appointments', ['view' => 'calendar', 'month' => '2026-10']);
        $this->assertSame(200, $cal->status);
        $this->assertStringContainsString('October 2026', $cal->body);
        $this->assertStringContainsString('/patient/appointments/' . $id, $cal->body);
        $this->assertSame(404, $this->get('/patient/appointments/' . $this->db->value('SELECT id FROM appointments WHERE patient_id <> :p LIMIT 1', ['p' => $pid]))->status);

        // Reminders go once, for the next 24 hours only.
        $this->clock->freeze(new DateTimeImmutable('2026-10-11 10:00:00'));
        $this->assertSame(3, $svc->sendReminders(24));
        $this->assertSame(0, $svc->sendReminders(24));
    }

    public function testCancellationNoticeAndStaffTransitions(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $pid = $this->selfPatientId($user);
        $svc = $this->app->service(AppointmentService::class);
        $soon = $svc->book($user, $pid, ['kind' => 'teleconsultation', 'date' => '2026-10-10', 'slot' => '10:40']);
        try {
            $svc->cancelByPatient($pid, $soon, '');
            $this->fail('Late cancellation accepted');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('one hour', $e->getMessage());
        }
        $later = $svc->book($user, $pid, ['kind' => 'teleconsultation', 'date' => '2026-10-14', 'slot' => '10:40']);
        $svc->cancelByPatient($pid, $later, 'Travelling');
        $this->assertSame('cancelled', $svc->find($later)['status']);

        $admin = $this->createAdmin();
        $svc->setStatus($soon, 'confirmed', $admin);
        try {
            $svc->setStatus($soon, 'attended', $admin);
            $this->fail('Marked attended before it started');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('only after it starts', $e->getMessage());
        }
        $this->clock->freeze(new DateTimeImmutable('2026-10-10 11:30:00'));
        $svc->setStatus($soon, 'attended', $admin);
        $this->expectException(WorkflowException::class);
        $svc->setStatus($soon, 'missed', $admin);
    }

    public function testQrHealthIdOpensCardUntilRevoked(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $pid = $this->selfPatientId($user);
        $platform = $this->app->service(PatientPlatformService::class);
        $platform->savePassport($pid, ['blood_group' => 'O-', 'allergies' => 'Sulfa drugs']);
        $platform->addContact($pid, ['full_name' => 'Ravi Menon', 'relationship' => 'Brother', 'mobile' => '9845011111']);

        $this->signIn('kavya@example.com');
        $this->get('/patient/health-id');
        $page = $this->post('/patient/health-id', ['label' => 'Wallet', 'days' => '30']);
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('<svg', $page->body);
        $this->assertSame(1, preg_match('#/h/([A-Za-z0-9_-]{32})#', $page->body, $m));
        $token = $m[1];
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM health_id_tokens WHERE token_hash = :t', ['t' => $token]), 'plain token is never stored');

        $this->newBrowser();
        $card = $this->get('/h/' . $token);
        $this->assertSame(200, $card->status);
        $this->assertStringContainsString('O-', $card->body);
        $this->assertStringContainsString('Sulfa drugs', $card->body);
        $this->assertStringContainsString('Ravi Menon', $card->body);
        $this->assertSame('noindex, nofollow', $card->header('X-Robots-Tag'));
        $this->assertSame(1, (int) $this->db->value('SELECT use_count FROM health_id_tokens'));

        $tokenId = (int) $this->db->value('SELECT id FROM health_id_tokens');
        $this->app->service(HealthIdService::class)->revoke($pid, $tokenId);
        $this->assertSame(404, $this->get('/h/' . $token)->status);
        $this->assertSame(404, $this->get('/h/' . str_repeat('A', 32))->status);

        // Expiry.
        $svc = $this->app->service(HealthIdService::class);
        $issued = $svc->issue($pid, $user, 'Short', 1);
        $this->clock->freeze(new DateTimeImmutable('2026-10-12 10:00:00'));
        $this->assertNull($svc->resolve($issued['token']));
    }

    public function testDocumentVaultEncryptsAndScopesFiles(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $pid = $this->selfPatientId($user);
        $svc = $this->app->service(DocumentVaultService::class);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmp = tempnam(sys_get_temp_dir(), 'vq');
        file_put_contents($tmp, $png);

        $id = $svc->store($pid, $user, ['name' => '../../x-ray.png', 'tmp_name' => $tmp, 'size' => strlen($png), 'error' => UPLOAD_ERR_OK], ['title' => 'Chest X-ray', 'category' => 'imaging']);
        $meta = $this->db->one('SELECT * FROM patient_documents WHERE id = :id', ['id' => $id]);
        $this->assertSame('image/png', $meta['mime_type']);
        $this->assertStringNotContainsString('/', (string) $meta['original_name']);
        $onDisk = (string) file_get_contents($svc->directory() . '/' . $meta['storage_name'] . '.bin');
        $this->assertStringNotContainsString("\x89PNG", $onDisk, 'stored bytes are encrypted');

        $this->signIn('kavya@example.com');
        $dl = $this->get('/patient/documents/' . $id);
        $this->assertSame(200, $dl->status);
        $this->assertSame($png, $dl->body);
        $this->assertStringContainsString('attachment', (string) $dl->header('Content-Disposition'));

        $this->createPatient('other@example.com');
        $this->newBrowser();
        $this->signIn('other@example.com');
        $this->assertSame(404, $this->get('/patient/documents/' . $id)->status);

        $txt = tempnam(sys_get_temp_dir(), 'vq');
        file_put_contents($txt, '<?php echo 1;');
        try {
            $svc->store($pid, $user, ['name' => 'scan.png', 'tmp_name' => $txt, 'size' => 13, 'error' => UPLOAD_ERR_OK], ['title' => 'Fake', 'category' => 'other']);
            $this->fail('A non-image with a .png name was accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('document', $e->errors);
        }
        $this->assertTrue($svc->delete($pid, $id));
        $this->assertFileDoesNotExist($svc->directory() . '/' . $meta['storage_name'] . '.bin');
        @unlink($tmp);
        @unlink($txt);
    }

    public function testTimelineFeedbackAndDashboard(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $pid = $this->selfPatientId($user);
        $platform = $this->app->service(PatientPlatformService::class);
        $platform->addImmunisation($pid, ['vaccine' => 'Tetanus', 'given_on' => '2025-03-01']);
        $this->app->service(AppointmentService::class)->book($user, $pid, ['kind' => 'teleconsultation', 'date' => '2026-10-15', 'slot' => '12:00']);
        $events = $platform->timeline($pid);
        $this->assertSame(['appointment', 'immunisation'], array_column($events, 'kind'));

        $this->signIn('kavya@example.com');
        $this->assertSame(200, $this->get('/patient/timeline')->status);
        $dash = $this->get('/patient');
        $this->assertSame(200, $dash->status);
        $this->assertStringContainsString('Upcoming appointments', $dash->body);

        $this->get('/patient/feedback');
        $this->assertSame(422, $this->post('/patient/feedback', ['rating' => '9', 'category' => 'website'])->status);
        $this->assertSame(422, $this->post('/patient/feedback', ['rating' => '4', 'category' => 'website', 'request_id' => '999'])->status);
        $this->assertSame(303, $this->post('/patient/feedback', ['rating' => '2', 'category' => 'results', 'comments' => 'Slow'])->status);

        $this->createAdmin();
        $this->newBrowser();
        $this->signIn('admin@vitaliq.test');
        $admin = $this->get('/admin/feedback');
        $this->assertStringContainsString('Slow', $admin->body);
        $fid = (int) $this->db->value('SELECT id FROM patient_feedback');
        $this->post('/admin/feedback/' . $fid, ['status' => 'actioned', 'note' => 'We added a second technician.']);
        $this->assertSame('actioned', $this->db->value('SELECT status FROM patient_feedback'));
        $this->assertSame(200, $this->get('/admin/appointments', ['day' => '2026-10-15'])->status);
    }
}

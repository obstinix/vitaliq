<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use DateTimeImmutable;
use Vitaliq\Services\LabAnalyticsService;
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\LabResourcesService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Tests\HttpTestCase;

final class LaboratoryTest extends HttpTestCase
{
    private LaboratoryService $lab;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lab = $this->app->service(LaboratoryService::class);
        $this->lab->syncCatalogue();
    }

    private function testId(string $code): int
    {
        return (int) $this->db->value('SELECT id FROM lab_tests WHERE code = :c', ['c' => $code]);
    }

    /** @return array{0:int,1:int} [order id, patient id] */
    private function order(array $codes, string $sex = 'female'): array
    {
        $user = $this->createPatient('p' . random_int(1, 999999) . '@example.com', ['sex' => $sex]);
        $pid = (int) $this->db->value('SELECT id FROM patients WHERE user_id = :u', ['u' => $user]);
        $labUser = (int) ($this->db->value("SELECT id FROM users WHERE email = 'tech1@vitaliq.test'") ?? $this->createUser('lab', 'tech1@vitaliq.test'));
        $id = $this->lab->createOrder(['patient_id' => (string) $pid, 'tests' => array_map(fn ($c) => (string) $this->testId($c), $codes), 'priority' => 'urgent'], ['id' => $labUser, 'role' => 'lab']);
        return [$id, $pid];
    }

    private function actor(string $email): array
    {
        $id = $this->db->value('SELECT id FROM users WHERE email = :e', ['e' => $email]) ?? $this->createUser('lab', $email);
        return ['id' => (int) $id, 'role' => 'lab'];
    }

    private function receiveAll(int $orderId, array $actor): void
    {
        foreach ($this->db->all('SELECT id FROM lab_samples WHERE order_id = :o', ['o' => $orderId]) as $s) {
            $this->lab->moveSample((int) $s['id'], 'collected', $actor);
            $this->lab->moveSample((int) $s['id'], 'received', $actor);
        }
    }

    public function testCatalogueLoadsOnceAndBarcodesCarryACheckDigit(): void
    {
        $this->assertSame(0, $this->lab->syncCatalogue(), 'a second sync adds nothing');
        $this->assertSame(10, (int) $this->db->value('SELECT COUNT(*) FROM lab_tests'));
        $this->assertTrue(LaboratoryService::barcodeValid('79927398713' . '0') === (LaboratoryService::luhnCheckDigit('79927398713') === 0));
        $this->assertSame(3, LaboratoryService::luhnCheckDigit('7992739871'));

        [$orderId] = $this->order(['HB', 'WBC', 'CREA', 'COVPCR']);
        $samples = $this->db->all('SELECT * FROM lab_samples WHERE order_id = :o', ['o' => $orderId]);
        $this->assertCount(3, $samples, 'one sample per specimen type: whole blood, serum, swab');
        foreach ($samples as $s) {
            $this->assertTrue(LaboratoryService::barcodeValid((string) $s['barcode']));
            $bad = substr((string) $s['barcode'], 0, 11) . (((int) $s['barcode'][11] + 1) % 10);
            $this->assertFalse(LaboratoryService::barcodeValid($bad), 'a single wrong digit is caught');
        }
        $this->assertMatchesRegularExpression('/^LO-261010-0001$/', (string) $this->db->value('SELECT order_no FROM lab_orders'));
    }

    public function testRangeResolutionAndFlags(): void
    {
        $hb = $this->testId('HB');
        $female = $this->lab->resolveRange($hb, 'female', 30);
        $male = $this->lab->resolveRange($hb, 'male', 30);
        $this->assertSame('12.0000', $female['low']);
        $this->assertSame('13.5000', $male['low']);
        $test = $this->db->one('SELECT * FROM lab_tests WHERE id = :id', ['id' => $hb]);
        $this->assertSame('low', LaboratoryService::evaluate($test, $female, '11.2')['flag']);
        $this->assertSame('normal', LaboratoryService::evaluate($test, $female, '13')['flag']);
        $this->assertSame('critical_low', LaboratoryService::evaluate($test, $female, '6.5')['flag']);
        $this->assertSame('critical_high', LaboratoryService::evaluate($test, $female, '21.0')['flag']);
        $this->assertSame('13.0', LaboratoryService::evaluate($test, $female, '13')['value_numeric']);
        foreach (['-1', '12.34', 'abc', ''] as $bad) {
            try {
                LaboratoryService::evaluate($test, $female, $bad);
                $this->fail("Accepted {$bad}");
            } catch (ValidationFailed $e) {
                $this->assertArrayHasKey('value', $e->errors);
            }
        }
        $pcr = $this->db->one("SELECT * FROM lab_tests WHERE code = 'COVPCR'");
        $this->assertSame('abnormal', LaboratoryService::evaluate($pcr, null, 'Detected')['flag']);
        $this->expectException(ValidationFailed::class);
        LaboratoryService::evaluate($pcr, null, 'positive');
    }

    public function testFullWorkflowWithTwoPersonVerificationAndRelease(): void
    {
        [$orderId, $pid] = $this->order(['HB', 'GLUF']);
        $tech = $this->actor('tech1@vitaliq.test');
        $checker = $this->actor('tech2@vitaliq.test');
        $items = $this->db->all('SELECT i.id, t.code FROM lab_order_items i JOIN lab_tests t ON t.id = i.test_id WHERE order_id = :o', ['o' => $orderId]);
        $ids = array_column($items, 'id', 'code');

        try {
            $this->lab->enterResult((int) $ids['HB'], '13.1', '', $tech);
            $this->fail('Result accepted before the sample reached the lab');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('received', $e->getMessage());
        }
        $this->receiveAll($orderId, $tech);
        $this->assertSame('normal', $this->lab->enterResult((int) $ids['HB'], '13.1', '', $tech));
        $this->assertSame('critical_high', $this->lab->enterResult((int) $ids['GLUF'], '450', 'Repeat confirmed', $tech));
        $this->assertSame('in_analysis', $this->db->value("SELECT status FROM lab_samples WHERE specimen = 'whole_blood'"));

        try {
            $this->lab->verifyItem((int) $ids['HB'], $tech);
            $this->fail('The enterer verified their own result');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('someone other', $e->getMessage());
        }
        $this->lab->returnItem((int) $ids['HB'], $checker, 'Check the analyser printout');
        $this->assertSame('pending', $this->db->value('SELECT status FROM lab_order_items WHERE id = :id', ['id' => $ids['HB']]));
        $this->lab->enterResult((int) $ids['HB'], '13.4', '', $tech);
        $this->lab->verifyItem((int) $ids['HB'], $checker);
        $this->lab->verifyItem((int) $ids['GLUF'], $checker);
        $this->assertSame('verified', $this->db->value('SELECT status FROM lab_orders WHERE id = :id', ['id' => $orderId]));
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'critical_result' AND user_id = :u", ['u' => $tech['id']]), 'the orderer hears about a critical result');

        $this->lab->releaseOrder($orderId, $checker);
        $patientUser = (int) $this->db->value('SELECT user_id FROM patients WHERE id = :id', ['id' => $pid]);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'lab_result' AND user_id = :u", ['u' => $patientUser]));

        // The patient sees the released report, and nobody else does.
        $email = (string) $this->db->value('SELECT email FROM users WHERE id = :id', ['id' => $patientUser]);
        $this->signIn($email);
        $report = $this->get('/patient/lab-results/' . $orderId);
        $this->assertSame(200, $report->status);
        $this->assertStringContainsString('13.4', $report->body);
        $this->assertStringContainsString('Critically high', $report->body);
        $this->assertStringContainsString('not a diagnosis', $report->body);
        $this->createPatient('stranger@example.com');
        $this->newBrowser();
        $this->signIn('stranger@example.com');
        $this->assertSame(404, $this->get('/patient/lab-results/' . $orderId)->status);
    }

    public function testRejectionRecollectionAndNotReportable(): void
    {
        [$orderId] = $this->order(['CREA', 'CRP']);
        $tech = $this->actor('tech1@vitaliq.test');
        $sample = (int) $this->db->value('SELECT id FROM lab_samples WHERE order_id = :o', ['o' => $orderId]);
        $this->lab->moveSample($sample, 'collected', $tech);
        try {
            $this->lab->moveSample($sample, 'stored', $tech);
            $this->fail('Skipped straight to storage');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString('cannot move', $e->getMessage());
        }
        try {
            $this->lab->moveSample($sample, 'rejected', $tech, '', ['rejection_reason' => 'other']);
            $this->fail('"Other" without a note');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('rejection_note', $e->errors);
        }
        $this->lab->moveSample($sample, 'rejected', $tech, '', ['rejection_reason' => 'haemolysed']);
        $new = $this->lab->recollect($sample, $tech);
        $this->assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM lab_order_items WHERE sample_id = :s', ['s' => $new]));
        $this->expectException(WorkflowException::class);
        $this->lab->recollect($sample, $tech);
    }

    public function testNotReportableItemsSettleTheOrderAndReleaseNeedsAResult(): void
    {
        [$orderId] = $this->order(['CRP']);
        $tech = $this->actor('tech1@vitaliq.test');
        $item = (int) $this->db->value('SELECT id FROM lab_order_items WHERE order_id = :o', ['o' => $orderId]);
        $this->lab->rejectItem($item, $tech, 'Reagent lot failed QC');
        $this->assertSame('verified', $this->db->value('SELECT status FROM lab_orders'));
        $this->expectException(WorkflowException::class);
        $this->lab->releaseOrder($orderId, $tech);
    }

    public function testRangeEditorValidatesLimits(): void
    {
        $crp = $this->testId('CRP');
        $admin = $this->createAdmin();
        try {
            $this->lab->saveRange($crp, 0, ['sex' => 'any', 'low' => '5', 'high' => '3'], $admin);
            $this->fail('Inverted range accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('low', $e->errors);
        }
        $this->lab->saveRange($crp, 0, ['sex' => 'female', 'age_min_years' => '0', 'age_max_years' => '17', 'high' => '5'], $admin);
        $this->assertSame('5.0000', $this->lab->resolveRange($crp, 'female', 10)['high'], 'the narrower, sex-specific range wins');
        $this->assertSame('10.0000', $this->lab->resolveRange($crp, 'female', 30)['high']);
    }

    public function testInventoryUsesEarliestExpiryAndNeverExpiredStock(): void
    {
        $res = $this->app->service(LabResourcesService::class);
        $admin = $this->createAdmin();
        $id = $res->createItem(['sku' => 'RAT-KIT', 'name' => 'Antigen test kit', 'category' => 'kit', 'unit' => 'tests', 'reorder_level' => '20'], $admin);
        $res->receive($id, ['lot_no' => 'A1', 'expires_on' => '2026-12-31', 'quantity' => '25'], $admin);
        $res->receive($id, ['lot_no' => 'B2', 'expires_on' => '2026-10-20', 'quantity' => '10'], $admin);
        $res->consume($id, ['quantity' => '12', 'reason' => 'consumption'], $admin);
        $lots = array_column($this->db->all('SELECT lot_no, quantity FROM lab_inventory_lots'), 'quantity', 'lot_no');
        $this->assertSame(0, (int) $lots['B2'], 'earliest expiry used first');
        $this->assertSame(23, (int) $lots['A1']);

        $res->receive($id, ['lot_no' => 'C3', 'expires_on' => '2026-10-11', 'quantity' => '5'], $admin);
        $this->clock->freeze(new DateTimeImmutable('2026-10-15 09:00:00'));
        try {
            $res->consume($id, ['quantity' => '24', 'reason' => 'consumption'], $admin);
            $this->fail('Expired stock was counted');
        } catch (ValidationFailed $e) {
            $this->assertStringContainsString('Only 23', $e->errors['quantity']);
        }
        $this->assertSame(5, $res->writeOffExpired($admin));
        $this->createUser('lab', 'tech@vitaliq.test');
        $res->consume($id, ['quantity' => '5', 'reason' => 'consumption'], $admin);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'stock_low'"), 'falling to the reorder level alerts the lab');
        $this->assertSame('low', $res->inventory()[0]['state']);
    }

    public function testEquipmentCalibrationAndQcFailure(): void
    {
        $res = $this->app->service(LabResourcesService::class);
        $admin = $this->createAdmin();
        $id = $res->createEquipment(['asset_tag' => 'HAEM-01', 'name' => 'Haematology analyser', 'calibration_interval_days' => '90', 'last_calibrated_on' => '2026-06-01'], $admin);
        $this->assertSame('overdue', $res->equipment()[0]['calibration_state']);
        $res->log($id, ['kind' => 'qc_failure', 'performed_on' => '2026-10-10', 'notes' => 'Level 2 control out by 3 SD'], $admin);
        $this->assertSame('out_of_service', $this->db->value('SELECT status FROM lab_equipment'));
        $res->log($id, ['kind' => 'calibration', 'performed_on' => '2026-10-10'], $admin);
        $this->assertSame('2027-01-08', $this->db->value('SELECT calibration_due_on FROM lab_equipment'));
        $res->log($id, ['kind' => 'status_change', 'status' => 'operational', 'performed_on' => '2026-10-10'], $admin);
        $this->assertSame('ok', $res->equipment()[0]['calibration_state']);
    }

    public function testLabRoutesEnforceRoleAndPermission(): void
    {
        $this->createUser('lab', 'tech@vitaliq.test');
        $this->createPatient('kavya@example.com');
        $this->signIn('kavya@example.com');
        $this->assertSame(403, $this->get('/lab')->status);
        $this->newBrowser();
        $this->signIn('tech@vitaliq.test');
        foreach (['/lab', '/lab/samples', '/lab/scan', '/lab/orders', '/lab/orders/new', '/lab/verify', '/lab/inventory', '/lab/equipment', '/lab/catalogue', '/lab/performance'] as $path) {
            $this->assertSame(200, $this->get($path)->status, $path);
        }
        $this->assertSame('camera=(self), microphone=(), geolocation=(), payment=(), usb=()', $this->get('/lab/scan')->header('Permissions-Policy'));
        $this->assertSame(422, $this->post('/lab/scan', ['barcode' => '123456789012'])->status);

        $techId = (int) $this->db->value("SELECT id FROM users WHERE email = 'tech@vitaliq.test'");
        $this->db->execute("INSERT INTO user_permissions (user_id, permission, granted, updated_at) VALUES (:u, 'lab.inventory', 0, NOW())", ['u' => $techId]);
        $this->assertSame(403, $this->get('/lab/inventory')->status, 'a revoked permission closes the page');
        $this->assertStringNotContainsString('href="/lab/inventory"', $this->get('/lab')->body, 'and hides the link');
    }

    public function testOrderCreationThroughTheFormAndAnalytics(): void
    {
        $tech = $this->createUser('lab', 'tech@vitaliq.test');
        $user = $this->createPatient('kavya@example.com');
        $pid = (int) $this->db->value('SELECT id FROM patients WHERE user_id = :u', ['u' => $user]);
        $this->signIn('tech@vitaliq.test');
        $this->assertStringContainsString('Kavya Menon', $this->get('/lab/orders/new', ['q' => 'Kavya'])->body);
        $this->assertSame(422, $this->post('/lab/orders/new', ['patient_id' => (string) $pid])->status);
        $ok = $this->post('/lab/orders/new', ['patient_id' => (string) $pid, 'tests' => [(string) $this->testId('HB')], 'priority' => 'stat']);
        $this->assertSame(303, $ok->status);
        $orderId = (int) $this->db->value('SELECT id FROM lab_orders');
        $page = $this->get('/lab/orders/' . $orderId);
        $this->assertStringContainsString('STAT', $page->body);
        $barcode = (string) $this->db->value('SELECT barcode FROM lab_samples');
        $this->assertRedirectsTo('/lab/samples/' . $this->db->value('SELECT id FROM lab_samples'), $this->post('/lab/scan', ['barcode' => $barcode]));
        $this->assertStringContainsString('<svg', $this->get('/lab/samples/' . $this->db->value('SELECT id FROM lab_samples') . '/label')->body);

        $this->clock->freeze(new DateTimeImmutable('2026-10-11 10:00:00'));
        $w = $this->app->service(LabAnalyticsService::class)->workload();
        $this->assertCount(1, $w['overdue'], 'a 4-hour haemoglobin ordered yesterday is overdue');
        $this->assertSame(1, $w['priority']['stat']);
        $this->assertSame(2.0, LabAnalyticsService::percentile([1, 2, 3], 0.5));
        $this->assertSame(2.8, round(LabAnalyticsService::percentile([1, 2, 3], 0.9), 1));
        $this->newBrowser();
        $this->signIn('tech@vitaliq.test');
        $this->assertSame(200, $this->get('/lab/performance', ['days' => '7'])->status);
        $this->assertSame($tech, (int) $this->db->value('SELECT ordered_by_user_id FROM lab_orders'));
    }
}

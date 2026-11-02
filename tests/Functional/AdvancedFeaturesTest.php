<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use RuntimeException;
use Vitaliq\Services\AlertEngine;
use Vitaliq\Services\ApiKeyService;
use Vitaliq\Services\Assistant\AssistantProvider;
use Vitaliq\Services\AssistantService;
use Vitaliq\Services\ClinicalReferenceService;
use Vitaliq\Services\LaboratoryService;
use Vitaliq\Services\ReportVerificationService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Tests\HttpTestCase;

final class AdvancedFeaturesTest extends HttpTestCase
{
    /** A released lab order with one verified HB result. @return array{order:int,patient:int,user:int,item:int} */
    private function releasedOrder(string $value = '6.5'): array
    {
        $lab = $this->app->service(LaboratoryService::class);
        $lab->syncCatalogue();
        $a = ['id' => $this->createUser('lab', 'a@vitaliq.test'), 'role' => 'lab'];
        $b = ['id' => $this->createUser('lab', 'b@vitaliq.test'), 'role' => 'lab'];
        $user = $this->createPatient('kavya@example.com');
        $pid = (int) $this->db->value('SELECT id FROM patients WHERE user_id = :u', ['u' => $user]);
        $order = $lab->createOrder(['patient_id' => (string) $pid, 'tests' => [(string) $this->db->value("SELECT id FROM lab_tests WHERE code = 'HB'")]], $a);
        $sample = (int) $this->db->value('SELECT id FROM lab_samples WHERE order_id = :o', ['o' => $order]);
        $lab->moveSample($sample, 'collected', $a);
        $lab->moveSample($sample, 'received', $a);
        $item = (int) $this->db->value('SELECT id FROM lab_order_items WHERE order_id = :o', ['o' => $order]);
        $lab->enterResult($item, $value, '', $a);
        $lab->verifyItem($item, $b);
        $lab->releaseOrder($order, $b);
        return ['order' => $order, 'patient' => $pid, 'user' => $user, 'item' => $item];
    }

    public function testAssistantScreensEmergenciesAndUsesTheConfiguredProvider(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $svc = $this->app->service(AssistantService::class);
        $this->assertNull($svc->provider(), 'off unless configured');
        $off = $svc->ask($user, 'What does an HbA1c test measure?');
        $this->assertSame('unavailable', $off['source']);

        $seen = [];
        $svc->useProvider(new class ($seen) implements AssistantProvider {
            /** @param list<mixed> $seen */
            public function __construct(public array &$seen)
            {
            }

            public function name(): string
            {
                return 'Test provider';
            }

            public function reply(string $system, array $messages): array
            {
                $this->seen[] = [$system, $messages];
                return ['text' => 'It reflects average blood sugar over about three months.', 'refused' => false];
            }
        });
        $emergency = $svc->ask($user, 'My father has crushing chest pain and is sweating');
        $this->assertSame('safety', $emergency['source']);
        $this->assertStringContainsString('112', $emergency['text']);
        $this->assertSame([], $seen, 'emergencies never reach the provider');

        $answer = $svc->ask($user, 'What does HbA1c measure?');
        $this->assertSame('provider', $answer['source']);
        $this->assertStringContainsString('do not diagnose', $seen[0][0]);
        $last = end($seen[0][1]);
        $this->assertSame(['role' => 'user', 'content' => 'What does HbA1c measure?'], $last);

        $this->expectException(ValidationFailed::class);
        $svc->ask($user, str_repeat('a', 1001));
    }

    public function testAssistantProviderFailureAndRefusalAreHandled(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $svc = $this->app->service(AssistantService::class);
        $svc->useProvider(new class implements AssistantProvider {
            public int $n = 0;

            public function name(): string
            {
                return 'Flaky';
            }

            public function reply(string $system, array $messages): array
            {
                if (++$this->n === 1) {
                    throw new RuntimeException('The assistant service could not be reached.');
                }
                return ['text' => '', 'refused' => true];
            }
        });
        $this->assertStringContainsString('could not be reached', $svc->ask($user, 'Hello?')['text']);
        $this->assertStringContainsString('cannot help', $svc->ask($user, 'Something else')['text']);
        $this->signIn('kavya@example.com');
        $page = $this->get('/patient/assistant');
        $this->assertStringContainsString('not a diagnosis', $page->body);
        $this->post('/patient/assistant/clear');
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM assistant_messages'));
    }

    public function testMedicineCheckerAndTriageRules(): void
    {
        $ref = $this->app->service(ClinicalReferenceService::class);
        $r = $ref->interactions(['Warfarin 5 mg', 'Brufen', 'Paracetamol']);
        $this->assertSame(['warfarin', 'ibuprofen', 'paracetamol'], $r['medicines']);
        $this->assertCount(1, $r['found']);
        $this->assertSame('major', $r['found'][0]['severity']);
        $this->assertSame(['paracetamol'], $r['unknown']);
        $this->assertSame('contraindicated', $ref->interactions(['sildenafil', 'isosorbide mononitrate'])['found'][0]['severity']);
        $this->assertSame([], $ref->interactions(['metformin', 'levothyroxine'])['found']);

        $this->assertSame('emergency', ClinicalReferenceService::triage(['red_flags' => ['chest'], 'symptoms' => ['cough']])['outcome']);
        $this->assertSame('urgent', ClinicalReferenceService::triage(['symptoms' => ['fever', 'bleeding_gums']])['outcome']);
        $this->assertSame('urgent', ClinicalReferenceService::triage(['symptoms' => ['fever'], 'days' => '3', 'risks' => ['age_60']])['outcome']);
        $this->assertSame('test', ClinicalReferenceService::triage(['symptoms' => ['fever', 'cough'], 'days' => '1'])['outcome']);
        $this->assertSame('self_care', ClinicalReferenceService::triage(['symptoms' => ['sore_throat'], 'days' => '1'])['outcome']);
        $this->assertSame('self_care', ClinicalReferenceService::triage(['red_flags' => ['made-up'], 'symptoms' => ['sore_throat']])['outcome'], 'unknown answers are ignored');

        $this->createPatient('kavya@example.com');
        $this->signIn('kavya@example.com');
        $this->assertStringContainsString('Do not combine', $this->get('/patient/medicines', ['m' => "sildenafil\nGTN"])->body);
        $this->assertSame(422, $this->get('/patient/medicines', ['m' => 'aspirin'])->status);
        $res = $this->post('/patient/triage', ['symptoms' => ['fever'], 'days' => '1']);
        $this->assertStringContainsString('Request a test', $res->body);
        $this->assertSame('test', $this->db->value('SELECT outcome FROM triage_assessments'));
    }

    public function testAlertRulesFireOnMatchingEventsOnly(): void
    {
        $admin = $this->createAdmin();
        $officer = $this->createUser('officer', 'officer@vitaliq.test');
        $engine = $this->app->service(AlertEngine::class);
        try {
            $engine->create(['name' => 'x', 'event' => 'nope'], $admin);
            $this->fail('Bad rule accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('event', $e->errors);
            $this->assertArrayHasKey('recipient_role', $e->errors);
        }
        $engine->create(['name' => 'Critical haemoglobin', 'event' => 'lab.result_flagged', 'match_value' => 'critical_low', 'recipient_user_id' => (string) $officer], $admin);
        $engine->create(['name' => 'Any high value', 'event' => 'lab.result_flagged', 'match_value' => 'high', 'recipient_role' => 'admin'], $admin);
        $this->releasedOrder('6.5');
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'alert_rule' AND user_id = :u", ['u' => $officer]));
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'alert_rule' AND user_id = :u", ['u' => $admin]), 'the high-value rule does not match a critical low');
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM alert_rule_hits'));
    }

    public function testReportVerificationCodes(): void
    {
        $o = $this->releasedOrder('13.2');
        $svc = $this->app->service(ReportVerificationService::class);
        $code = $svc->codeFor('lab_order', $o['order']);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{4}(-[A-Z2-7]{4}){3}$/', $code);
        $this->assertSame($code, $svc->codeFor('lab_order', $o['order']), 'stable per report');
        $this->assertNotSame($code, $svc->codeFor('lab_order', $o['order'] + 1));

        $this->signIn('kavya@example.com');
        $this->assertStringContainsString($code, $this->get('/patient/lab-results/' . $o['order'])->body);
        $this->newBrowser();
        $ok = $this->get('/verify/' . strtolower(str_replace('-', '', $code)));
        $this->assertSame(200, $ok->status);
        $this->assertStringContainsString('Genuine report', $ok->body);
        $this->assertStringContainsString('K.M.', $ok->body);
        $this->assertStringNotContainsString('Kavya', $ok->body, 'only initials are shown');
        $this->assertSame(404, $this->get('/verify/AAAAAAAAAAAAAAAA')->status);
        $this->db->execute("UPDATE lab_orders SET status = 'cancelled'");
        $this->assertStringContainsString('withdrawn', $this->get('/verify/' . str_replace('-', '', $code))->body);
    }

    public function testApiKeysScopesAndFhirResources(): void
    {
        $admin = $this->createAdmin();
        $o = $this->releasedOrder('13.2');
        $keys = $this->app->service(ApiKeyService::class);
        $fhirKey = $keys->issue(['name' => 'Hospital EHR', 'scopes' => ['fhir.read'], 'days' => '90'], $admin)['key'];
        $survKey = $keys->issue(['name' => 'State dashboard', 'scopes' => ['surveillance.read', 'facilities.read'], 'days' => '30'], $admin)['key'];
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM api_keys WHERE key_hash = :k', ['k' => $fhirKey]), 'only the hash is stored');

        $this->newBrowser();
        $this->assertSame(401, $this->get('/api/v1/facilities')->status);
        $this->assertSame(403, $this->get('/api/v1/facilities', [], ['authorization' => 'Bearer ' . $fhirKey])->status);
        $summary = $this->get('/api/v1/surveillance/summary', ['disease' => 'covid19', 'days' => '7'], ['authorization' => 'Bearer ' . $survKey]);
        $this->assertSame(200, $summary->status);
        $this->assertSame(0, json_decode($summary->body, true)['tests']);
        $this->assertSame(400, $this->get('/api/v1/surveillance/summary', ['disease' => 'plague'], ['authorization' => 'Bearer ' . $survKey])->status);

        $meta = $this->get('/fhir/r4/metadata');
        $this->assertSame('application/fhir+json; charset=UTF-8', $meta->header('Content-Type'));
        $auth = ['authorization' => 'Bearer ' . $fhirKey];
        $patient = json_decode($this->get('/fhir/r4/Patient/' . $o['patient'], [], $auth)->body, true);
        $this->assertSame('Patient', $patient['resourceType']);
        $this->assertSame('female', $patient['gender']);
        $this->assertSame('Menon', $patient['name'][0]['family']);
        $bundle = json_decode($this->get('/fhir/r4/Observation', ['patient' => (string) $o['patient']], $auth)->body, true);
        $this->assertSame(1, $bundle['total']);
        $obs = $bundle['entry'][0]['resource'];
        $this->assertSame('718-7', $obs['code']['coding'][0]['code']);
        $this->assertSame(13.2, $obs['valueQuantity']['value']);
        $this->assertSame('N', $obs['interpretation'][0]['coding'][0]['code']);
        $report = json_decode($this->get('/fhir/r4/DiagnosticReport/lab-' . $o['order'], [], $auth)->body, true);
        $this->assertSame('Observation/lab-' . $o['item'], $report['result'][0]['reference']);
        $this->assertSame(404, $this->get('/fhir/r4/Patient/999999', [], $auth)->status);
        $this->assertSame('OperationOutcome', json_decode($this->get('/fhir/r4/Observation', [], $auth)->body, true)['resourceType']);
        $this->assertSame(3, (int) $this->db->value("SELECT COUNT(*) FROM audit_events WHERE action = 'fhir.read'"));

        $keys->revoke((int) $this->db->value("SELECT id FROM api_keys WHERE name = 'Hospital EHR'"), $admin);
        $this->assertSame(401, $this->get('/fhir/r4/Patient/' . $o['patient'], [], $auth)->status);
    }

    public function testOfflinePortalFilesAndAdminScreens(): void
    {
        $sw = $this->get('/sw.js');
        $this->assertSame('text/javascript; charset=UTF-8', $sw->header('Content-Type'));
        $this->assertStringContainsString("'clear-pages'", $sw->body);
        $this->assertStringNotContainsString('__VERSION__', $sw->body);
        $this->assertSame('Vitaliq', json_decode($this->get('/manifest.webmanifest')->body, true)['name']);
        $this->assertSame(200, $this->get('/offline')->status);
        $this->assertSame(200, $this->get('/developers')->status);

        $this->createAdmin();
        $this->signIn('admin@vitaliq.test');
        $this->assertSame(200, $this->get('/admin/alert-rules')->status);
        $page = $this->post('/admin/api-keys', ['name' => 'Test client', 'scopes' => ['facilities.read'], 'days' => '30']);
        $this->assertSame(1, preg_match('/vq_[0-9a-f]{12}_[A-Za-z0-9_-]{30,60}/', $page->body));
    }
}

<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use DateTimeImmutable;
use Vitaliq\Services\OutbreakService;
use Vitaliq\Services\SurveillanceImportService;
use Vitaliq\Services\SurveillanceService;
use Vitaliq\Services\TestingService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Services\WorkflowException;
use Vitaliq\Support\PdfDocument;
use Vitaliq\Support\Stats;
use Vitaliq\Tests\HttpTestCase;

final class SurveillanceTest extends HttpTestCase
{
    private function upload(string $content, string $name = 'returns.csv'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'vqi');
        file_put_contents($tmp, $content);
        return ['name' => $name, 'tmp_name' => $tmp, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK];
    }

    private function import(string $csv, int $actor): int
    {
        $svc = $this->app->service(SurveillanceImportService::class);
        $id = $svc->preview($this->upload($csv), 'District return', $actor);
        $svc->commit($id, $actor);
        return $id;
    }

    public function testStatisticsHelpers(): void
    {
        $this->assertNull(Stats::wilson(0, 0)['p']);
        $w = Stats::wilson(5, 10);
        $this->assertEqualsWithDelta(0.2366, $w['low'], 0.0005);
        $this->assertEqualsWithDelta(0.7634, $w['high'], 0.0005);
        $this->assertSame(0.0, Stats::wilson(0, 50)['low']);

        $growing = array_map(static fn ($t) => (int) round(20 * 1.10 ** $t), range(0, 20));
        $trend = Stats::logLinearTrend($growing);
        $this->assertEqualsWithDelta(0.10, $trend['growth'], 0.01);
        $this->assertLessThan($trend['growth'], $trend['growth_low']);
        $this->assertGreaterThan($trend['growth'], $trend['growth_high']);
        $this->assertEqualsWithDelta(log(2) / log(1.1), $trend['doubling_days'], 0.6);
        $this->assertCount(7, $trend['projection']);
        $p = $trend['projection'][6];
        $this->assertTrue($p['low'] <= $p['value'] && $p['value'] <= $p['high']);
        $this->assertNull(Stats::logLinearTrend([1, 2, 3]));
        $this->assertSame([null, null, 2.0, 3.0], Stats::movingAverage([1, 2, 3, 4], 3));
    }

    public function testImporterValidatesPreviewsCommitsReplacesAndRollsBack(): void
    {
        $officer = $this->createUser('officer', 'officer@vitaliq.test');
        $svc = $this->app->service(SurveillanceImportService::class);
        $csv = "disease,date,state,tests,positives,sex,age_band,facility\n"
            . "dengue,2026-10-05,KA,120,9,,,\n"
            . "dengue,2026-10-05,Kerala,88,14,female,18-44,\n"
            . "dengue,2026-10-05,KA,1,1,,,\n"
            . "measles,2026-10-05,KA,10,1,,,\n"
            . "dengue,05/10/2026,KA,10,1,,,\n"
            . "dengue,2026-10-05,Atlantis,10,1,,,\n"
            . "dengue,2026-10-06,KA,5,9,,,\n"
            . "dengue,2026-10-11,KA,5,1,,,\n"
            . "dengue,2026-10-06,KA,10,1,unknown,,\n";
        $id = $svc->preview($this->upload($csv), 'District return', $officer);
        $imp = $svc->find($id);
        $this->assertSame(9, (int) $imp['rows_total']);
        $this->assertSame(2, (int) $imp['rows_ok']);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM surveillance_records'), 'nothing is written on preview');
        $messages = implode(' | ', array_column($imp['errors'], 'error'));
        foreach (['Repeats row 2', 'Unknown disease', 'YYYY-MM-DD', 'Unknown state', 'cannot exceed tests', 'between 2000-01-01 and today', 'Sex must be'] as $needle) {
            $this->assertStringContainsString($needle, $messages);
        }
        $this->assertEquals(['added' => 2, 'replaced' => 0], $svc->commit($id, $officer));
        $this->expectExceptionObject(new WorkflowException('Only a previewed import can be committed.'));
        try {
            $svc->commit($id, $officer);
        } finally {
            // A second file correcting Karnataka replaces that cell and leaves Kerala alone.
            $second = $svc->preview($this->upload('[{"disease":"dengue","date":"2026-10-05","state":"KA","tests":130,"positives":11}]', 'fix.json'), 'Correction', $officer);
            $this->assertSame('json', $svc->find($second)['format']);
            $this->assertEquals(['added' => 0, 'replaced' => 1], $svc->commit($second, $officer));
            $this->assertSame(11, (int) $this->db->value("SELECT positives FROM surveillance_records WHERE state_code = 'KA'"));
            $this->assertSame(1, $svc->rollback($second, $officer));
            $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM surveillance_records'), 'rollback removes only rows the import still owns');
        }
    }

    public function testFilesThatAreNotTabularAreRefused(): void
    {
        $officer = $this->createUser('officer', 'officer@vitaliq.test');
        $svc = $this->app->service(SurveillanceImportService::class);
        foreach (["date,state\n2026-10-05,KA\n" => 'missing: disease', '{"records": 5}' => 'list of records', "\x89PNG\r\n\x1a\n" . str_repeat("\0", 20) => 'CSV or JSON'] as $body => $needle) {
            try {
                $svc->preview($this->upload($body), 'Source', $officer);
                $this->fail('Accepted: ' . $needle);
            } catch (ValidationFailed $e) {
                $this->assertStringContainsString($needle, implode(' ', $e->errors));
            }
        }
    }

    public function testCountsCombineWorkflowLaboratoryAndImports(): void
    {
        $admin = $this->createAdmin();
        $actor = ['id' => $admin, 'role' => 'admin'];
        [$phleb] = $this->createPhlebotomist();
        $testing = $this->app->service(TestingService::class);
        $req = $testing->submitGuest($this->guestInput(), 'tok-1');
        $testing->assign($req['id'], $phleb, $actor);
        $testing->recordCollection($req['id'], ['specimen' => 'nasal', 'barcode' => 'VQS20261010A'], $actor);
        $testing->startProcessing($req['id'], $actor);
        $testing->recordResult($req['id'], ['outcome' => 'positive'], $actor);
        $testing->releaseResult($req['id'], $actor);
        $this->import("disease,date,state,tests,positives\ncovid19,2026-10-09,KA,40,2\ncovid19,2026-10-09,MH,10,0\n", $admin);

        $svc = $this->app->service(SurveillanceService::class);
        $o = $svc->overview('covid19', 7);
        $this->assertSame(51, $o['current']['tests']);
        $this->assertSame(3, $o['current']['positives']);
        $this->assertSame(1, $o['sources']['workflow']['tests']);
        $this->assertSame(50, $o['sources']['imported']['tests']);
        $this->assertSame('KA', $o['byState'][0]['state_code']);
        $this->assertCount(7, $o['daily']);
        $this->assertSame(1, $svc->demographics('covid19', 7)['sex'][0]['tests'], 'only the workflow result has a sex');
        $map = array_column($svc->map('covid19', 7), null, 'code');
        $this->assertSame(3, $map['KA']['level'], '3 of 41 is 7.3%, in the 5-10% band');
        $this->assertSame(1, $map['MH']['level'], 'Maharashtra reaches the 10-test minimum: 0% is shown, in the lowest band');
    }

    public function testOutbreakRulesRaiseOnceAndNeedANoteToClose(): void
    {
        $officer = $this->createUser('officer', 'officer@vitaliq.test');
        $admin = $this->createAdmin();
        $rows = "disease,date,state,tests,positives\n";
        // Four quiet weeks (1 a week) then a busy one, in Kerala.
        foreach ([1, 1, 2, 1] as $w => $n) {
            $rows .= 'dengue,' . (new DateTimeImmutable('2026-10-09'))->modify('-' . (7 * ($w + 1)) . ' days')->format('Y-m-d') . ",KL,30,{$n}\n";
        }
        $rows .= "dengue,2026-10-08,KL,60,9\ndengue,2026-10-09,KL,40,6\n";
        $this->import($rows, $officer);
        $out = $this->app->service(OutbreakService::class);
        try {
            $out->createThreshold(['disease' => 'dengue', 'method' => 'positivity', 'threshold' => '250', 'window_days' => '7'], $officer);
            $this->fail('A 250% threshold was accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('threshold', $e->errors);
        }
        $out->createThreshold(['disease' => 'dengue', 'method' => 'count', 'threshold' => '10', 'window_days' => '7'], $officer);
        $out->createThreshold(['disease' => 'dengue', 'method' => 'baseline', 'threshold' => '2', 'window_days' => '7'], $officer);
        $out->createThreshold(['disease' => 'dengue', 'method' => 'positivity', 'threshold' => '20', 'window_days' => '7', 'min_tests' => '20'], $officer);

        $raised = $out->evaluate();
        $this->assertCount(2, $raised, 'count (15 >= 10) and baseline fire; positivity 15% stays under 20%');
        $this->assertSame([], $out->evaluate(), 'the same window does not alert twice');
        $this->assertSame(2, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'outbreak_alert' AND user_id = :u", ['u' => $officer]));
        $this->assertSame(2, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'outbreak_alert' AND user_id = :u", ['u' => $admin]));

        $id = (int) $raised[0]['id'];
        $out->setAlertStatus($id, 'acknowledged', '', $officer);
        try {
            $out->setAlertStatus($id, 'closed', '', $officer);
            $this->fail('Closed without a note');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('note', $e->errors);
        }
        $out->setAlertStatus($id, 'closed', 'Vector control team sent to Ernakulam', $officer);
        $this->assertSame('closed', $out->alert($id)['status']);
    }

    public function testScreensExportsAndAccess(): void
    {
        $officer = $this->createUser('officer', 'officer@vitaliq.test');
        $this->import("disease,date,state,tests,positives\ncovid19,2026-10-09,KA,40,2\ncovid19,2026-10-08,=HYPERLINK(1),10,1\n", $officer);
        $this->createUser('lab', 'lab@vitaliq.test');
        $this->signIn('lab@vitaliq.test');
        $this->assertSame(403, $this->get('/surveillance')->status);
        $this->newBrowser();
        $this->signIn('officer@vitaliq.test');
        foreach (['/surveillance', '/surveillance/map', '/surveillance/trends', '/surveillance/demographics', '/surveillance/facilities', '/surveillance/quality', '/surveillance/alerts', '/surveillance/imports', '/surveillance/reports'] as $path) {
            $this->assertSame(200, $this->get($path, ['disease' => 'covid19', 'days' => '28'])->status, $path);
        }
        $csv = $this->get('/surveillance/reports/export', ['format' => 'csv', 'by' => 'state', 'disease' => 'covid19', 'days' => '28']);
        $this->assertStringStartsWith("\xEF\xBB\xBFstate,tests,positives,positivity_pct", $csv->body);
        $this->assertStringContainsString('Karnataka,40,2,5.00,', $csv->body);
        $pdf = $this->get('/surveillance/reports/export', ['format' => 'pdf', 'by' => 'day', 'disease' => 'covid19', 'days' => '7']);
        $this->assertSame('application/pdf', $pdf->header('Content-Type'));
        $this->assertStringStartsWith('%PDF-1.4', $pdf->body);
        $this->assertStringEndsWith("%%EOF\n", $pdf->body);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM audit_events WHERE action = 'surveillance.report_exported' AND details LIKE '%pdf%'"));

        // Upload through the form, then commit.
        $this->get('/surveillance/imports');
        $this->assertSame(422, $this->post('/surveillance/imports', ['source_name' => 'x'])->status);
    }

    public function testPdfEscapesTextAndPaginates(): void
    {
        $pdf = new PdfDocument('Test (one)', 'footer');
        $pdf->heading('Heading with (brackets) and \\ slash');
        $rows = [];
        for ($i = 0; $i < 90; $i++) {
            $rows[] = ['Row ' . $i, (string) $i];
        }
        $pdf->table(['Name', 'N'], $rows, [0.7, 0.3], [1]);
        $out = $pdf->output();
        $this->assertStringContainsString('(Heading with \\(brackets\\) and \\\\ slash)', $out);
        $this->assertMatchesRegularExpression('#/Count [2-9]#', $out, 'long tables continue on new pages');
        $this->assertStringContainsString('Page 2', $out);
        $this->assertSame(1, preg_match('/startxref\n(\d+)\n/', $out, $m));
        $this->assertSame('xref', substr($out, (int) $m[1], 4), 'startxref points at the cross-reference table');
    }
}

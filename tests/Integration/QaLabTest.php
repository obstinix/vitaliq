<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use RuntimeException;
use Vitaliq\Services\DefectService;
use Vitaliq\Services\QaService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Tests\DatabaseTestCase;

final class QaLabTest extends DatabaseTestCase
{
    private function qa(): QaService
    {
        return $this->app->service(QaService::class);
    }

    public function testCatalogueSyncLoadsEveryCaseOnceAndHasNoResults(): void
    {
        $regex = count(json_decode((string) file_get_contents(VQ_ROOT . '/database/qa/regex-cases.json'), true));
        $manual = count(json_decode((string) file_get_contents(VQ_ROOT . '/database/qa/manual-cases.json'), true));
        $this->assertSame($regex + $manual, $this->qa()->syncCatalogue());
        $this->assertSame(0, $this->qa()->syncCatalogue(), 'Second sync adds nothing');
        $summary = $this->qa()->summary();
        $this->assertSame($regex + $manual, $summary['totals']['cases']);
        $this->assertSame(0, $summary['totals']['executed'], 'Catalogue cases start as not executed');
        $this->assertSame(0, $summary['totals']['pass']);
    }

    public function testDuplicateCaseCodeInCatalogueIsAnError(): void
    {
        $dir = sys_get_temp_dir() . '/vq-qa-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $case = ['code' => 'TC-DUP-001', 'rule' => 'pincode', 'module' => 'X', 'title' => 'Dup', 'type' => 'positive', 'input' => '560001', 'expected' => true];
        file_put_contents($dir . '/regex-cases.json', json_encode([$case]));
        file_put_contents($dir . '/manual-cases.json', json_encode([['code' => 'TC-DUP-001', 'module' => 'X', 'title' => 'Dup', 'type' => 'functional', 'input' => 'x', 'expected' => 'y']]));
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('TC-DUP-001');
            $this->qa()->syncCatalogue($dir);
        } finally {
            array_map('unlink', glob($dir . '/*'));
            rmdir($dir);
        }
    }

    public function testRegexSuiteRecordsActualOutcomesForEveryCase(): void
    {
        $this->qa()->syncCatalogue();
        $runId = $this->qa()->runRegexSuite(null, 'cli');
        $run = $this->qa()->run($runId);
        $regexCount = (int) $this->db->value("SELECT COUNT(*) FROM qa_test_cases WHERE kind = 'regex'");
        $this->assertSame($regexCount, (int) $run['total']);
        $this->assertSame($regexCount, (int) $this->db->value('SELECT COUNT(*) FROM qa_run_results WHERE run_id = :r', ['r' => $runId]));
        $this->assertSame(0, (int) $run['failed'], 'All catalogue expectations hold');
        $this->assertSame(self::NOW, $run['started_at']);
        $this->assertStringContainsString('PHP ' . PHP_VERSION, (string) $run['environment']);
    }

    public function testSuiteReportsFailureWhenActualDiffersFromExpected(): void
    {
        $this->db->insert(
            "INSERT INTO qa_test_cases (case_code, module, title, kind, case_type, rule_key, input_value, expected_match, expected_result, origin, created_at)
             VALUES ('TC-WRONG-001', 'X', 'Deliberately wrong expectation', 'regex', 'negative', 'pincode', '560001', 0, 'Rejected', 'user', :t)",
            ['t' => self::NOW]
        );
        $run = $this->qa()->run($this->qa()->runRegexSuite(null, 'cli'));
        $this->assertSame(1, (int) $run['failed']);
        $this->assertSame('fail', $run['results'][0]['status']);
        $this->assertSame('Accepted by the validator', $run['results'][0]['actual_result']);
    }

    public function testManualExecutionIsRecordedWithItsTimestamp(): void
    {
        $this->qa()->syncCatalogue();
        $userId = $this->createUser('qa', 'qa@vitaliq.test');
        $caseId = (int) $this->db->value("SELECT id FROM qa_test_cases WHERE case_code = 'TC-SEC-002'");
        try {
            $this->qa()->recordManual($caseId, ['status' => 'maybe', 'actual_result' => ''], $userId);
            $this->fail('Invalid execution accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('status', $e->errors);
        }
        $this->qa()->recordManual($caseId, ['status' => 'pass', 'actual_result' => 'HTTP 419 returned'], $userId);
        $case = $this->qa()->findCase($caseId);
        $this->assertSame('pass', $case['executions'][0]['status']);
        $this->assertSame(self::NOW, $case['executions'][0]['executed_at']);
    }

    public function testJunitImportRecordsEachTestcase(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'junit');
        file_put_contents($file, '<?xml version="1.0"?><testsuites><testsuite name="all">'
            . '<testcase name="testA" class="Vitaliq\Tests\Unit\FooTest" assertions="3" time="0.010"/>'
            . '<testcase name="testB" class="Vitaliq\Tests\Integration\BarTest" assertions="1" time="0.200"><failure>Expected 1, got 2' . "\n" . 'trace</failure></testcase>'
            . '<testcase name="testC" class="Vitaliq\Tests\Functional\BazTest" assertions="0" time="0"><skipped/></testcase>'
            . '</testsuite></testsuites>');
        $run = $this->qa()->run($this->qa()->importJunit($file));
        unlink($file);
        $this->assertSame([3, 1, 1, 1], [(int) $run['total'], (int) $run['passed'], (int) $run['failed'], (int) $run['blocked']]);
        $byName = array_column($run['results'], null, 'test_name');
        $this->assertSame('Expected 1, got 2', $byName['BarTest::testB']['actual_result']);
        $this->assertSame('Integration', $byName['BarTest::testB']['module']);
    }

    public function testCoverageRunDerivesCaseStatusFromTheJunitReport(): void
    {
        $this->qa()->syncCatalogue();
        $map = tempnam(sys_get_temp_dir(), 'map');
        file_put_contents($map, json_encode([
            'TC-SEC-002' => ['SecurityTest::testA'],
            'TC-SEC-003' => ['SecurityTest::testA', 'SecurityTest::testB'],
            'TC-DB-001' => ['SecurityTest::testMissing'],
        ]));
        $junit = tempnam(sys_get_temp_dir(), 'junit');
        file_put_contents($junit, '<?xml version="1.0"?><testsuites><testsuite name="s">'
            . '<testcase name="testA with data set &quot;x&quot;" class="Vitaliq\Tests\Functional\SecurityTest" assertions="1" time="0"/>'
            . '<testcase name="testA with data set &quot;y&quot;" class="Vitaliq\Tests\Functional\SecurityTest" assertions="1" time="0"/>'
            . '<testcase name="testB" class="Vitaliq\Tests\Functional\SecurityTest" assertions="1" time="0"><failure>boom</failure></testcase>'
            . '</testsuite></testsuites>');
        $run = $this->qa()->run($this->qa()->recordCoverage($junit, $map));
        unlink($map);
        unlink($junit);
        $byCase = array_column($run['results'], 'status', 'case_code');
        $this->assertCount(3, $byCase);
        $this->assertSame('pass', $byCase['TC-SEC-002'], 'All data sets of the mapped test passed');
        $this->assertSame('fail', $byCase['TC-SEC-003'], 'One failing mapped test fails the case');
        $this->assertSame('blocked', $byCase['TC-DB-001'], 'A missing test blocks rather than passes');
    }

    public function testEveryTestNamedInTheAutomationMapExists(): void
    {
        $map = json_decode((string) file_get_contents(VQ_ROOT . '/database/qa/automation-map.json'), true, 512, JSON_THROW_ON_ERROR);
        $catalogue = array_column(json_decode((string) file_get_contents(VQ_ROOT . '/database/qa/manual-cases.json'), true), 'code');
        foreach ($map as $code => $tests) {
            $this->assertContains($code, $catalogue, "{$code} is not a catalogue case");
            foreach ($tests as $test) {
                [$class, $method] = explode('::', $test);
                $found = false;
                foreach (['Unit', 'Integration', 'Functional'] as $suite) {
                    $fqcn = "Vitaliq\\Tests\\{$suite}\\{$class}";
                    if (class_exists($fqcn) && method_exists($fqcn, $method)) {
                        $found = true;
                    }
                }
                $this->assertTrue($found, "{$code}: {$test} does not exist");
            }
        }
    }

    public function testDefectLifecycleRequiresResolutionNotes(): void
    {
        $userId = $this->createUser('qa', 'qa@vitaliq.test');
        $this->actingAs($userId);
        $defects = $this->app->service(DefectService::class);
        $id = $defects->create([
            'title' => 'Bell count wrong', 'description' => 'Count stays at zero after a new request.',
            'severity' => 'minor', 'priority' => 'medium', 'module' => 'Notifications',
            'steps_to_reproduce' => '1. Submit a request. 2. Look at the bell.', 'expected_behaviour' => 'Count rises',
            'actual_behaviour' => 'Count stays at zero',
        ], $userId);
        $this->assertSame('DEF-001', $this->db->value('SELECT code FROM defects WHERE id = :id', ['id' => $id]));
        $this->assertSame('user_report', $this->db->value('SELECT origin FROM defects WHERE id = :id', ['id' => $id]));
        try {
            $defects->updateStatus($id, ['status' => 'resolved', 'resolution_notes' => '']);
            $this->fail('Resolved without notes');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('resolution_notes', $e->errors);
        }
        $defects->updateStatus($id, ['status' => 'resolved', 'resolution_notes' => 'Fixed polling interval.']);
        $this->assertNotNull($this->db->value('SELECT resolved_at FROM defects WHERE id = :id', ['id' => $id]));
        $defects->updateStatus($id, ['status' => 'open', 'resolution_notes' => '']);
        $this->assertNull($this->db->value('SELECT resolved_at FROM defects WHERE id = :id', ['id' => $id]), 'Reopening clears the resolved time');
    }
}

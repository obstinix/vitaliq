<?php

declare(strict_types=1);

/*
 * STQA laboratory command line.
 *
 *   php bin/qa.php sync                   add new catalogue cases from database/qa/*.json
 *   php bin/qa.php run                    execute the regex suite and record a run
 *   php bin/qa.php import-junit <file>    record a PHPUnit JUnit XML report as a run
 *   php bin/qa.php cover <file>           mark catalogue cases automated by PHPUnit
 *                                         (database/qa/automation-map.json) from the report
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\QaService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$config = Config::fromEnv();
$app = new App($config, new Database($config['db']));
$qa = $app->service(QaService::class);
$command = $argv[1] ?? '';

switch ($command) {
    case 'sync':
        fwrite(STDOUT, 'Added ' . $qa->syncCatalogue() . " new case(s).\n");
        break;
    case 'run':
        $id = $qa->runRegexSuite(null, 'cli');
        $run = $qa->run($id);
        fwrite(STDOUT, sprintf("Run #%d: %d executed, %d passed, %d failed, %d blocked.\n", $id, $run['total'], $run['passed'], $run['failed'], $run['blocked']));
        foreach ($run['results'] as $r) {
            if ($r['status'] !== 'pass') {
                fwrite(STDOUT, sprintf("  %s %s\n    input: %s\n    expected: %s\n    actual:   %s\n",
                    strtoupper($r['status']), $r['test_name'], show_input($r['input_value']), $r['expected_result'], $r['actual_result']));
            }
        }
        exit($run['failed'] > 0 ? 2 : 0);
    case 'import-junit':
        $path = $argv[2] ?? '';
        $id = $qa->importJunit($path);
        $run = $qa->run($id);
        fwrite(STDOUT, sprintf("Imported run #%d: %d tests, %d passed, %d failed, %d skipped.\n", $id, $run['total'], $run['passed'], $run['failed'], $run['blocked']));
        break;
    case 'cover':
        $path = $argv[2] ?? '';
        $id = $qa->recordCoverage($path);
        $run = $qa->run($id);
        fwrite(STDOUT, sprintf("Run #%d: %d catalogue cases evaluated from PHPUnit, %d passed, %d failed, %d blocked.\n", $id, $run['total'], $run['passed'], $run['failed'], $run['blocked']));
        foreach ($run['results'] as $r) {
            if ($r['status'] !== 'pass') {
                fwrite(STDOUT, '  ' . strtoupper($r['status']) . ' ' . $r['test_name'] . ': ' . $r['actual_result'] . "\n");
            }
        }
        break;
    default:
        fwrite(STDERR, "Usage: php bin/qa.php sync | run | import-junit <file> | cover <file>\n");
        exit(1);
}

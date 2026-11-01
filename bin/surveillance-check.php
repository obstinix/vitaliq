<?php

declare(strict_types=1);

/*
 * Evaluate every active outbreak rule for the window ending yesterday and
 * notify officers of new alerts. Safe to run repeatedly: an alert is raised
 * once per rule, state and window. Schedule daily, for example:
 *
 *   15 6 * * *  php /path/to/vitaliq/bin/surveillance-check.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\OutbreakService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = Config::fromEnv();
$app = new App($config, new Database($config['db']));
$raised = $app->service(OutbreakService::class)->evaluate();
fwrite(STDOUT, count($raised) . " new alert(s).\n");
foreach ($raised as $a) {
    fwrite(STDOUT, sprintf("  %s %s %s to %s: %d of %d\n", $a['disease'], $a['state_code'], $a['window_start'], $a['window_end'], $a['positives'], $a['tests']));
}

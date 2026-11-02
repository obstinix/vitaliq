<?php

declare(strict_types=1);

/*
 * Remove records past their retention period (feature 49). Clinical
 * records are never touched. Schedule daily, for example:
 *
 *   45 2 * * *  php /path/to/vitaliq/bin/retention.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\RetentionService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = Config::fromEnv();
$app = new App($config, new Database($config['db']));
foreach ($app->service(RetentionService::class)->purge(null) as $what => $n) {
    fwrite(STDOUT, sprintf("%-14s %d removed\n", $what, $n));
}

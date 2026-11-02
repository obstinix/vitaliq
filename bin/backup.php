<?php

declare(strict_types=1);

/*
 * Write an encrypted backup of the configured database to storage/backups
 * and keep the newest 14. Schedule daily, for example:
 *
 *   30 2 * * *  php /path/to/vitaliq/bin/backup.php
 *
 * Copy the files off the server as well: a backup on the same disk does
 * not survive the loss of that disk.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\BackupService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = Config::fromEnv();
$app = new App($config, new Database($config['db']));
$b = $app->service(BackupService::class)->create(null);
fwrite(STDOUT, sprintf("Wrote storage/backups/%s: %d tables, %d rows, %d bytes (encrypted).\n", $b['filename'], $b['tables'], $b['rows'], $b['bytes']));

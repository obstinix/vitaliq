<?php

declare(strict_types=1);

/*
 * Restore an encrypted backup into an EMPTY database.
 *
 *   php bin/backup-restore.php storage/backups/<file>.sql.gz.enc --into=<database>
 *   php bin/backup-restore.php <file> --sql > restored.sql      (decrypt only)
 *
 * Uses APP_KEY and the DB_* connection details from .env; only the database
 * name is replaced. Refuses a database that already has any table, so a
 * restore can never overwrite live data.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Services\BackupService;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$file = $argv[1] ?? '';
$into = null;
$sqlOnly = false;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--into=')) {
        $into = substr($arg, 7);
    } elseif ($arg === '--sql') {
        $sqlOnly = true;
    }
}
if ($file === '' || !is_file($file) || ($into === null && !$sqlOnly)) {
    fwrite(STDERR, "Usage: php bin/backup-restore.php <file> --into=<empty database> | --sql\n");
    exit(2);
}
$config = Config::fromEnv();
try {
    $sql = BackupService::decryptFile($file, (string) $config['key']);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Cannot decrypt: ' . $e->getMessage() . " (is APP_KEY the one that wrote this backup?)\n");
    exit(1);
}
if ($sqlOnly) {
    fwrite(STDOUT, implode(";\n", BackupService::statements($sql)) . ";\n");
    exit(0);
}
if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', (string) $into)) {
    fwrite(STDERR, "Invalid database name.\n");
    exit(2);
}
$db = new Database(['name' => $into] + $config['db']);
$count = (int) $db->value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :s', ['s' => $into]);
if ($count > 0) {
    fwrite(STDERR, "Database '{$into}' already has {$count} tables. Restore only into an empty database.\n");
    exit(1);
}
$n = 0;
foreach (BackupService::statements($sql) as $statement) {
    $db->pdo()->exec($statement);
    $n++;
}
fwrite(STDOUT, "Restored {$n} statements into '{$into}'.\n");

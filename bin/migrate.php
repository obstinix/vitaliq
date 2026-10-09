<?php

declare(strict_types=1);

/*
 * Apply pending migrations to the database configured in .env.
 *
 *   php bin/migrate.php            apply pending migrations
 *   php bin/migrate.php --fresh    drop all tables first (local/testing only;
 *                                  asks for confirmation)
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Core\Migrator;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$config = Config::fromEnv();
$db = new Database($config['db']);
$migrator = new Migrator($db, VQ_ROOT . '/database/migrations');

if (in_array('--fresh', $argv, true)) {
    if ($config['env'] === 'production') {
        fwrite(STDERR, "--fresh is disabled when APP_ENV=production.\n");
        exit(1);
    }
    $name = $config['db']['name'];
    fwrite(STDOUT, "This drops EVERY table in database '{$name}'. Type the database name to continue: ");
    $answer = trim((string) fgets(STDIN));
    if ($answer !== $name) {
        fwrite(STDERR, "Aborted.\n");
        exit(1);
    }
    $migrator->dropAll(true);
    fwrite(STDOUT, "Dropped all tables in {$name}.\n");
}

$applied = $migrator->migrate();
fwrite(STDOUT, $applied === [] ? "Nothing to migrate.\n" : 'Applied: ' . implode(', ', $applied) . "\n");

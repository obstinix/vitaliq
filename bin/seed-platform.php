<?php

declare(strict_types=1);

/*
 * Add synthetic demonstration data for the platform features (facilities,
 * laboratory, appointments, surveillance) on top of the base demo data.
 *
 *   php bin/seed-platform.php
 *
 * Refuses to run when APP_ENV=production or when it has already run.
 * New demo passwords are generated and printed once.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Support\PlatformSeeder;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$config = Config::fromEnv();
if ($config['env'] === 'production') {
    fwrite(STDERR, "Refusing to seed demo data when APP_ENV=production.\n");
    exit(1);
}
$app = new App($config, new Database($config['db']));
$seeder = new PlatformSeeder($app);
try {
    $seeder->run();
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
fwrite(STDOUT, "Platform demo data added.\n\nNew demo sign-ins for THIS install (shown once, keep them private):\n");
foreach ($seeder->credentials as $c) {
    fwrite(STDOUT, sprintf("  %-13s %-34s %s\n", $c['role'], $c['email'], $c['password']));
}
fwrite(STDOUT, "\nAll facilities, people and results are synthetic.\n");

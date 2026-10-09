<?php

declare(strict_types=1);

/*
 * Load synthetic demonstration data into the database configured in .env.
 *
 *   php bin/seed.php
 *
 * Refuses to run when APP_ENV=production or when demo data already exists.
 * Demo account passwords are generated randomly for this install and printed
 * once; they are not stored anywhere except as Argon2id hashes.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Support\DemoSeeder;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$config = Config::fromEnv();
if ($config['env'] === 'production') {
    fwrite(STDERR, "Refusing to seed demo data when APP_ENV=production.\n");
    exit(1);
}

$app = new App($config, new Database($config['db']));
$seeder = new DemoSeeder($app);
$started = microtime(true);
try {
    $seeder->run();
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$db = $app->db;
fwrite(STDOUT, sprintf(
    "Seeded in %.1fs: %d users, %d patients, %d phlebotomists, %d requests, %d QA cases.\n\n",
    microtime(true) - $started,
    (int) $db->value('SELECT COUNT(*) FROM users'),
    (int) $db->value('SELECT COUNT(*) FROM patients'),
    (int) $db->value('SELECT COUNT(*) FROM phlebotomists'),
    (int) $db->value('SELECT COUNT(*) FROM test_requests'),
    (int) $db->value('SELECT COUNT(*) FROM qa_test_cases')
));
fwrite(STDOUT, "Demo sign-ins for THIS install (shown once, keep them private):\n");
foreach ($seeder->credentials as $c) {
    fwrite(STDOUT, sprintf("  %-13s %-34s %s\n", $c['role'], $c['email'], $c['password']));
}
fwrite(STDOUT, "\nAll people, phone numbers and requests are synthetic.\n");

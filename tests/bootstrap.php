<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Core\DatabaseUnavailable;
use Vitaliq\Core\Env;
use Vitaliq\Core\Migrator;

/*
 * Point every test at the isolated test database. DB host, user and password
 * come from .env (or real environment variables); only the name changes.
 */
$testDb = (string) Env::get('TEST_DB_NAME', 'vitaliq_test');
if (!str_ends_with($testDb, '_test')) {
    fwrite(STDERR, "TEST_DB_NAME must end in _test (got '{$testDb}'). Refusing to run.\n");
    exit(1);
}
Env::set('DB_NAME', $testDb);
Env::set('APP_ENV', 'testing');
Env::set('MAIL_DRIVER', 'none');
Env::set('APP_URL', 'http://vitaliq.test');
if (strlen((string) Env::get('APP_KEY', '')) < 32) {
    Env::set('APP_KEY', str_repeat('0123456789abcdef', 4));
}

define('VQ_TEST_DB_AVAILABLE', (static function (): bool {
    $db = new Database(Config::fromEnv()['db']);
    try {
        $migrator = new Migrator($db, VQ_ROOT . '/database/migrations');
        $migrator->dropAll();
        $migrator->migrate();
        return true;
    } catch (DatabaseUnavailable $e) {
        fwrite(STDERR, "Test database unavailable: integration and functional tests will be skipped.\n");
        return false;
    }
})());

<?php

declare(strict_types=1);

/*
 * Shared bootstrap for the web front controller, CLI scripts and tests.
 * Vitaliq has no runtime Composer dependencies, so classes are loaded by
 * this small PSR-4 style autoloader.
 */

if (!defined('VQ_ROOT')) {
    define('VQ_ROOT', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Vitaliq\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = VQ_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once VQ_ROOT . '/src/Support/helpers.php';

Vitaliq\Core\Env::load(VQ_ROOT . '/.env');

date_default_timezone_set((string) Vitaliq\Core\Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
mb_internal_encoding('UTF-8');

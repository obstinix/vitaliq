<?php

declare(strict_types=1);

/*
 * Front controller. The web server's document root must be this public/
 * directory so that .env, src/, database/ and storage/ are never served.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Core\Database;
use Vitaliq\Core\NativeSession;
use Vitaliq\Core\Request;

$config = Config::fromEnv();

if ($config['key'] === '' || strlen($config['key']) < 32) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Vitaliq is not configured: set APP_KEY in .env (php bin/generate-key.php).\n";
    exit;
}

ini_set('display_errors', $config['debug'] && $config['env'] !== 'production' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('expose_php', '0');
header_remove('X-Powered-By');

$app = new App($config, new Database($config['db']));
$request = Request::fromGlobals($config['base_path']);
$session = NativeSession::start($config['session'], $request, $config['base_path']);

$app->handle($request, $session)->send();

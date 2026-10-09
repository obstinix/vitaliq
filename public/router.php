<?php

declare(strict_types=1);

/*
 * Router script for PHP's built-in development server:
 *   php -S 127.0.0.1:8000 -t public public/router.php
 * Serves existing files under public/assets directly; everything else goes
 * to the front controller. Not used by Apache/Nginx.
 */

$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$file = realpath(__DIR__ . $path);

if ($file !== false && is_file($file) && str_starts_with($file, realpath(__DIR__ . '/assets') ?: "\0")) {
    return false;
}

require __DIR__ . '/index.php';

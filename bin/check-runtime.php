<?php

declare(strict_types=1);

/*
 * Exit with status 1 when the PHP running this script is a release affected
 * by CVE-2026-109618 / CVE-2026-109619. Run it with the same PHP binary the
 * web server uses (php-fpm, mod_php or the CLI server), e.g. in deployment:
 *
 *   php bin/check-runtime.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Vitaliq\Support\RuntimeCheck;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$result = RuntimeCheck::php();
fwrite($result['ok'] ? STDOUT : STDERR, ($result['ok'] ? 'OK: ' : 'VULNERABLE: ') . $result['message'] . PHP_EOL);
exit($result['ok'] ? 0 : 1);

<?php

declare(strict_types=1);

/*
 * Print a new random APP_KEY (64 hex characters) for .env.
 * The key signs form timestamps and keys IP hashes; it is never logged.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

fwrite(STDOUT, bin2hex(random_bytes(32)) . PHP_EOL);

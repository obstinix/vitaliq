<?php

declare(strict_types=1);

/*
 * Syntax-check every PHP file in the project with the running PHP binary.
 *
 *   php bin/lint.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$failures = 0;
$count = 0;
foreach (['src', 'bin', 'public', 'views', 'tests'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $count++;
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            $failures++;
            fwrite(STDERR, implode("\n", $output) . "\n");
        }
    }
}
fwrite(STDOUT, "Linted {$count} files: " . ($failures === 0 ? 'no syntax errors' : "{$failures} with errors") . ".\n");
exit($failures === 0 ? 0 : 1);

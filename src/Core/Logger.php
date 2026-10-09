<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use Throwable;

/**
 * Append-only file logger. Callers must never pass passwords, tokens or
 * other secrets in the message or context.
 */
final class Logger
{
    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string,scalar|null> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('c'),
            strtoupper($level),
            str_replace(["\r", "\n"], ' ', $message),
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents($this->directory . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public function exception(Throwable $e, string $requestId): void
    {
        $this->log('error', get_class($e) . ': ' . $e->getMessage(), [
            'request_id' => $requestId,
            'file' => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    }
}

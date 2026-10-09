<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Outgoing mail. No SMTP transport is configured in this project:
 *  - "outbox" writes RFC 822 files to storage/mail for local development;
 *  - "none" drops messages.
 * Neither driver delivers email to a real inbox, and the UI never claims it did.
 */
final class Mailer
{
    public function __construct(
        private readonly string $driver,
        private readonly string $from,
        private readonly string $outboxDirectory,
    ) {
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /** @return bool true when the message was written to the local outbox */
    public function send(string $to, string $subject, string $text): bool
    {
        if ($this->driver !== 'outbox') {
            return false;
        }
        if (!is_dir($this->outboxDirectory)) {
            @mkdir($this->outboxDirectory, 0775, true);
        }
        $safe = static fn (string $v): string => str_replace(["\r", "\n"], '', $v);
        $message = 'From: ' . $safe($this->from) . "\r\n"
            . 'To: ' . $safe($to) . "\r\n"
            . 'Subject: ' . $safe($subject) . "\r\n"
            . 'Date: ' . date(DATE_RFC2822) . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $text . "\r\n";
        $file = $this->outboxDirectory . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        return file_put_contents($file, $message, LOCK_EX) !== false;
    }
}

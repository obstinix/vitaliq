<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Outgoing mail for account emails (password reset, invitations):
 *  - "outbox" writes RFC 822 files to storage/mail for local development;
 *  - "smtp" delivers through the configured SMTP server;
 *  - "none" drops messages.
 * The UI never claims a message reached an inbox.
 */
final class Mailer
{
    /** @param array<string,mixed> $smtp host, port, encryption, username, password */
    public function __construct(
        private readonly string $driver,
        private readonly string $from,
        private readonly string $outboxDirectory,
        private readonly array $smtp = [],
    ) {
    }

    public function driver(): string
    {
        return $this->driver;
    }

    /** @return bool true when the message was written to the local outbox */
    public function send(string $to, string $subject, string $text): bool
    {
        if ($this->driver === 'smtp') {
            try {
                (new \Vitaliq\Messaging\SmtpTransport([
                    'host' => (string) ($this->smtp['host'] ?? ''), 'port' => (int) ($this->smtp['port'] ?? 587),
                    'encryption' => (string) ($this->smtp['encryption'] ?? 'tls'), 'username' => (string) ($this->smtp['username'] ?? ''),
                    'password' => (string) ($this->smtp['password'] ?? ''), 'from' => $this->from,
                ]))->send($to, $subject, $text);
                return true;
            } catch (\RuntimeException $e) {
                error_log('Vitaliq mail failed: ' . $e->getMessage());
                return false;
            }
        }
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

<?php

declare(strict_types=1);

namespace Vitaliq\Messaging;

use RuntimeException;

/**
 * Plain-text email over SMTP with STARTTLS or implicit TLS and AUTH LOGIN
 * (feature 31). Certificates are verified. Used when MAIL_DRIVER=smtp.
 */
final class SmtpTransport
{
    /** @var resource|null */
    private $socket = null;

    /**
     * @param array{host:string,port:int,encryption:string,username:string,password:string,from:string,timeout?:int} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /** Build the message. Header injection is impossible: CR and LF are removed from header values. */
    public static function message(string $from, string $to, string $subject, string $text, string $messageId): string
    {
        $h = static fn (string $v): string => str_replace(["\r", "\n"], '', $v);
        $encodedSubject = preg_match('/[^\x20-\x7E]/', $subject) ? '=?UTF-8?B?' . base64_encode($h($subject)) . '?=' : $h($subject);
        $body = str_replace(["\r\n", "\r"], "\n", $text);
        $body = (string) preg_replace('/^\./m', '..', $body);
        return 'From: ' . $h($from) . "\r\n"
            . 'To: ' . $h($to) . "\r\n"
            . 'Subject: ' . $encodedSubject . "\r\n"
            . 'Date: ' . date(DATE_RFC2822) . "\r\n"
            . 'Message-ID: <' . $h($messageId) . ">\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . rtrim(chunk_split(base64_encode($body), 76, "\r\n")) . "\r\n";
    }

    /** @return string the server's queue reference, when given */
    public function send(string $to, string $subject, string $text): string
    {
        $c = $this->config;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid recipient address.');
        }
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $c['host']]]);
        $scheme = $c['encryption'] === 'ssl' ? 'ssl' : 'tcp';
        $socket = @stream_socket_client($scheme . '://' . $c['host'] . ':' . $c['port'], $errno, $errstr, $c['timeout'] ?? 15, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new RuntimeException("SMTP connection failed: {$errstr}");
        }
        $this->socket = $socket;
        stream_set_timeout($socket, $c['timeout'] ?? 15);
        try {
            $this->expect(220);
            $host = gethostname() ?: 'vitaliq.local';
            $this->command('EHLO ' . $host, 250);
            if ($c['encryption'] === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                $this->command('EHLO ' . $host, 250);
            }
            if ($c['username'] !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($c['username']), 334);
                $this->command(base64_encode($c['password']), 235, true);
            }
            $from = (string) preg_replace('/^.*<([^>]+)>.*$/', '$1', $c['from']);
            $this->command('MAIL FROM:<' . $from . '>', 250);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', 354);
            $id = bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'vitaliq.local');
            fwrite($socket, self::message($c['from'], $to, $subject, $text, $id) . ".\r\n");
            $reply = $this->expect(250);
            $this->command('QUIT', 221);
            return mb_substr(trim(substr($reply, 4)), 0, 80) ?: $id;
        } finally {
            fclose($socket);
            $this->socket = null;
        }
    }

    /** @param int|list<int> $codes */
    private function command(string $line, int|array $codes, bool $secret = false): string
    {
        fwrite($this->socket, $line . "\r\n");
        try {
            return $this->expect($codes);
        } catch (RuntimeException $e) {
            throw $secret ? new RuntimeException('SMTP authentication was refused.') : $e;
        }
    }

    /** @param int|list<int> $codes */
    private function expect(int|array $codes): string
    {
        $reply = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($reply, 0, 3);
        if (!in_array($code, (array) $codes, true)) {
            throw new RuntimeException('SMTP server replied ' . trim(mb_substr($reply, 0, 120)));
        }
        return $reply;
    }
}

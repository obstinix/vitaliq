<?php

declare(strict_types=1);

namespace Vitaliq\Messaging;

use RuntimeException;

/**
 * Outside delivery channels (features 31-33), chosen by configuration:
 *
 *   email     MAIL_DRIVER     none | outbox (local .eml files) | smtp
 *   sms       SMS_DRIVER      none | log (local file) | twilio
 *   whatsapp  WHATSAPP_DRIVER none | log (local file) | twilio
 *
 * "outbox" and "log" never send anything off the server; deliveries made
 * with them are recorded as "simulated". Twilio's Messages API serves both
 * SMS and WhatsApp (From "whatsapp:+..." for WhatsApp).
 */
final class Channels
{
    /** @param array<string,mixed> $config the application config */
    public function __construct(private readonly array $config, private readonly string $storage)
    {
    }

    public function driver(string $channel): string
    {
        return match ($channel) {
            'email' => (string) $this->config['mail']['driver'],
            'sms' => (string) ($this->config['sms']['driver'] ?? 'none'),
            'whatsapp' => (string) ($this->config['whatsapp']['driver'] ?? 'none'),
            default => 'none',
        };
    }

    public function enabled(string $channel): bool
    {
        return $this->driver($channel) !== 'none';
    }

    public function isLive(string $channel): bool
    {
        return in_array($this->driver($channel), ['smtp', 'twilio'], true);
    }

    /**
     * @return array{status:string,ref:?string}
     * @throws RuntimeException on a provider failure (the caller retries)
     */
    public function send(string $channel, string $recipient, string $subject, string $text): array
    {
        $driver = $this->driver($channel);
        if ($driver === 'none') {
            return ['status' => 'skipped', 'ref' => null];
        }
        if (in_array($driver, ['outbox', 'log'], true)) {
            $dir = $this->storage . '/' . ($channel === 'email' ? 'mail' : 'messages');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $file = $dir . '/' . date('Ymd-His') . '-' . $channel . '-' . bin2hex(random_bytes(4)) . ($channel === 'email' ? '.eml' : '.txt');
            $content = $channel === 'email'
                ? SmtpTransport::message((string) $this->config['mail']['from'], $recipient, $subject, $text, basename($file) . '@outbox')
                : 'To: ' . $recipient . "\nChannel: " . $channel . "\n\n" . $subject . "\n" . $text . "\n";
            file_put_contents($file, $content, LOCK_EX);
            return ['status' => 'simulated', 'ref' => basename($file)];
        }
        if ($channel === 'email' && $driver === 'smtp') {
            $m = $this->config['mail'];
            $ref = (new SmtpTransport([
                'host' => (string) $m['host'], 'port' => (int) $m['port'], 'encryption' => (string) $m['encryption'],
                'username' => (string) $m['username'], 'password' => (string) $m['password'], 'from' => (string) $m['from'],
            ]))->send($recipient, $subject, $text);
            return ['status' => 'sent', 'ref' => $ref];
        }
        if ($driver === 'twilio') {
            $c = $this->config[$channel];
            if (($c['account_sid'] ?? '') === '' || ($c['auth_token'] ?? '') === '' || ($c['from'] ?? '') === '') {
                throw new RuntimeException('Twilio is selected but its credentials are not configured.');
            }
            $to = $channel === 'whatsapp' ? 'whatsapp:' . $recipient : $recipient;
            $from = $channel === 'whatsapp' && !str_starts_with((string) $c['from'], 'whatsapp:') ? 'whatsapp:' . $c['from'] : (string) $c['from'];
            $res = HttpClient::request('POST',
                'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode((string) $c['account_sid']) . '/Messages.json',
                ['Authorization' => 'Basic ' . base64_encode($c['account_sid'] . ':' . $c['auth_token']), 'Content-Type' => 'application/x-www-form-urlencoded'],
                http_build_query(['To' => $to, 'From' => $from, 'Body' => mb_substr($subject . "\n" . $text, 0, 1500)]));
            $data = json_decode($res['body'], true);
            if ($res['status'] < 200 || $res['status'] >= 300) {
                throw new RuntimeException('Twilio replied ' . $res['status'] . (is_array($data) && isset($data['message']) ? ': ' . mb_substr((string) $data['message'], 0, 160) : ''));
            }
            return ['status' => 'sent', 'ref' => is_array($data) ? mb_substr((string) ($data['sid'] ?? ''), 0, 80) : null];
        }
        throw new RuntimeException("Unknown {$channel} driver '{$driver}'.");
    }
}

<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use DateTimeImmutable;
use RuntimeException;
use Vitaliq\Core\App;
use Vitaliq\Core\Config;
use Vitaliq\Messaging\Channels;
use Vitaliq\Messaging\HttpClient;
use Vitaliq\Messaging\SmtpTransport;
use Vitaliq\Services\BroadcastService;
use Vitaliq\Services\DeliveryService;
use Vitaliq\Services\NotificationService;
use Vitaliq\Services\ValidationFailed;
use Vitaliq\Support\Lang;
use Vitaliq\Tests\HttpTestCase;

final class NotificationChannelsTest extends HttpTestCase
{
    protected function tearDown(): void
    {
        HttpClient::fake(null);
        Lang::set('en');
        parent::tearDown();
    }

    /** @param array<string,mixed> $overrides */
    private function appWith(array $overrides): App
    {
        return new App(array_replace_recursive(Config::fromEnv(), $overrides), $this->db, $this->clock);
    }

    public function testNothingLeavesTheAppUntilTheUserOptsIn(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $app = $this->appWith(['mail' => ['driver' => 'outbox']]);
        $app->service(NotificationService::class)->notify($user, 'result.available', 'Result ready', 'Your result is ready.', '/patient');
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM notification_deliveries'));

        $delivery = $app->service(DeliveryService::class);
        $delivery->savePreferences($user, ['prefs' => ['results' => ['email' => '1', 'sms' => '1']]]);
        $this->assertTrue($delivery->preferences($user)['results']['email']);
        $this->assertFalse($delivery->preferences($user)['appointments']['email']);
        $app->service(NotificationService::class)->notify($user, 'result.available', 'Result ready', 'Your result is ready.', '/patient');
        $app->service(NotificationService::class)->notify($user, 'appointment', 'Booked', 'Appointment booked.');
        $this->assertSame(['email'], array_column($this->db->all('SELECT channel FROM notification_deliveries'), 'channel'), 'SMS is off on this server and appointments are not opted in');

        $stats = $delivery->process();
        $this->assertSame(1, $stats['simulated']);
        $row = $this->db->one('SELECT * FROM notification_deliveries');
        $this->assertSame('simulated', $row['status']);
        $file = VQ_ROOT . '/storage/mail/' . $row['provider_ref'];
        $this->assertFileExists($file);
        $this->assertStringContainsString('To: kavya@example.com', (string) file_get_contents($file));
        @unlink($file);
    }

    public function testTwilioSmsAndWhatsAppWithRetries(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $mobile = (string) $this->db->value('SELECT mobile FROM users WHERE id = :id', ['id' => $user]);
        $twilio = ['driver' => 'twilio', 'account_sid' => 'AC123', 'auth_token' => 'secret', 'from' => '+15005550006'];
        $app = $this->appWith(['sms' => $twilio, 'whatsapp' => ['from' => '+14155238886'] + $twilio]);
        $calls = [];
        $fail = 2;
        HttpClient::fake(function (string $method, string $url, array $headers, string $body) use (&$calls, &$fail): array {
            parse_str($body, $form);
            $calls[] = ['url' => $url, 'auth' => $headers['Authorization'], 'form' => $form];
            if ($fail-- > 0) {
                return ['status' => 503, 'body' => '{"message":"Service unavailable"}'];
            }
            return ['status' => 201, 'body' => '{"sid":"SM' . count($calls) . '"}'];
        });
        $delivery = $app->service(DeliveryService::class);
        $delivery->savePreferences($user, ['prefs' => ['appointments' => ['sms' => '1', 'whatsapp' => '1']]]);
        $app->service(NotificationService::class)->notify($user, 'reminder', 'Appointment reminder', 'Tomorrow at 10:20.');

        $this->assertSame(['sent' => 0, 'simulated' => 0, 'failed' => 0, 'retry' => 2], $delivery->process());
        $this->assertSame(['sent' => 0, 'simulated' => 0, 'failed' => 0, 'retry' => 0], $delivery->process(), 'backoff: not retried immediately');
        $this->clock->freeze(new DateTimeImmutable('2026-10-10 10:02:00'));
        $this->assertSame(2, $delivery->process()['sent']);
        $sms = $calls[2]['form'];
        $this->assertSame('+91' . $mobile, $sms['To']);
        $this->assertStringContainsString('Appointment reminder', $sms['Body']);
        $this->assertSame('Basic ' . base64_encode('AC123:secret'), $calls[2]['auth']);
        $this->assertStringContainsString('/Accounts/AC123/Messages.json', $calls[2]['url']);
        $this->assertSame('whatsapp:+91' . $mobile, $calls[3]['form']['To']);
        $this->assertSame('whatsapp:+14155238886', $calls[3]['form']['From']);
        $this->assertSame(['SM3', 'SM4'], array_column($this->db->all('SELECT provider_ref FROM notification_deliveries ORDER BY id'), 'provider_ref'));
    }

    public function testDeliveriesGiveUpAfterFiveAttemptsAndMissingCredentialsAreAnError(): void
    {
        $channels = new Channels(['sms' => ['driver' => 'twilio', 'account_sid' => '', 'auth_token' => '', 'from' => ''], 'mail' => ['driver' => 'none']], sys_get_temp_dir());
        try {
            $channels->send('sms', '+919800000000', 'x', 'y');
            $this->fail('Sent without credentials');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
        $this->assertSame('skipped', $channels->send('email', 'a@b.c', 's', 't')['status']);

        $user = $this->createPatient('kavya@example.com');
        $app = $this->appWith(['sms' => ['driver' => 'twilio', 'account_sid' => 'AC1', 'auth_token' => 't', 'from' => '+1']]);
        HttpClient::fake(static fn () => ['status' => 400, 'body' => '{"message":"Invalid To number"}']);
        $delivery = $app->service(DeliveryService::class);
        $delivery->savePreferences($user, ['prefs' => ['results' => ['sms' => '1']]]);
        $app->service(NotificationService::class)->notify($user, 'result.available', 'Ready', 'Ready.');
        for ($i = 0; $i < 5; $i++) {
            $this->clock->freeze((new DateTimeImmutable(self::NOW))->modify('+' . (20 * $i) . ' minutes'));
            $delivery->process();
        }
        $row = $this->db->one('SELECT * FROM notification_deliveries');
        $this->assertSame('failed', $row['status']);
        $this->assertSame(5, (int) $row['attempts']);
        $this->assertStringContainsString('Invalid To number', (string) $row['last_error']);
    }

    public function testSmtpMessageIsSafeAndEncoded(): void
    {
        $msg = SmtpTransport::message('Vitaliq <no-reply@vitaliq.example>', "a@b.c\r\nBcc: evil@x.y", "Résultat\r\nBcc: evil@x.y", "line 1\n.hidden\nline 3", 'id@vitaliq');
        $this->assertStringNotContainsString("\r\nBcc:", $msg);
        $this->assertStringContainsString('Subject: =?UTF-8?B?', $msg);
        $body = base64_decode(trim(substr($msg, strpos($msg, "\r\n\r\n") + 4)));
        $this->assertStringContainsString("\n..hidden\n", $body, 'leading dots are doubled');
    }

    public function testEventStreamReportsUnreadAndNewSinceLastId(): void
    {
        $user = $this->createPatient('kavya@example.com');
        $this->signIn('kavya@example.com');
        $first = $this->get('/api/notifications/stream');
        $this->assertSame('text/event-stream; charset=UTF-8', $first->header('Content-Type'));
        $this->assertMatchesRegularExpression('/^retry: \d+\nid: 0\nevent: state\ndata: \{"unread":0,"new":0\}\n\n$/', $first->body);
        $notes = $this->app->service(NotificationService::class);
        $a = $notes->notify($user, 'x', 'One', 'One');
        $notes->notify($user, 'x', 'Two', 'Two');
        $next = $this->get('/api/notifications/stream', [], ['last-event-id' => (string) $a]);
        $this->assertStringContainsString("event: notification\n", $next->body);
        $this->assertStringContainsString('"unread":2,"new":1', $next->body);
        $this->newBrowser();
        $this->assertSame(401, $this->get('/api/notifications/stream', [], ['accept' => 'application/json'])->status);
    }

    public function testBroadcastReachesItsAudienceOnly(): void
    {
        $admin = $this->createAdmin();
        $ka = $this->createPatient('ka@example.com');
        $this->db->execute("UPDATE patients SET state_code = 'KA' WHERE user_id = :u", ['u' => $ka]);
        $this->createPatient('kl@example.com');
        $this->createUser('lab', 'lab@vitaliq.test');
        $svc = $this->app->service(BroadcastService::class);
        $this->assertSame(1, $svc->count('state', 'KA'));
        $this->assertSame(2, $svc->count('staff', ''));
        try {
            $svc->send(['title' => 'Clinic hours', 'body' => 'Open on Sunday.', 'audience' => 'state', 'audience_value' => 'ZZ', 'link_path' => 'https://evil.example'], $admin);
            $this->fail('Bad state and external link accepted');
        } catch (ValidationFailed $e) {
            $this->assertArrayHasKey('audience_value', $e->errors);
            $this->assertArrayHasKey('link_path', $e->errors);
        }
        $r = $svc->send(['title' => 'Clinic hours', 'body' => 'Indiranagar is open this Sunday.', 'audience' => 'state', 'audience_value' => 'KA', 'link_path' => '/announcements'], $admin);
        $this->assertSame(1, $r['recipients']);
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'broadcast' AND user_id = :u", ['u' => $ka]));
        $this->signIn('admin@vitaliq.test');
        $page = $this->get('/admin/broadcasts');
        $this->assertSame(200, $page->status);
        $this->assertStringContainsString('Clinic hours', $page->body);
    }

    public function testLanguageAndAccessibilityPreferences(): void
    {
        $this->assertSame('hi', Lang::negotiate('hi-IN,hi;q=0.9,en;q=0.8'));
        $this->assertSame('en', Lang::negotiate('fr-FR'));
        $this->createPatient('kavya@example.com');
        $this->signIn('kavya@example.com');
        $this->post('/account/preferences', ['locale' => 'hi', 'accessibility_mode' => '1', 'prefs' => ['results' => ['email' => '1']]]);
        $page = $this->get('/patient');
        $this->assertStringContainsString('आपका स्वास्थ्य', $page->body);
        $this->assertStringContainsString('स्वास्थ्य पासपोर्ट', $page->body, 'the navigation is translated');
        $this->assertStringContainsString('class="layout-app a11y-mode"', $page->body);
        $this->assertStringContainsString('lang="hi"', $page->body);
        $this->assertSame('hi', $this->db->value("SELECT locale FROM users WHERE email = 'kavya@example.com'"));

        $this->post('/locale', ['locale' => 'mr', 'back' => '/patient']);
        $this->assertStringContainsString('तुमचे आरोग्य', $this->get('/patient')->body);
        $this->assertRedirectsTo('/', $this->post('/locale', ['locale' => 'xx', 'back' => '//evil.example']));
        $this->assertStringContainsString('Your health', $this->get('/patient')->body, 'an unknown language falls back to English');

        $this->newBrowser();
        $this->get('/');
        $this->post('/locale', ['locale' => 'mr', 'back' => '/']);
        $this->assertStringContainsString('लक्षणे', $this->get('/')->body, 'visitors can switch language too');
    }
}

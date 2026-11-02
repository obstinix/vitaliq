<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Functional;

use DateTimeImmutable;
use RuntimeException;
use Vitaliq\Auth\Totp;
use Vitaliq\Services\AccountSecurityService;
use Vitaliq\Services\DocumentVaultService;
use Vitaliq\Services\RetentionService;
use Vitaliq\Support\Crypto;
use Vitaliq\Tests\HttpTestCase;

final class SecurityFeaturesTest extends HttpTestCase
{
    private const UA_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
    private const UA_PHONE = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

    private function login(string $email, string $ua = self::UA_DESKTOP): \Vitaliq\Core\Response
    {
        $this->request('GET', '/login', [], [], ['user-agent' => $ua]);
        return $this->request('POST', '/login', [], ['email' => $email, 'password' => 'correct horse 42', '_token' => $this->jar['_csrf']], ['user-agent' => $ua]);
    }

    private function codeFor(int $userId, int $offsetSteps = 0): string
    {
        $secret = (new Crypto((string) $this->app->config['key']))->decrypt((string) $this->db->value('SELECT secret_enc FROM user_two_factor WHERE user_id = :u', ['u' => $userId]), 'totp:' . $userId);
        return Totp::code($secret, Totp::step($this->clock->now()->getTimestamp()) + $offsetSteps);
    }

    public function testTotpMatchesRfc6238(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
        // RFC 6238 appendix B (SHA-1), last six digits.
        $this->assertSame('287082', Totp::code($secret, Totp::step(59)));
        $this->assertSame('081804', Totp::code($secret, Totp::step(1111111109)));
        $this->assertSame('005924', Totp::code($secret, Totp::step(1234567890)));
        $this->assertSame(Totp::step(59), Totp::verify($secret, '287 082', 59));
        $this->assertNull(Totp::verify($secret, '287082', 59, Totp::step(59)), 'a used step is never accepted again');
        $this->assertNull(Totp::verify($secret, '287082', 59 + 120), 'two steps late is too late');
        $this->assertStringStartsWith('otpauth://totp/Vitaliq%3Akavya%40example.com?secret=', Totp::uri($secret, 'kavya@example.com'));
    }

    public function testTwoStepSignInWithReplayProtectionAndRecoveryCodes(): void
    {
        $uid = $this->createPatient('kavya@example.com');
        $this->login('kavya@example.com');
        $this->get('/account/security');
        $this->post('/account/security/two-step');
        $page = $this->get('/account/security');
        $this->assertStringContainsString('<svg', $page->body, 'the set-up QR code is shown');
        $this->assertSame(422, $this->post('/account/security/two-step/confirm', ['code' => '000000'])->status);
        $confirmed = $this->post('/account/security/two-step/confirm', ['code' => $this->codeFor($uid)]);
        $this->assertSame(200, $confirmed->status);
        $this->assertSame(10, preg_match_all('/<li class="ref">([0-9a-f]{6}-[0-9a-f]{6})<\/li>/', $confirmed->body, $m));
        $recovery = $m[1][0];
        $this->assertStringNotContainsString($recovery, (string) $this->db->value('SELECT recovery_codes FROM user_two_factor'), 'only hashes are stored');

        $this->post('/logout');
        $this->newBrowser();
        $step1 = $this->login('kavya@example.com');
        $this->assertRedirectsTo('/login/verify', $step1);
        $this->assertRedirectsTo('/login', $this->get('/patient'));
        $this->assertSame(422, $this->post('/login/verify', ['code' => '123456'])->status);
        // The code used at enrolment cannot be replayed; the next one works.
        $this->clock->freeze(new DateTimeImmutable('2026-10-10 10:00:31'));
        $this->assertRedirectsTo('/patient', $this->post('/login/verify', ['code' => $this->codeFor($uid)]));
        $this->assertSame(200, $this->get('/patient')->status);

        $this->post('/logout');
        $this->newBrowser();
        $this->login('kavya@example.com');
        $this->assertRedirectsTo('/patient', $this->post('/login/verify', ['code' => $recovery]));
        $this->post('/logout');
        $this->newBrowser();
        $this->login('kavya@example.com');
        $this->assertSame(422, $this->post('/login/verify', ['code' => $recovery])->status, 'a recovery code works once');
        $this->assertSame(9, $this->app->service(AccountSecurityService::class)->recoveryCodesLeft($uid));

        // The pending step expires after five minutes.
        $this->clock->freeze(new DateTimeImmutable('2026-10-10 10:06:00'));
        $this->assertRedirectsTo('/login', $this->get('/login/verify'));
    }

    public function testSessionsCanBeEndedRemotelyAndNewDevicesRaiseAnAlert(): void
    {
        $uid = $this->createPatient('kavya@example.com');
        $this->login('kavya@example.com', self::UA_DESKTOP);
        $desktop = $this->jar;
        $this->assertSame(0, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'security'"), 'the first sign-in is not news');

        $this->newBrowser();
        $this->login('kavya@example.com', self::UA_PHONE);
        $alert = $this->db->one("SELECT * FROM notifications WHERE type = 'security' AND user_id = :u", ['u' => $uid]);
        $this->assertStringContainsString('Chrome on Android', (string) $alert['body']);
        $page = $this->get('/account/security');
        $this->assertStringContainsString('Chrome on Windows', $page->body);
        $this->assertStringContainsString('This device', $page->body);

        $this->post('/account/security/sessions/others');
        $phone = $this->jar;
        $this->jar = $desktop;
        $this->assertRedirectsTo('/login', $this->get('/patient'));
        $this->jar = $phone;
        $this->assertSame(200, $this->get('/patient')->status, 'the session that ended the others stays signed in');
    }

    public function testRepeatedFailedSignInsWarnTheAccountOwner(): void
    {
        $uid = $this->createPatient('kavya@example.com');
        $this->get('/login');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'kavya@example.com', 'password' => 'wrong password ' . $i]);
        }
        $this->assertSame(1, (int) $this->db->value("SELECT COUNT(*) FROM notifications WHERE type = 'security' AND user_id = :u AND title = 'Failed sign-in attempts'", ['u' => $uid]));
    }

    public function testEncryptionIsBoundToItsPurposeAndDetectsTampering(): void
    {
        $c = new Crypto((string) $this->app->config['key']);
        $box = $c->encrypt('O+, penicillin allergy', 'totp:1');
        $this->assertSame('O+, penicillin allergy', $c->decrypt($box, 'totp:1'));
        foreach ([[$box, 'totp:2'], [substr($box, 0, -4) . 'AAAA', 'totp:1']] as [$payload, $purpose]) {
            try {
                $c->decrypt($payload, $purpose);
                $this->fail('Decrypted with the wrong key or a damaged payload');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('authentication', $e->getMessage());
            }
        }
    }

    public function testRetentionPurgesOperationalRecordsOnly(): void
    {
        $admin = $this->createAdmin();
        $uid = $this->createPatient('kavya@example.com');
        $this->db->execute("INSERT INTO notifications (user_id, type, title, body, read_at, created_at) VALUES (:u, 'x', 'Old', 'Old', '2025-01-01', '2025-01-01'), (:u2, 'x', 'Unread', 'Unread', NULL, '2025-01-01')", ['u' => $uid, 'u2' => $uid]);
        $this->db->execute("INSERT INTO audit_events (action, entity_type, created_at) VALUES ('old.event', 'x', '2023-01-01')");
        $this->app->service(\Vitaliq\Services\TestingService::class)->submitForUser($uid, $this->testInput(), 'tok');
        $svc = $this->app->service(RetentionService::class);
        $r = $svc->purge($admin);
        $this->assertSame(1, $r['notifications'], 'unread notifications are kept');
        $this->assertSame(1, $r['audit']);
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM test_requests'));
        $this->expectException(\Vitaliq\Services\ValidationFailed::class);
        $svc->savePolicies(['retention_audit_days' => '30'] + array_map(static fn ($p) => (string) $p[1], RetentionService::POLICIES), $admin);
    }

    public function testDeletionRemovesPersonalContentAndKeepsClinicalRecords(): void
    {
        $admin = $this->createAdmin();
        $uid = $this->createPatient('kavya@example.com');
        $pid = (int) $this->db->value('SELECT id FROM patients WHERE user_id = :u', ['u' => $uid]);
        $this->app->service(\Vitaliq\Services\TestingService::class)->submitForUser($uid, $this->testInput(), 'tok');
        $this->app->service(\Vitaliq\Services\PatientPlatformService::class)->savePassport($pid, ['blood_group' => 'A+', 'allergies' => 'Latex']);
        $tmp = tempnam(sys_get_temp_dir(), 'vq');
        file_put_contents($tmp, '%PDF-1.4 test');
        $vault = $this->app->service(DocumentVaultService::class);
        $doc = $vault->store($pid, $uid, ['name' => 'rx.pdf', 'tmp_name' => $tmp, 'size' => 13, 'error' => UPLOAD_ERR_OK], ['title' => 'Prescription', 'category' => 'prescription']);
        $file = $vault->directory() . '/' . $this->db->value('SELECT storage_name FROM patient_documents WHERE id = :id', ['id' => $doc]) . '.bin';

        $this->signIn('kavya@example.com');
        $this->assertSame(422, $this->post('/account/security/delete', ['reason' => 'Moving abroad'])->status, 'needs the confirmation box');
        $this->post('/account/security/delete', ['reason' => 'Moving abroad', 'confirm' => '1']);
        $requestId = (int) $this->db->value('SELECT id FROM deletion_requests');
        $done = $this->app->service(RetentionService::class)->complete($requestId, $admin);
        $this->assertSame(1, $done['documents']);
        $this->assertFileDoesNotExist($file);
        $user = $this->db->one('SELECT * FROM users WHERE id = :id', ['id' => $uid]);
        $this->assertSame('Deleted account', $user['full_name']);
        $this->assertNull($user['password_hash']);
        $this->assertSame(0, (int) $user['is_active']);
        $this->assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM health_profiles'));
        $this->assertNull($this->db->value('SELECT user_id FROM patients WHERE id = :p', ['p' => $pid]));
        $this->assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM test_requests WHERE patient_id = :p', ['p' => $pid]), 'clinical records stay');
        $this->assertRedirectsTo('/login', $this->get('/patient'));
        @unlink($tmp);
    }
}

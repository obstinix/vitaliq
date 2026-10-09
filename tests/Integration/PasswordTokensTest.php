<?php

declare(strict_types=1);

namespace Vitaliq\Tests\Integration;

use Vitaliq\Auth\PasswordTokens;
use Vitaliq\Tests\DatabaseTestCase;

final class PasswordTokensTest extends DatabaseTestCase
{
    public function testOnlyTheHashIsStored(): void
    {
        $id = $this->createPatient();
        $raw = $this->app->service(PasswordTokens::class)->issue($id, 'reset');
        $this->assertGreaterThanOrEqual(43, strlen($raw));
        $this->assertNull($this->db->value('SELECT id FROM password_tokens WHERE token_hash = :t', ['t' => $raw]));
        $this->assertNotNull($this->db->value('SELECT id FROM password_tokens WHERE token_hash = :t', ['t' => hash('sha256', $raw)]));
    }

    public function testTokenResetsPasswordOnceAndSignsOutSessions(): void
    {
        $id = $this->createPatient();
        $tokens = $this->app->service(PasswordTokens::class);
        $raw = $tokens->issue($id, 'reset');
        $this->assertNotNull($tokens->find($raw));
        $this->assertTrue($tokens->consume($raw, 'a new passphrase 9'));
        $this->assertSame(2, (int) $this->db->value('SELECT session_version FROM users WHERE id = :id', ['id' => $id]));
        $this->assertTrue($this->app->auth()->attempt('patient@vitaliq.test', 'a new passphrase 9')['ok']);

        $this->assertNull($tokens->find($raw), 'Used token is no longer valid');
        $this->assertFalse($tokens->consume($raw, 'yet another pass 1'), 'Token cannot be reused');
    }

    public function testTokenExpiresAfterThirtyMinutes(): void
    {
        $id = $this->createPatient();
        $tokens = $this->app->service(PasswordTokens::class);
        $raw = $tokens->issue($id, 'reset');
        $this->clock->advance('+29 minutes');
        $this->assertNotNull($tokens->find($raw));
        $this->clock->advance('+2 minutes');
        $this->assertNull($tokens->find($raw));
        $this->assertFalse($tokens->consume($raw, 'a new passphrase 9'));
    }

    public function testNewTokenSupersedesTheOldOne(): void
    {
        $id = $this->createPatient();
        $tokens = $this->app->service(PasswordTokens::class);
        $first = $tokens->issue($id, 'reset');
        $second = $tokens->issue($id, 'reset');
        $this->assertNull($tokens->find($first));
        $this->assertNotNull($tokens->find($second));
    }

    public function testTokenForDeactivatedUserIsRefused(): void
    {
        $id = $this->createPatient();
        $raw = $this->app->service(PasswordTokens::class)->issue($id, 'reset');
        $this->db->execute('UPDATE users SET is_active = 0 WHERE id = :id', ['id' => $id]);
        $this->assertNull($this->app->service(PasswordTokens::class)->find($raw));
    }

    public function testMalformedTokensAreRejectedWithoutQuerying(): void
    {
        $tokens = $this->app->service(PasswordTokens::class);
        $this->assertNull($tokens->find("' OR '1'='1"));
        $this->assertNull($tokens->find(''));
    }
}

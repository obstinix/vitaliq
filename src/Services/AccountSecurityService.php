<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use Vitaliq\Auth\Totp;
use Vitaliq\Core\App;
use Vitaliq\Support\Crypto;

/**
 * Two-factor authentication (feature 46), the session register (feature 47)
 * and security alerts (feature 50).
 */
final class AccountSecurityService
{
    private const PURPOSE = 'totp';

    public function __construct(private readonly App $app)
    {
    }

    private function crypto(): Crypto
    {
        return new Crypto((string) $this->app->config['key']);
    }

    /* ------------------------------------------------------------- 2FA */

    public function twoFactorEnabled(int $userId): bool
    {
        return $this->app->db->value('SELECT enabled_at FROM user_two_factor WHERE user_id = :u AND enabled_at IS NOT NULL', ['u' => $userId]) !== null;
    }

    /** Start (or restart) enrolment; returns the plain secret to show once. */
    public function beginEnrolment(int $userId): string
    {
        if ($this->twoFactorEnabled($userId)) {
            throw new WorkflowException('Two-step sign-in is already on. Turn it off first to set up a new app.');
        }
        $secret = Totp::newSecret();
        $this->app->db->execute(
            'INSERT INTO user_two_factor (user_id, secret_enc, updated_at) VALUES (:u, :s, :t)
             ON DUPLICATE KEY UPDATE secret_enc = VALUES(secret_enc), enabled_at = NULL, last_used_step = NULL, recovery_codes = NULL, updated_at = VALUES(updated_at)',
            ['u' => $userId, 's' => $this->crypto()->encrypt($secret, self::PURPOSE . ':' . $userId), 't' => $this->app->clock->nowSql()]
        );
        return $secret;
    }

    public function pendingSecret(int $userId): ?string
    {
        $row = $this->app->db->one('SELECT secret_enc FROM user_two_factor WHERE user_id = :u AND enabled_at IS NULL', ['u' => $userId]);
        return $row === null ? null : $this->crypto()->decrypt((string) $row['secret_enc'], self::PURPOSE . ':' . $userId);
    }

    /**
     * Confirm enrolment with a code from the app. Returns ten recovery codes,
     * shown once.
     *
     * @return list<string>
     */
    public function confirmEnrolment(int $userId, string $code): array
    {
        $secret = $this->pendingSecret($userId);
        if ($secret === null) {
            throw new WorkflowException('Start the set-up again.');
        }
        $step = Totp::verify($secret, $code, $this->app->clock->now()->getTimestamp());
        if ($step === null) {
            throw new ValidationFailed(['code' => 'That code did not match. Check the time on your phone and try the newest code.']);
        }
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtolower(bin2hex(random_bytes(3)) . '-' . bin2hex(random_bytes(3)));
        }
        $this->app->db->execute(
            'UPDATE user_two_factor SET enabled_at = :t, last_used_step = :s, recovery_codes = :r, updated_at = :t2 WHERE user_id = :u',
            ['t' => $this->app->clock->nowSql(), 's' => $step, 'r' => json_encode(array_map(static fn ($c) => hash('sha256', $c), $codes)), 't2' => $this->app->clock->nowSql(), 'u' => $userId]
        );
        $this->app->service(AuditLog::class)->record('security.2fa_enabled', 'user', $userId);
        $this->alert($userId, 'Two-step sign-in turned on', 'Signing in now needs a code from your authenticator app.');
        return $codes;
    }

    /** Check a code or a recovery code at sign-in. Used codes cannot be replayed. */
    public function verifyLogin(int $userId, string $input): bool
    {
        $row = $this->app->db->one('SELECT * FROM user_two_factor WHERE user_id = :u AND enabled_at IS NOT NULL', ['u' => $userId]);
        if ($row === null) {
            return false;
        }
        $input = strtolower(trim($input));
        if (preg_match('/^[0-9a-f]{6}-[0-9a-f]{6}$/', $input)) {
            $hashes = json_decode((string) $row['recovery_codes'], true) ?: [];
            $h = hash('sha256', $input);
            $idx = array_search($h, $hashes, true);
            if ($idx === false) {
                return false;
            }
            unset($hashes[$idx]);
            $this->app->db->execute('UPDATE user_two_factor SET recovery_codes = :r WHERE user_id = :u', ['r' => json_encode(array_values($hashes)), 'u' => $userId]);
            $this->app->service(AuditLog::class)->record('security.recovery_code_used', 'user', $userId, ['left' => count($hashes)], $userId);
            $this->alert($userId, 'Recovery code used', 'A recovery code was used to sign in. ' . count($hashes) . ' remain.');
            return true;
        }
        $secret = $this->crypto()->decrypt((string) $row['secret_enc'], self::PURPOSE . ':' . $userId);
        $step = Totp::verify($secret, $input, $this->app->clock->now()->getTimestamp(), $row['last_used_step'] === null ? null : (int) $row['last_used_step']);
        if ($step === null) {
            return false;
        }
        $this->app->db->execute('UPDATE user_two_factor SET last_used_step = :s WHERE user_id = :u', ['s' => $step, 'u' => $userId]);
        return true;
    }

    public function disable(int $userId, string $password): void
    {
        $hash = (string) $this->app->db->value('SELECT password_hash FROM users WHERE id = :u', ['u' => $userId]);
        if (!\Vitaliq\Auth\Passwords::verify($password, $hash)) {
            throw new ValidationFailed(['password' => 'The password is incorrect.']);
        }
        $this->app->db->execute('DELETE FROM user_two_factor WHERE user_id = :u', ['u' => $userId]);
        $this->app->service(AuditLog::class)->record('security.2fa_disabled', 'user', $userId);
        $this->alert($userId, 'Two-step sign-in turned off', 'If this was not you, change your password now.');
    }

    public function recoveryCodesLeft(int $userId): int
    {
        $raw = $this->app->db->value('SELECT recovery_codes FROM user_two_factor WHERE user_id = :u', ['u' => $userId]);
        return $raw === null ? 0 : count(json_decode((string) $raw, true) ?: []);
    }

    /* -------------------------------------------------------- sessions */

    /** Describe a user agent in a few words, e.g. "Chrome on Android". */
    public static function device(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => 'Unknown browser',
            default => 'Other browser',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'an unknown system',
        };
        return $browser . ' on ' . $os;
    }

    /** Record a new session; returns the key to keep in the session store. */
    public function startSession(int $userId): string
    {
        $key = bin2hex(random_bytes(32));
        $device = self::device($this->app->request()->header('user-agent'));
        $deviceHash = hash('sha256', $device . '|' . $this->app->ipHash());
        $known = (int) $this->app->db->value('SELECT COUNT(*) FROM user_sessions WHERE user_id = :u AND device_hash = :d', ['u' => $userId, 'd' => $deviceHash]);
        $any = (int) $this->app->db->value('SELECT COUNT(*) FROM user_sessions WHERE user_id = :u', ['u' => $userId]);
        $now = $this->app->clock->nowSql();
        $this->app->db->insert(
            'INSERT INTO user_sessions (user_id, key_hash, device, device_hash, ip_hash, created_at, last_seen_at) VALUES (:u, :k, :d, :dh, :ip, :t, :t2)',
            ['u' => $userId, 'k' => hash('sha256', $key), 'd' => $device, 'dh' => $deviceHash, 'ip' => $this->app->ipHash(), 't' => $now, 't2' => $now]
        );
        if ($known === 0 && $any > 0) {
            $this->alert($userId, 'New sign-in', 'Your account was signed in from ' . $device . ' at ' . $this->app->clock->now()->format('j M Y, g:i a') . '. If this was not you, end that session and change your password.');
        }
        return $key;
    }

    /** False when the session has been ended from another device. */
    public function touch(int $userId, string $key): bool
    {
        $row = $this->app->db->one('SELECT id, revoked_at, last_seen_at FROM user_sessions WHERE key_hash = :k AND user_id = :u', ['k' => hash('sha256', $key), 'u' => $userId]);
        if ($row === null || $row['revoked_at'] !== null) {
            return false;
        }
        if (strtotime((string) $row['last_seen_at']) < $this->app->clock->now()->getTimestamp() - 300) {
            $this->app->db->execute('UPDATE user_sessions SET last_seen_at = :t WHERE id = :id', ['t' => $this->app->clock->nowSql(), 'id' => $row['id']]);
        }
        return true;
    }

    /** @return list<array<string,mixed>> */
    public function sessions(int $userId, string $currentKey): array
    {
        $rows = $this->app->db->all('SELECT id, device, created_at, last_seen_at, key_hash FROM user_sessions WHERE user_id = :u AND revoked_at IS NULL ORDER BY last_seen_at DESC LIMIT 50', ['u' => $userId]);
        $cur = $currentKey === '' ? '' : hash('sha256', $currentKey);
        foreach ($rows as &$r) {
            $r['current'] = hash_equals((string) $r['key_hash'], $cur);
            unset($r['key_hash']);
        }
        return $rows;
    }

    public function revoke(int $userId, int $sessionId): bool
    {
        return $this->app->db->execute('UPDATE user_sessions SET revoked_at = :t WHERE id = :id AND user_id = :u AND revoked_at IS NULL',
            ['t' => $this->app->clock->nowSql(), 'id' => $sessionId, 'u' => $userId]) === 1;
    }

    public function revokeOthers(int $userId, string $currentKey): int
    {
        $n = $this->app->db->execute('UPDATE user_sessions SET revoked_at = :t WHERE user_id = :u AND revoked_at IS NULL AND key_hash <> :k',
            ['t' => $this->app->clock->nowSql(), 'u' => $userId, 'k' => hash('sha256', $currentKey)]);
        $this->app->service(AuditLog::class)->record('security.sessions_ended', 'user', $userId, ['count' => $n]);
        return $n;
    }

    public function endCurrent(string $key): void
    {
        $this->app->db->execute('UPDATE user_sessions SET revoked_at = :t WHERE key_hash = :k AND revoked_at IS NULL', ['t' => $this->app->clock->nowSql(), 'k' => hash('sha256', $key)]);
    }

    /* ---------------------------------------------------------- alerts */

    public function alert(int $userId, string $title, string $body): void
    {
        $this->app->service(NotificationService::class)->notify($userId, 'security', $title, $body, '/account/security');
    }

    /** Called after a failed password for a known account; alerts at most once an hour. */
    public function failedLogin(int $userId, int $failures): void
    {
        if ($failures >= 5 && $this->app->rateLimiter()->hit('security-alert:' . $userId, 1, 3600)) {
            $this->alert($userId, 'Failed sign-in attempts', $failures . ' wrong passwords were entered for your account. Sign-in is paused for 15 minutes. If this was not you, consider turning on two-step sign-in.');
        }
    }
}

<?php

declare(strict_types=1);

namespace Vitaliq\Auth;

use Vitaliq\Core\App;
use Vitaliq\Services\AuditLog;

/**
 * Session authentication. The session stores only the user id and the
 * user's session_version; every request re-reads the user row so that
 * deactivation and "sign out everywhere" take effect immediately.
 */
final class Auth
{
    public const LOGIN_MAX_PER_ACCOUNT = 5;
    public const LOGIN_MAX_PER_IP = 30;
    public const LOGIN_WINDOW_SECONDS = 900;

    /** @var array<string,mixed>|null */
    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(private readonly App $app)
    {
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;
        $session = $this->app->session();
        $id = $session->get('user_id');
        if (!is_int($id)) {
            return null;
        }
        $row = $this->app->db->one(
            'SELECT id, role, full_name, email, mobile, is_active, session_version FROM users WHERE id = :id',
            ['id' => $id]
        );
        if ($row === null || (int) $row['is_active'] !== 1
            || (int) $row['session_version'] !== (int) $session->get('session_version')) {
            $session->invalidate();
            return null;
        }
        $row['id'] = (int) $row['id'];
        return $this->user = $row;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    public function role(): ?string
    {
        return $this->user()['role'] ?? null;
    }

    public function is(string ...$roles): bool
    {
        return in_array($this->role(), $roles, true);
    }

    public function homePath(?string $role = null): string
    {
        return match ($role ?? $this->role()) {
            'admin' => '/admin',
            'phlebotomist' => '/staff',
            'qa' => '/qa',
            default => '/patient',
        };
    }

    public function enforceIdleTimeout(int $idleMinutes): void
    {
        $session = $this->app->session();
        if (!is_int($session->get('user_id'))) {
            return;
        }
        $now = $this->app->clock->now()->getTimestamp();
        $last = (int) $session->get('last_activity', $now);
        if ($now - $last > $idleMinutes * 60) {
            $session->invalidate();
            $this->resolved = false;
            $this->user = null;
            $session->flash('notice', 'You were signed out after a period of inactivity.');
            return;
        }
        $session->set('last_activity', $now);
    }

    /**
     * Verify credentials. Error messages never reveal whether the email exists.
     *
     * @return array{ok:bool,error:?string}
     */
    public function attempt(string $email, string $password): array
    {
        $email = mb_strtolower(trim($email));
        $limiter = $this->app->rateLimiter();
        $accountBucket = 'login:' . $email;
        $ipBucket = 'login-ip:' . $this->app->ipHash();

        if ($limiter->tooMany($accountBucket, self::LOGIN_MAX_PER_ACCOUNT)
            || !$limiter->hit($ipBucket, self::LOGIN_MAX_PER_IP, self::LOGIN_WINDOW_SECONDS)) {
            return ['ok' => false, 'error' => 'Too many sign-in attempts. Please wait 15 minutes and try again.'];
        }

        $user = $this->app->db->one(
            'SELECT id, role, email, password_hash, is_active, session_version FROM users WHERE email = :e',
            ['e' => $email]
        );
        // Verify against a dummy hash when the user is missing so response
        // time does not reveal which emails are registered.
        // (The dummy is a real Argon2id hash of a random, discarded value.)
        $hash = $user['password_hash'] ?? '$argon2id$v=19$m=65536,t=4,p=1$L2EybEhmYmk5eTFmT3A0Yw$7nWtn4BH6fUzRJnCkzcfeUhBP2IxvBDaFilrtRvM4Bs';
        $valid = Passwords::verify($password, (string) $hash);

        if ($user === null || !$valid || (int) $user['is_active'] !== 1 || $user['password_hash'] === null) {
            $limiter->hit($accountBucket, self::LOGIN_MAX_PER_ACCOUNT, self::LOGIN_WINDOW_SECONDS);
            $this->app->service(AuditLog::class)->record('auth.login_failed', 'user', $user !== null ? (int) $user['id'] : null, [], null);
            return ['ok' => false, 'error' => 'The email or password is incorrect, or the account is inactive.'];
        }

        if (Passwords::needsRehash((string) $user['password_hash'])) {
            $this->app->db->execute('UPDATE users SET password_hash = :h WHERE id = :id', [
                'h' => Passwords::hash($password),
                'id' => (int) $user['id'],
            ]);
        }
        $limiter->clear($accountBucket);
        $this->login((int) $user['id'], (int) $user['session_version']);
        return ['ok' => true, 'error' => null];
    }

    public function login(int $userId, int $sessionVersion): void
    {
        $session = $this->app->session();
        $session->regenerate();
        $session->set('user_id', $userId);
        $session->set('session_version', $sessionVersion);
        $session->set('last_activity', $this->app->clock->now()->getTimestamp());
        $this->app->csrf()->rotate();
        $this->app->db->execute('UPDATE users SET last_login_at = :t WHERE id = :id', [
            't' => $this->app->clock->nowSql(),
            'id' => $userId,
        ]);
        $this->resolved = false;
        $this->app->service(AuditLog::class)->record('auth.login', 'user', $userId, [], $userId);
    }

    public function logout(): void
    {
        $id = $this->id();
        if ($id !== null) {
            $this->app->service(AuditLog::class)->record('auth.logout', 'user', $id, [], $id);
        }
        $this->app->session()->invalidate();
        $this->app->csrf()->rotate();
        $this->resolved = true;
        $this->user = null;
    }

    /** Forget the cached user so the next call re-reads the database. */
    public function refresh(): void
    {
        $this->resolved = false;
        $this->user = null;
    }
}

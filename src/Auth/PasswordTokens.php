<?php

declare(strict_types=1);

namespace Vitaliq\Auth;

use Vitaliq\Core\App;
use Vitaliq\Services\AuditLog;

/**
 * Single-use, expiring tokens for password reset and staff invitations.
 * The raw token exists only in the link given to the user; the database
 * stores its SHA-256 hash.
 */
final class PasswordTokens
{
    public const RESET_TTL_MINUTES = 30;
    public const INVITE_TTL_MINUTES = 2880;

    public function __construct(private readonly App $app)
    {
    }

    /** Issue a token, superseding earlier unused tokens for the same purpose. */
    public function issue(int $userId, string $purpose): string
    {
        $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $now = $this->app->clock->now();
        $ttl = $purpose === 'invite' ? self::INVITE_TTL_MINUTES : self::RESET_TTL_MINUTES;

        $this->app->db->transaction(function ($db) use ($userId, $purpose, $raw, $now, $ttl): void {
            $db->execute(
                'UPDATE password_tokens SET used_at = :t WHERE user_id = :u AND purpose = :p AND used_at IS NULL',
                ['t' => $now->format('Y-m-d H:i:s'), 'u' => $userId, 'p' => $purpose]
            );
            $db->insert(
                'INSERT INTO password_tokens (user_id, purpose, token_hash, expires_at, created_at)
                 VALUES (:u, :p, :h, :x, :c)',
                [
                    'u' => $userId,
                    'p' => $purpose,
                    'h' => hash('sha256', $raw),
                    'x' => $now->modify("+{$ttl} minutes")->format('Y-m-d H:i:s'),
                    'c' => $now->format('Y-m-d H:i:s'),
                ]
            );
        });
        return $raw;
    }

    /**
     * Look up a usable token without consuming it.
     *
     * @return array{token_id:int,user_id:int,email:string,full_name:string,purpose:string}|null
     */
    public function find(string $raw): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,64}$/', $raw)) {
            return null;
        }
        $row = $this->app->db->one(
            'SELECT t.id AS token_id, t.user_id, t.purpose, u.email, u.full_name
               FROM password_tokens t JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = :h AND t.used_at IS NULL AND t.expires_at > :now AND u.is_active = 1',
            ['h' => hash('sha256', $raw), 'now' => $this->app->clock->nowSql()]
        );
        if ($row === null) {
            return null;
        }
        $row['token_id'] = (int) $row['token_id'];
        $row['user_id'] = (int) $row['user_id'];
        return $row;
    }

    /**
     * Set a new password and consume the token atomically. Signs the user
     * out of every existing session by bumping session_version.
     */
    public function consume(string $raw, string $newPassword): bool
    {
        return $this->app->db->transaction(function ($db) use ($raw, $newPassword): bool {
            $row = $db->one(
                'SELECT t.id, t.user_id, t.purpose FROM password_tokens t JOIN users u ON u.id = t.user_id
                  WHERE t.token_hash = :h AND t.used_at IS NULL AND t.expires_at > :now AND u.is_active = 1
                  FOR UPDATE',
                ['h' => hash('sha256', $raw), 'now' => $this->app->clock->nowSql()]
            );
            if ($row === null) {
                return false;
            }
            $now = $this->app->clock->nowSql();
            $db->execute('UPDATE password_tokens SET used_at = :t WHERE user_id = :u AND used_at IS NULL', [
                't' => $now,
                'u' => (int) $row['user_id'],
            ]);
            $db->execute(
                'UPDATE users SET password_hash = :h, session_version = session_version + 1 WHERE id = :id',
                ['h' => Passwords::hash($newPassword), 'id' => (int) $row['user_id']]
            );
            $this->app->service(AuditLog::class)->record(
                $row['purpose'] === 'invite' ? 'auth.invite_accepted' : 'auth.password_reset',
                'user',
                (int) $row['user_id'],
                [],
                (int) $row['user_id']
            );
            return true;
        });
    }
}

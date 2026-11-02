<?php

declare(strict_types=1);

namespace Vitaliq\Auth;

/**
 * RFC 6238 time-based one-time passwords (HMAC-SHA1, 6 digits, 30 s),
 * compatible with common authenticator apps. Base32 secrets per RFC 4648.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper((string) preg_replace('/[\s=-]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $v = strpos(self::ALPHABET, $c);
            if ($v === false) {
                throw new \InvalidArgumentException('Invalid base32 character.');
            }
            $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }
        return $out;
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function step(int $timestamp): int
    {
        return intdiv($timestamp, self::PERIOD);
    }

    /**
     * Check a code within one step either side of now, never accepting a step
     * at or before $lastUsedStep. Returns the matching step or null.
     */
    public static function verify(string $secret, string $code, int $timestamp, ?int $lastUsedStep = null): ?int
    {
        $code = (string) preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $now = self::step($timestamp);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer = 'Vitaliq'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&period=30&digits=6&algorithm=SHA1';
    }
}

<?php

declare(strict_types=1);

namespace Vitaliq\Auth;

use Vitaliq\Validation\Rules;

/**
 * Password hashing and policy. Argon2id is used when PHP supports it
 * (it does on the tested Windows and Linux builds), otherwise bcrypt.
 */
final class Passwords
{
    /** Small blocklist of the most common passwords that satisfy the length rule. */
    private const COMMON = [
        'password123', 'password1234', 'password12345', 'qwerty12345', 'qwertyuiop1',
        '1234567890a', 'a1234567890', 'iloveyou123', 'welcome1234', 'admin12345',
        'letmein1234', 'abc1234567', 'abcdef12345', 'passw0rd123', 'covid19covid',
        'covid2020pass', 'india123456', 'monkey12345', 'football123', 'sunshine123',
    ];

    public static function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    /** Returns an error message, or null when the password is acceptable. */
    public static function policyError(string $password, string $email = ''): ?string
    {
        if (!Rules::matches('required', $password)) {
            return 'Password is required.';
        }
        $error = Rules::check('password', $password);
        if ($error !== null) {
            return 'Password must be 10–128 characters and include at least one letter and one digit.';
        }
        if (in_array(mb_strtolower($password), self::COMMON, true)) {
            return 'That password is too common. Choose something less predictable.';
        }
        $local = mb_strtolower((string) strstr($email, '@', true));
        if (mb_strlen($local) >= 4 && str_contains(mb_strtolower($password), $local)) {
            return 'Password must not contain the name part of your email address.';
        }
        return null;
    }
}

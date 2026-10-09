<?php

declare(strict_types=1);

namespace Vitaliq\Validation;

use InvalidArgumentException;

/**
 * The single registry of input-format rules. The same pattern source is
 * compiled by PCRE on the server (/.../u) and by the browser
 * (new RegExp(source, "u")), so patterns avoid constructs whose meaning
 * differs between the engines: \d, \w, \s and \b are Unicode-aware in PHP
 * with /u but ASCII-only in JavaScript. Use explicit classes instead.
 *
 * Client-side checks are a convenience; the server always re-validates.
 */
final class Rules
{
    /**
     * @return array<string,array{
     *   label:string, pattern:string, description:string, format:string,
     *   min:int, max:int, normalise:string, valid:list<string>, invalid:list<string>
     * }>
     */
    public static function all(): array
    {
        return [
            'full_name' => [
                'label' => 'Full name',
                'pattern' => "^[\\p{L}\\p{M}](?:[\\p{L}\\p{M}'’.\\- ]*[\\p{L}\\p{M}.])?$",
                'description' => 'Letters from any script, with spaces, hyphens, apostrophes and full stops between them. Digits and other symbols are rejected. A single name is allowed.',
                'format' => '2–100 characters, e.g. “Ananya Iyer”, “O’Brien”, “प्रिया शर्मा”',
                'min' => 2,
                'max' => 100,
                'normalise' => 'collapse',
                'valid' => ['Ananya Iyer', "D'Souza", 'Zoë Mary-Jane', 'प्रिया शर्मा'],
                'invalid' => ['R2D2', 'A', '<b>Raj</b>', 'Ravi_K'],
            ],
            'email' => [
                'label' => 'Email address',
                'pattern' => "^[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~-]+(?:\\.[A-Za-z0-9!#$%&'*+\\/=?^_`{|}~-]+)*@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\\.[A-Za-z]{2,63}$",
                'description' => 'A practical subset of RFC 5322: an ASCII “dot-atom” local part (no leading, trailing or doubled dots), a domain with at least one dot and an alphabetic top-level domain. Internationalised addresses are not supported. The server additionally applies PHP’s FILTER_VALIDATE_EMAIL. Stored in lower case.',
                'format' => 'name@example.com — up to 254 characters',
                'min' => 6,
                'max' => 254,
                'normalise' => 'lower',
                'valid' => ['ananya.iyer@example.com', 'first+tag@sub.example.in'],
                'invalid' => ['ananya@', 'ananya@example', 'an ya@example.com', 'ananya@@example.com'],
            ],
            'indian_mobile' => [
                'label' => 'Indian mobile number',
                'pattern' => '^(?:(?:\\+91|0091|91)[ -]?|0)?[6-9][0-9]{4}[ -]?[0-9]{5}$',
                'description' => 'Ten digits starting with 6, 7, 8 or 9, optionally prefixed by +91, 0091, 91 or 0. A single space or hyphen is allowed after the prefix and between the two groups of five. Stored as the bare 10 digits.',
                'format' => '98765 43210, +91 98765 43210 or 09876543210',
                'min' => 10,
                'max' => 16,
                'normalise' => 'trim',
                'valid' => ['9876543210', '+91 98765 43210', '09876543210', '+91-98765-43210'],
                'invalid' => ['5876543210', '987654321', '98765432100', '+44 7911 123456'],
            ],
            'password' => [
                'label' => 'Password',
                'pattern' => '^(?=.*\\p{L})(?=.*[0-9]).{10,128}$',
                'description' => 'At least 10 characters with at least one letter and one digit. Spaces and passphrases are allowed. Following NIST SP 800-63B, there is no forced symbol or case rule, but very common passwords and passwords containing your email name are rejected on the server.',
                'format' => '10–128 characters, at least one letter and one digit',
                'min' => 10,
                'max' => 128,
                'normalise' => 'none',
                'valid' => ['correct horse 42', 'Mango-Lassi-2026'],
                'invalid' => ['short1', 'onlyletterspassword', '1234567890123'],
            ],
            'reference' => [
                'label' => 'Testing reference',
                'pattern' => '^VQ-[0-9]{6}-[0-9A-HJKMNP-TV-Z]{5}$',
                'description' => 'Issued by Vitaliq when a request is submitted: “VQ-”, the submission date as YYMMDD, a hyphen and five Crockford base-32 characters (no I, L, O or U). Lower-case input is accepted and converted to upper case.',
                'format' => 'VQ-261010-7K3MP',
                'min' => 15,
                'max' => 15,
                'normalise' => 'upper',
                'valid' => ['VQ-261010-7K3MP', 'vq-261010-7k3mp'],
                'invalid' => ['VQ-261010-7K3MO', 'VQ-26101-7K3MP', 'QV-261010-7K3MP'],
            ],
            'pincode' => [
                'label' => 'PIN code',
                'pattern' => '^[1-9][0-9]{5}$',
                'description' => 'Indian postal index number: six digits, first digit 1–9. A space after the third digit is removed before checking.',
                'format' => '560001',
                'min' => 6,
                'max' => 7,
                'normalise' => 'digits_space',
                'valid' => ['560001', '110 001'],
                'invalid' => ['056001', '56001', '5600011', 'ABC123'],
            ],
            'employee_code' => [
                'label' => 'Phlebotomist employee code',
                'pattern' => '^PHL-[0-9]{4}$',
                'description' => 'Internal staff code: “PHL-” followed by four digits.',
                'format' => 'PHL-0007',
                'min' => 8,
                'max' => 8,
                'normalise' => 'upper',
                'valid' => ['PHL-0007', 'phl-1234'],
                'invalid' => ['PHL-7', 'PHL0007', 'LAB-0007'],
            ],
            'sample_barcode' => [
                'label' => 'Sample tube barcode',
                'pattern' => '^[A-Z0-9]{8,14}$',
                'description' => '8–14 upper-case letters or digits, as printed on the collection tube label.',
                'format' => 'VQS20261010A',
                'min' => 8,
                'max' => 14,
                'normalise' => 'upper',
                'valid' => ['VQS20261010A', '12345678'],
                'invalid' => ['VQS-2026', 'ABC123', 'VQS 20261010'],
            ],
            'ct_value' => [
                'label' => 'Ct value',
                // 0–44.9 with an optional decimal, or exactly 45 / 45.0 (see DEF-004).
                'pattern' => '^(?:(?:[0-9]|[1-3][0-9]|4[0-4])(?:\\.[0-9])?|45(?:\\.0)?)$',
                'description' => 'Optional RT-PCR cycle threshold between 0 and 45, with at most one decimal place.',
                'format' => '24.5',
                'min' => 1,
                'max' => 4,
                'normalise' => 'trim',
                'valid' => ['24.5', '9', '45.0'],
                'invalid' => ['46', '24.55', '-3', '2 4'],
            ],
            'iso_date' => [
                'label' => 'Date',
                'pattern' => '^[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])$',
                'description' => 'Calendar date as YYYY-MM-DD, the value an HTML date input submits. The server also rejects impossible dates such as 2026-02-30.',
                'format' => '2026-10-10',
                'min' => 10,
                'max' => 10,
                'normalise' => 'trim',
                'valid' => ['2026-10-10', '1990-02-28'],
                'invalid' => ['10/10/2026', '2026-13-01', '2026-1-5'],
            ],
            'required' => [
                'label' => 'Required field',
                'pattern' => '[\\p{L}\\p{M}\\p{N}\\p{P}\\p{S}]',
                'description' => 'At least one visible character: a letter, mark, number, punctuation or symbol. Input made only of spaces, tabs, line breaks or invisible characters (such as a zero-width space) is treated as empty.',
                'format' => 'Any visible text',
                'min' => 1,
                'max' => 1000,
                'normalise' => 'none',
                'valid' => ['x', ' ok '],
                'invalid' => ['   ', "\u{200B}"],
            ],
        ];
    }

    /** @return array{label:string,pattern:string,description:string,format:string,min:int,max:int,normalise:string,valid:list<string>,invalid:list<string>} */
    public static function get(string $key): array
    {
        $all = self::all();
        if (!isset($all[$key])) {
            throw new InvalidArgumentException('Unknown validation rule: ' . $key);
        }
        return $all[$key];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function normalise(string $key, string $value): string
    {
        $value = str_replace("\0", '', $value);
        return match (self::get($key)['normalise']) {
            'collapse' => trim((string) preg_replace('/[\h\v]+/u', ' ', $value)),
            'lower' => mb_strtolower(trim($value)),
            'upper' => mb_strtoupper(trim($value)),
            'digits_space' => str_replace(' ', '', trim($value)),
            'trim' => trim($value),
            default => $value,
        };
    }

    /**
     * Check an already-normalised value. Returns null when valid, otherwise
     * a message that does not echo the input back.
     */
    public static function check(string $key, string $value): ?string
    {
        $rule = self::get($key);
        $length = mb_strlen($value);
        if ($length < $rule['min']) {
            return $rule['label'] . ' is too short.';
        }
        if ($length > $rule['max']) {
            return $rule['label'] . ' is too long.';
        }
        if (!self::matches($key, $value)) {
            return $rule['label'] . ' is not in the expected format (' . $rule['format'] . ').';
        }
        if ($key === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'Email address is not valid.';
        }
        if ($key === 'iso_date') {
            [$y, $m, $d] = array_map('intval', explode('-', $value));
            if (!checkdate($m, $d, $y)) {
                return 'That date does not exist.';
            }
        }
        return null;
    }

    /** Raw regex test, used by the QA lab to report match / no match. */
    public static function matches(string $key, string $value): bool
    {
        $pattern = '/' . self::get($key)['pattern'] . '/u';
        $result = @preg_match($pattern, $value);
        return $result === 1;
    }

    /** Canonical stored form, applied after a successful check. */
    public static function canonical(string $key, string $value): string
    {
        return match ($key) {
            'indian_mobile' => substr((string) preg_replace('/[^0-9]/', '', $value), -10),
            default => $value,
        };
    }

    /**
     * Rule data for the browser: label, pattern source, limits and hints.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function forClient(): array
    {
        $out = [];
        foreach (self::all() as $key => $rule) {
            $out[$key] = [
                'label' => $rule['label'],
                'pattern' => $rule['pattern'],
                'flags' => 'u',
                'min' => $rule['min'],
                'max' => $rule['max'],
                'normalise' => $rule['normalise'],
                'format' => $rule['format'],
            ];
        }
        return $out;
    }
}

<?php

declare(strict_types=1);

namespace Vitaliq\Support;

/**
 * Interface translations (feature 38): English, Hindi and Marathi. Keys are
 * the English strings, so an untranslated string shows in English rather
 * than as a key. Catalogues live in lang/{hi,mr}.php.
 */
final class Lang
{
    public const LOCALES = ['en' => 'English', 'hi' => 'हिन्दी', 'mr' => 'मराठी'];

    private static string $locale = 'en';
    /** @var array<string,array<string,string>> */
    private static array $catalogues = [];

    public static function set(string $locale): void
    {
        self::$locale = isset(self::LOCALES[$locale]) ? $locale : 'en';
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @param array<string,string|int> $params replaces {name} placeholders */
    public static function get(string $text, array $params = []): string
    {
        $out = $text;
        if (self::$locale !== 'en') {
            self::$catalogues[self::$locale] ??= self::load(self::$locale);
            $out = self::$catalogues[self::$locale][$text] ?? $text;
        }
        foreach ($params as $k => $v) {
            $out = str_replace('{' . $k . '}', (string) $v, $out);
        }
        return $out;
    }

    /** @return array<string,string> */
    private static function load(string $locale): array
    {
        $file = VQ_ROOT . '/lang/' . $locale . '.php';
        $data = is_file($file) ? require $file : [];
        return is_array($data) ? $data : [];
    }

    /** Pick a locale from an Accept-Language header, for visitors who have not chosen one. */
    public static function negotiate(string $header): string
    {
        foreach (explode(',', strtolower($header)) as $part) {
            $code = substr(trim(explode(';', $part)[0]), 0, 2);
            if (isset(self::LOCALES[$code])) {
                return $code;
            }
        }
        return 'en';
    }
}

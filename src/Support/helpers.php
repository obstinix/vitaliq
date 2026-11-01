<?php

declare(strict_types=1);

use Vitaliq\Core\App;
use Vitaliq\Support\Labels;

/** Escape for HTML text and attribute contexts. */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function app(): App
{
    return App::instance();
}

/** @param array<string,scalar|null> $query */
function url(string $path = '/', array $query = []): string
{
    $base = App::hasInstance() ? (string) app()->config['base_path'] : '';
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return $base . ($path === '/' && $base !== '' ? '/' : $path) . ($query === [] ? '' : '?' . http_build_query($query));
}

function asset(string $path): string
{
    $file = VQ_ROOT . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '0';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function csrf_token(): string
{
    return app()->csrf()->token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function fmt_date(?string $value, string $format = 'j M Y'): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts === false ? '—' : date($format, $ts);
}

function fmt_datetime(?string $value): string
{
    return fmt_date($value, 'j M Y, g:i a');
}

/** ISO-8601 value for <time datetime="">. */
function iso_datetime(?string $value): string
{
    $ts = $value ? strtotime($value) : false;
    return $ts === false ? '' : date('c', $ts);
}

function fmt_number(int|float|string|null $value): string
{
    return number_format((float) ($value ?? 0));
}

function status_label(?string $status): string
{
    return Labels::get(Labels::STATUSES, $status);
}

/** @param array<string,mixed> $old */
function old(array $old, string $key, string $default = ''): string
{
    $value = $old[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

/**
 * Inline field error. Pair with aria-describedby="err-{field}" on the input.
 *
 * @param array<string,string> $errors
 */
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($field) . '">' . e($errors[$field]) . '</p>';
}

/** @param array<string,string> $errors */
function aria_invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . e($field) . '"' : '';
}

function selected(string $a, string $b): string
{
    return $a === $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/**
 * Embed data for JavaScript as an inert JSON block. The HEX flags escape
 * <, >, & and quotes so the payload cannot close the element.
 */
function json_script(string $id, mixed $data): string
{
    $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    return '<script type="application/json" id="' . e($id) . '">' . $json . '</script>';
}

/**
 * Quote a test input so invisible characters are visible: tabs, newlines,
 * non-breaking and zero-width spaces and other control/format characters
 * are shown as escapes, e.g. "a\u{200B}b".
 */
function show_input(?string $value): string
{
    $value ??= '';
    $out = preg_replace_callback('/[\p{Cc}\p{Cf}\p{Z}]/u', static function (array $m): string {
        return match ($m[0]) {
            ' ' => ' ',
            "\t" => '\t',
            "\n" => '\n',
            "\r" => '\r',
            default => sprintf('\u{%04X}', mb_ord($m[0], 'UTF-8')),
        };
    }, str_replace(['\\', '"'], ['\\\\', '\"'], $value));
    return '"' . ($out ?? '') . '"';
}

/** Percentage string, or an em dash when the denominator is zero. */
function pct(int|float $part, int|float $whole, int $decimals = 0): string
{
    return $whole > 0 ? number_format($part / $whole * 100, $decimals) . '%' : '—';
}

/** Mask all but the last four digits of a mobile number for display. */
function mask_mobile(?string $mobile): string
{
    if ($mobile === null || strlen($mobile) < 4) {
        return '—';
    }
    return str_repeat('•', max(0, strlen($mobile) - 4)) . substr($mobile, -4);
}

function format_mobile(?string $mobile): string
{
    if ($mobile === null || strlen($mobile) !== 10) {
        return (string) $mobile;
    }
    return '+91 ' . substr($mobile, 0, 5) . ' ' . substr($mobile, 5);
}

/**
 * Hidden field naming the family profile a patient form acts on.
 *
 * @param array<string,mixed> $profile
 */
function profile_field(array $profile): string
{
    return '<input type="hidden" name="profile_id" value="' . (int) $profile['id'] . '">';
}

/**
 * Query for links that stay on the same family profile.
 *
 * @param array<string,mixed> $profile
 * @return array<string,int>
 */
function profile_query(array $profile): array
{
    return ($profile['rel'] ?? 'self') === 'self' ? [] : ['for' => (int) $profile['id']];
}

/**
 * Translate interface text into the current language (feature 38).
 *
 * @param array<string,string|int> $params
 */
function t(string $text, array $params = []): string
{
    return \Vitaliq\Support\Lang::get($text, $params);
}

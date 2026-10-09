<?php

declare(strict_types=1);

namespace Vitaliq\Validation;

use DateTimeImmutable;

/**
 * Collects normalised values and per-field error messages for one form.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $input */
    public function __construct(private readonly array $input)
    {
    }

    private function raw(string $field): string
    {
        $value = $this->input[$field] ?? '';
        return is_scalar($value) ? (string) $value : '';
    }

    private static function isBlank(string $value): bool
    {
        return !Rules::matches('required', $value);
    }

    /** Validate against a registered rule. Returns the canonical value or null. */
    public function rule(string $field, string $rule, bool $required = true, ?string $label = null): ?string
    {
        $label ??= Rules::get($rule)['label'];
        $raw = $this->raw($field);
        if (self::isBlank($raw)) {
            if ($required) {
                $this->errors[$field] = $label . ' is required.';
            }
            return null;
        }
        $value = Rules::normalise($rule, $raw);
        $error = Rules::check($rule, $value);
        if ($error !== null) {
            $this->errors[$field] = $label === Rules::get($rule)['label'] ? $error : str_replace(Rules::get($rule)['label'], $label, $error);
            return null;
        }
        return Rules::canonical($rule, $value);
    }

    /** Free text: trimmed, control characters removed, length-checked. */
    public function text(string $field, string $label, int $max, bool $required = true, int $min = 0): ?string
    {
        $raw = $this->raw($field);
        // Drop control characters (keeping newlines and tabs). Format characters
        // such as ZWJ/ZWNJ are kept because Indic scripts depend on them.
        $value = trim((string) preg_replace('/[^\P{Cc}\n\t]/u', '', $raw));
        if (self::isBlank($value)) {
            if ($required) {
                $this->errors[$field] = $label . ' is required.';
            }
            return null;
        }
        $length = mb_strlen($value);
        if ($length < $min) {
            $this->errors[$field] = $label . " must be at least {$min} characters.";
            return null;
        }
        if ($length > $max) {
            $this->errors[$field] = $label . " must be {$max} characters or fewer.";
            return null;
        }
        return $value;
    }

    /** @param array<string,string> $allowed whitelist (keys are valid values) */
    public function choice(string $field, array $allowed, string $label, bool $required = true): ?string
    {
        $value = trim($this->raw($field));
        if ($value === '') {
            if ($required) {
                $this->errors[$field] = 'Choose ' . lcfirst($label) . '.';
            }
            return null;
        }
        if (!array_key_exists($value, $allowed)) {
            $this->errors[$field] = 'Choose a valid option for ' . lcfirst($label) . '.';
            return null;
        }
        return $value;
    }

    /**
     * @param array<string,string> $allowed
     * @return list<string>
     */
    public function choices(string $field, array $allowed, string $label): array
    {
        $values = $this->input[$field] ?? [];
        if (!is_array($values)) {
            $this->errors[$field] = 'Choose valid options for ' . lcfirst($label) . '.';
            return [];
        }
        $clean = [];
        foreach ($values as $v) {
            if (!is_string($v) || !array_key_exists($v, $allowed)) {
                $this->errors[$field] = 'Choose valid options for ' . lcfirst($label) . '.';
                return [];
            }
            $clean[$v] = $v;
        }
        return array_values($clean);
    }

    public function date(string $field, string $label, ?DateTimeImmutable $min = null, ?DateTimeImmutable $max = null, bool $required = true): ?string
    {
        $value = $this->rule($field, 'iso_date', $required, $label);
        if ($value === null) {
            return null;
        }
        $date = new DateTimeImmutable($value);
        if ($min !== null && $date < $min->setTime(0, 0)) {
            $this->errors[$field] = $label . ' cannot be before ' . $min->format('j M Y') . '.';
            return null;
        }
        if ($max !== null && $date > $max->setTime(0, 0)) {
            $this->errors[$field] = $label . ' cannot be after ' . $max->format('j M Y') . '.';
            return null;
        }
        return $value;
    }

    public function accepted(string $field, string $message): bool
    {
        $ok = in_array($this->raw($field), ['1', 'on', 'yes'], true);
        if (!$ok) {
            $this->errors[$field] = $message;
        }
        return $ok;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}

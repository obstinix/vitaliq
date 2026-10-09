<?php

declare(strict_types=1);

namespace Vitaliq\Core;

/**
 * Session facade over an array. The web front controller binds it to
 * $_SESSION via NativeSession; tests use a plain array.
 */
class Session
{
    /** @var array<string,mixed> */
    protected array $data;
    public int $regenerations = 0;

    /** @param array<string,mixed> $storage */
    public function __construct(array &$storage)
    {
        $this->data = &$storage;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    /** Value available on the next request only. */
    public function flash(string $key, mixed $value): void
    {
        $this->data['_flash_new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->data['_flash_old'][$key] ?? $this->data['_flash_new'][$key] ?? $default;
    }

    public function ageFlash(): void
    {
        $this->data['_flash_old'] = $this->data['_flash_new'] ?? [];
        $this->data['_flash_new'] = [];
    }

    /** New session identifier, same data. Called after login and privilege changes. */
    public function regenerate(): void
    {
        $this->regenerations++;
    }

    /** Destroy all session data and issue a new identifier. */
    public function invalidate(): void
    {
        foreach (array_keys($this->data) as $key) {
            unset($this->data[$key]);
        }
        $this->regenerate();
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->data;
    }
}

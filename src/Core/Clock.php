<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use DateTimeImmutable;

/**
 * Single source of "now". Tests freeze or advance it to check expiry rules
 * (password reset tokens, rate-limit windows) without sleeping.
 */
class Clock
{
    private ?DateTimeImmutable $frozen = null;

    public function now(): DateTimeImmutable
    {
        return $this->frozen ?? new DateTimeImmutable('now');
    }

    public function nowSql(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    public function freeze(DateTimeImmutable $at): void
    {
        $this->frozen = $at;
    }

    public function advance(string $modifier): void
    {
        $this->frozen = $this->now()->modify($modifier);
    }
}

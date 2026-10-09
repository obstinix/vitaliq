<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use RuntimeException;

/** Carries per-field messages from a service back to the form. */
final class ValidationFailed extends RuntimeException
{
    /** @param array<string,string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed: ' . implode(', ', array_keys($errors)));
    }
}

<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be understood.',
            403 => 'You do not have permission to view this page.',
            404 => 'We could not find that page.',
            405 => 'That action is not allowed here.',
            419 => 'Your form expired. Please go back, refresh the page and try again.',
            429 => 'Too many attempts. Please wait a few minutes and try again.',
            503 => 'Vitaliq is temporarily unavailable. Please try again shortly.',
            default => 'Something went wrong on our side.',
        };
    }
}

<?php

declare(strict_types=1);

namespace Vitaliq\Core;

use RuntimeException;

/** Raised when the database cannot be reached; rendered as a 503 page. */
final class DatabaseUnavailable extends RuntimeException
{
}

<?php

declare(strict_types=1);

namespace Vitaliq\Services;

use RuntimeException;

/** A business rule refused the operation (invalid transition, not owner, etc.). */
final class WorkflowException extends RuntimeException
{
}

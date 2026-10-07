<?php

declare(strict_types=1);

namespace App\Domain\Execution\Exceptions;

use RuntimeException;

final class ConcurrentExecutionException extends RuntimeException
{
    public function __construct(
        string $message = 'The Execution was modified concurrently; reload it before saving.',
    ) {
        parent::__construct($message);
    }
}

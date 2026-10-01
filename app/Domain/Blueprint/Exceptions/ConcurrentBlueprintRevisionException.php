<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Exceptions;

use RuntimeException;

final class ConcurrentBlueprintRevisionException extends RuntimeException
{
    public function __construct(
        string $message = 'The Blueprint was modified concurrently; reload it before adding a new Revision.',
    ) {
        parent::__construct($message);
    }
}

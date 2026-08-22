<?php

declare(strict_types=1);

namespace App\Application\Execution\Engine;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;

interface ExecutionEngineContract
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     */
    public function execute(
        Blueprint $blueprint,
        RevisionId $revisionId,
        array $input,
        array $context = [],
    ): Execution;
}
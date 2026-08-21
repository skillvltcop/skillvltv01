<?php

declare(strict_types=1);

namespace App\Application\Execution\Commands;

use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Repositories\ExecutionRepository;

final class CreateExecution
{
    public function __construct(
        private ExecutionRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     */
    public function handle(
        string $blueprintId,
        string $revisionId,
        array $input,
        array $context = [],
    ): Execution {
        $execution = Execution::create(
            blueprintId: new BlueprintId($blueprintId),
            revisionId: new RevisionId($revisionId),
            input: $input,
            context: $context,
        );

        $this->repository->save($execution);

        return $execution;
    }
}
<?php

declare(strict_types=1);

namespace App\Application\Execution\Commands;

use App\Application\Execution\Engine\ExecutionEngineContract;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Repositories\ExecutionRepository;

final class ExecuteBlueprint
{
    public function __construct(
        private BlueprintRepository $blueprintRepository,
        private ExecutionEngineContract $engine,
        private ExecutionRepository $executionRepository,
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
        $blueprint = $this->blueprintRepository->find(
            new BlueprintId($blueprintId),
        );

        if ($blueprint === null) {
            throw new \DomainException(
                'Blueprint not found.'
            );
        }

        $execution = $this->engine->execute(
            blueprint: $blueprint,
            revisionId: new RevisionId($revisionId),
            input: $input,
            context: $context,
        );

        $this->executionRepository->save($execution);

        return $execution;
    }
}
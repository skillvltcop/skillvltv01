<?php

declare(strict_types=1);

namespace App\Application\Execution\Engine;

use App\Application\Execution\Runtime\Contracts\BehaviorRunner;
use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Execution\Entities\Execution;

final class ExecutionEngine
{
    public function __construct(
        private BehaviorRunner $runner,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     */
    public function execute(
        Blueprint $blueprint,
        RevisionId $revisionId,
        array $input,
        array $context = [],
    ): Execution {
        $revision = $blueprint->revision($revisionId);

        if ($revision === null) {
            throw new \DomainException(
                'Revision does not belong to the Blueprint.'
            );
        }

        if (! $revision->isFrozen()) {
            throw new \DomainException(
                'Only a frozen revision can be executed.'
            );
        }

        if (
            $blueprint->currentRevisionId() === null
            || (string) $blueprint->currentRevisionId() !== (string) $revisionId
        ) {
            throw new \DomainException(
                'Only the current revision can be executed.'
            );
        }

        if ($blueprint->lifecycleStatus()->value !== 'active') {
            throw new \DomainException(
                'Only an active Blueprint can be executed.'
            );
        }

        $execution = Execution::create(
            blueprintId: $blueprint->id(),
            revisionId: $revisionId,
            input: $input,
            context: $context,
        );

        $execution->start();

        $output = $this->runner->run(
            logic: $revision->logic(),
            input: $input,
            context: $context,
        );

        $execution->complete($output);

        return $execution;
    }
}
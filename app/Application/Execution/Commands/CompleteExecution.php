<?php

declare(strict_types=1);

namespace App\Application\Execution\Commands;

use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Repositories\ExecutionRepository;
use App\Domain\Execution\ValueObjects\ExecutionId;

final class CompleteExecution
{
    public function __construct(
        private ExecutionRepository $repository,
    ) {
    }

    /**
     * @param array<string, mixed> $output
     */
    public function handle(
        string $executionId,
        array $output,
    ): Execution {
        $execution = $this->repository->find(
            new ExecutionId($executionId),
        );

        if ($execution === null) {
            throw new \DomainException(
                'Execution not found.'
            );
        }

        $execution->complete($output);

        $this->repository->save($execution);

        return $execution;
    }
}
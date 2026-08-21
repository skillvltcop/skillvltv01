<?php

declare(strict_types=1);

namespace App\Application\Execution\Commands;

use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\Repositories\ExecutionRepository;
use App\Domain\Execution\ValueObjects\ExecutionId;

final class FailExecution
{
    public function __construct(
        private ExecutionRepository $repository,
    ) {
    }

    public function handle(
        string $executionId,
        string $error,
    ): Execution {
        $execution = $this->repository->find(
            new ExecutionId($executionId),
        );

        if ($execution === null) {
            throw new \DomainException(
                'Execution not found.'
            );
        }

        $execution->fail($error);

        $this->repository->save($execution);

        return $execution;
    }
}
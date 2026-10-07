<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Execution\Entities\Execution as DomainExecution;
use App\Domain\Execution\Enums\ExecutionStatus;
use App\Domain\Execution\Exceptions\ConcurrentExecutionException;
use App\Domain\Execution\Repositories\ExecutionRepository;
use App\Domain\Execution\ValueObjects\ExecutionId;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Models\Execution as ExecutionModel;

final class EloquentExecutionRepository implements ExecutionRepository
{
    public function find(ExecutionId $id): ?DomainExecution
    {
        $model = ExecutionModel::query()->find((string) $id);

        if ($model === null) {
            return null;
        }

        return DomainExecution::reconstitute(
            id: new ExecutionId((string) $model->id),
            blueprintId: new BlueprintId((string) $model->blueprint_id),
            revisionId: new RevisionId((string) $model->revision_id),
            input: $model->input ?? [],
            context: $model->context ?? [],
            status: $model->status,
            output: $model->output,
            error: $model->error,
        );
    }

    public function save(DomainExecution $execution): void
    {
        $model = ExecutionModel::query()->find(
            (string) $execution->id(),
        );

        if ($model !== null) {
            $this->assertStatusIsCurrent($execution, $model);
        }

        ExecutionModel::query()->updateOrCreate(
            [
                'id' => (string) $execution->id(),
            ],
            [
                'blueprint_id' => (string) $execution->blueprintId(),
                'revision_id' => (string) $execution->revisionId(),
                'input' => $execution->input(),
                'context' => $execution->context(),
                'status' => $execution->status(),
                'output' => $execution->output(),
                'error' => $execution->error(),
            ],
        );
    }

    private function assertStatusIsCurrent(
        DomainExecution $execution,
        ExecutionModel $model,
    ): void {
        $persisted = $model->status instanceof ExecutionStatus
            ? $model->status
            : ExecutionStatus::from((string) $model->status);

        $candidate = $execution->status();

        $rank = static fn (ExecutionStatus $status): int => match ($status) {
            ExecutionStatus::PENDING => 0,
            ExecutionStatus::RUNNING => 1,
            ExecutionStatus::COMPLETED,
            ExecutionStatus::FAILED => 2,
        };

        if ($rank($candidate) < $rank($persisted)) {
            throw new ConcurrentExecutionException();
        }

        if (
            $rank($candidate) === $rank($persisted)
            && $candidate !== $persisted
        ) {
            throw new ConcurrentExecutionException();
        }
    }

}
}
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Execution\Entities\Execution as DomainExecution;
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
}
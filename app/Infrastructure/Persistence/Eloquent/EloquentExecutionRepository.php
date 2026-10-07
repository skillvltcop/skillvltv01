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
use Illuminate\Support\Facades\DB;

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
            ownerId: $model->owner_id !== null
                ? (string) $model->owner_id
                : null,
            input: $model->input ?? [],
            context: $model->context ?? [],
            status: $model->status,
            output: $model->output,
            error: $model->error,
        );
    }

    public function save(DomainExecution $execution): void
    {
        DB::transaction(function () use ($execution): void {
            $model = ExecutionModel::query()
                ->lockForUpdate()
                ->find((string) $execution->id());

            if ($model !== null) {
                $this->assertIdentityIsCurrent($execution, $model);
                $this->assertOwnerIsCurrent($execution, $model);
                $this->assertInputAndContextAreCurrent($execution, $model);
                $this->assertStatusIsCurrent($execution, $model);

                $model->fill([
                    'blueprint_id' => (string) $execution->blueprintId(),
                    'revision_id' => (string) $execution->revisionId(),
                    'owner_id' => $execution->ownerId(),
                    'input' => $execution->input(),
                    'context' => $execution->context(),
                    'status' => $execution->status(),
                    'output' => $execution->output(),
                    'error' => $execution->error(),
                ]);

                $model->save();

                return;
            }

            ExecutionModel::query()->create([
                'id' => (string) $execution->id(),
                'blueprint_id' => (string) $execution->blueprintId(),
                'revision_id' => (string) $execution->revisionId(),
                'owner_id' => $execution->ownerId(),
                'input' => $execution->input(),
                'context' => $execution->context(),
                'status' => $execution->status(),
                'output' => $execution->output(),
                'error' => $execution->error(),
            ]);
        });
    }

    private function assertIdentityIsCurrent(
        DomainExecution $execution,
        ExecutionModel $model,
    ): void {
        if (
            (string) $execution->blueprintId() !== (string) $model->blueprint_id
            || (string) $execution->revisionId() !== (string) $model->revision_id
        ) {
            throw new ConcurrentExecutionException();
        }
    }

    private function assertOwnerIsCurrent(
        DomainExecution $execution,
        ExecutionModel $model,
    ): void {
        if ($execution->ownerId() !== (
            $model->owner_id === null
                ? null
                : (string) $model->owner_id
        )) {
            throw new ConcurrentExecutionException();
        }
    }

    private function assertInputAndContextAreCurrent(
        DomainExecution $execution,
        ExecutionModel $model,
    ): void {
        if (
            $execution->input() !== ($model->input ?? [])
            || $execution->context() !== ($model->context ?? [])
        ) {
            throw new ConcurrentExecutionException();
        }
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

        if (
            $rank($persisted) === 2
            && (
                $execution->output() !== $model->output
                || $execution->error() !== $model->error
            )
        ) {
            throw new ConcurrentExecutionException();
        }
    }
}

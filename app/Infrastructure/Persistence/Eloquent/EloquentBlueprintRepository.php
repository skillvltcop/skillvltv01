<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\Entities\BlueprintRevision as DomainBlueprintRevision;
use App\Domain\Blueprint\Exceptions\ConcurrentBlueprintRevisionException;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Models\Blueprint as BlueprintModel;
use Illuminate\Support\Facades\DB;

final class EloquentBlueprintRepository implements BlueprintRepository
{
    public function find(BlueprintId $id): ?Blueprint
    {
        $model = BlueprintModel::query()
            ->with([
                'revisions.parentRevision',
                'currentRevision',
            ])
            ->find((string) $id);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByCanonicalName(string $canonicalName): ?Blueprint
    {
        $model = BlueprintModel::query()
            ->with([
                'revisions.parentRevision',
                'currentRevision',
            ])
            ->where('canonical_name', $canonicalName)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function save(Blueprint $blueprint): void
    {
        DB::transaction(function () use ($blueprint): void {
            $ownership = $blueprint->ownership();

            $model = BlueprintModel::query()
                ->whereKey((string) $blueprint->id())
                ->lockForUpdate()
                ->first();

            if ($model === null) {
                $model = BlueprintModel::query()->create([
                    'id' => (string) $blueprint->id(),
                    'canonical_name' => (string) $blueprint->canonicalName(),
                    'namespace' => (string) $blueprint->namespace(),
                    'owner_type' => $ownership['type'],
                    'owner_id' => $ownership['id'],
                    'lifecycle_status' => $blueprint->lifecycleStatus()->value,
                    'current_revision_id' => null,
                ]);
            } else {
                $this->assertLifecycleStatusIsCurrent($blueprint, $model);

                $this->assertRevisionHistoryIsCurrent(
                    $blueprint,
                    $model,
                );

                $model->update([
                    'canonical_name' => (string) $blueprint->canonicalName(),
                    'namespace' => (string) $blueprint->namespace(),
                    'owner_type' => $ownership['type'],
                    'owner_id' => $ownership['id'],
                    'lifecycle_status' => $blueprint->lifecycleStatus()->value,
                    'current_revision_id' => null,
                ]);
            }

            $metadata = $blueprint->metadata();

            $model->metadata()->updateOrCreate(
                [
                    'blueprint_id' => $model->id,
                ],
                [
                    'taxonomy' => $metadata['taxonomy'] ?? [],
                    'documentation' => $metadata['documentation'] ?? [],
                    'discovery' => $metadata['discovery'] ?? null,
                    'lifecycle_metadata' => $metadata['lifecycle_metadata'] ?? [],
                    'payload' => $metadata,
                ],
            );

            foreach ($blueprint->revisions() as $revision) {
                $model->revisions()->updateOrCreate(
                    [
                        'id' => (string) $revision->id(),
                    ],
                    [
                        'blueprint_id' => $model->id,
                        'revision_number' => (string) $revision->number(),
                        'parent_revision_id' => $revision->parentRevisionId()
                            ? (string) $revision->parentRevisionId()
                            : null,
                        'behavior_digest' => (string) $revision->behaviorDigest(),
                        'contracts' => $revision->contracts(),
                        'logic' => $revision->logic(),
                        'outputs' => $revision->outputs(),
                        'policies' => $revision->policies(),
                        'frozen' => $revision->isFrozen(),
                    ],
                );
            }

            if ($blueprint->currentRevisionId() !== null) {
                $model->update([
                    'current_revision_id' => (string) $blueprint->currentRevisionId(),
                ]);
            }
        });
    }

    private function assertRevisionHistoryIsCurrent(
        Blueprint $blueprint,
        BlueprintModel $model,
    ): void {
        $this->assertCurrentRevisionIsCurrent($blueprint, $model);

        $persistedRevisions = $model->revisions()->get();

        $persistedRevisionIds = $persistedRevisions
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $newRevisions = array_values(
            array_filter(
                $blueprint->revisions(),
                fn ($revision): bool =>
                    ! in_array(
                        (string) $revision->id(),
                        $persistedRevisionIds,
                        true,
                    ),
            ),
        );

        if ($newRevisions === []) {
            return;
        }

        $persistedLatestRevision = null;
        $persistedLatestNumber = null;

        foreach ($persistedRevisions as $revision) {
            $number = new RevisionNumber(
                (string) $revision->revision_number,
            );

            if (
                $persistedLatestNumber === null
                || $number->isGreaterThan($persistedLatestNumber)
            ) {
                $persistedLatestRevision = $revision;
                $persistedLatestNumber = $number;
            }
        }

        usort(
            $newRevisions,
            function ($left, $right): int {
                if ($left->number()->equals($right->number())) {
                    return 0;
                }

                return $left->number()->isGreaterThan($right->number())
                    ? 1
                    : -1;
            },
        );

        $firstNewRevision = $newRevisions[0];
        $expectedParentId = $persistedLatestRevision?->id;
        $actualParentId = $firstNewRevision->parentRevisionId();

        if (
            (string) ($actualParentId ?? '')
            !== (string) ($expectedParentId ?? '')
        ) {
            throw new ConcurrentBlueprintRevisionException();
        }
    }

    private function assertLifecycleStatusIsCurrent(
        Blueprint $blueprint,
        BlueprintModel $model,
    ): void {
        $persistedStatus = $model->lifecycle_status instanceof \App\Domain\Blueprint\Enums\LifecycleStatus
            ? $model->lifecycle_status
            : \App\Domain\Blueprint\Enums\LifecycleStatus::from((string) $model->lifecycle_status);

        $rank = static fn (\App\Domain\Blueprint\Enums\LifecycleStatus $status): int => match ($status) {
            \App\Domain\Blueprint\Enums\LifecycleStatus::DRAFT => 0,
            \App\Domain\Blueprint\Enums\LifecycleStatus::ACTIVE => 1,
            \App\Domain\Blueprint\Enums\LifecycleStatus::DEPRECATED => 2,
            \App\Domain\Blueprint\Enums\LifecycleStatus::SUNSET => 3,
        };

        if ($rank($blueprint->lifecycleStatus()) < $rank($persistedStatus)) {
            throw new ConcurrentBlueprintRevisionException(
                'The Blueprint was modified concurrently; reload it before saving.',
            );
        }
    }

    private function assertCurrentRevisionIsCurrent(
        Blueprint $blueprint,
        BlueprintModel $model,
    ): void {
        $persistedCurrentRevisionId = $model->current_revision_id;
        $currentRevision = $blueprint->currentRevision();

        if ($persistedCurrentRevisionId === null) {
            return;
        }

        if ($currentRevision === null) {
            throw new ConcurrentBlueprintRevisionException();
        }

        if ((string) $currentRevision->id() === (string) $persistedCurrentRevisionId) {
            return;
        }

        $persistedCurrentRevision = $model->revisions()
            ->whereKey($persistedCurrentRevisionId)
            ->first();

        if ($persistedCurrentRevision === null) {
            throw new ConcurrentBlueprintRevisionException();
        }

        $persistedNumber = new RevisionNumber(
            (string) $persistedCurrentRevision->revision_number,
        );

        if (! $currentRevision->number()->isGreaterThan($persistedNumber)) {
            throw new ConcurrentBlueprintRevisionException();
        }
    }

    private function toDomain(BlueprintModel $model): Blueprint
    {
        $metadata = $model->metadata?->payload;

        if ($metadata === null) {
            $metadata = $model->metadata
                ? [
                    'taxonomy' => $model->metadata->taxonomy,
                    'documentation' => $model->metadata->documentation,
                    'discovery' => $model->metadata->discovery,
                    'lifecycle_metadata' => $model->metadata->lifecycle_metadata,
                ]
                : [];
        }

        return Blueprint::reconstitute(
            id: new BlueprintId((string) $model->id),
            canonicalName: new CanonicalName($model->canonical_name),
            namespace: new BlueprintNamespace($model->namespace),
            ownership: [
                'type' => $model->owner_type,
                'id' => $model->owner_id,
            ],
            metadata: $metadata,
            lifecycleStatus: $model->lifecycle_status,
            revisions: $model->revisions
                ->mapWithKeys(
                    fn ($revision) => [
                        (string) $revision->id => $this->revisionToDomain($revision),
                    ]
                )
                ->all(),
            currentRevisionId: $model->current_revision_id
                ? new RevisionId((string) $model->current_revision_id)
                : null,
        );
    }

    private function revisionToDomain(
        \App\Models\BlueprintRevision $model,
    ): DomainBlueprintRevision {
        return DomainBlueprintRevision::reconstitute(
            id: new RevisionId((string) $model->id),
            number: new RevisionNumber((string) $model->revision_number),
            parentRevisionId: $model->parent_revision_id
                ? new RevisionId((string) $model->parent_revision_id)
                : null,
            behaviorDigest: new BehaviorDigest(
                (string) $model->behavior_digest
            ),
            contracts: $model->contracts,
            logic: $model->logic,
            outputs: $model->outputs,
            policies: $model->policies,
            frozen: (bool) $model->frozen,
        );
    }

    public function findOwnedBy(
        string $ownerType,
        string $ownerId,
    ): array {
        return BlueprintModel::query()
            ->with([
                'revisions.parentRevision',
                'currentRevision',
            ])
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->orderBy('created_at')
            ->get()
            ->map(
                fn (BlueprintModel $model): Blueprint =>
                    $this->toDomain($model),
            )
            ->all();
    }

    public function discover(): array
    {
        return BlueprintModel::query()
            ->with([
                'revisions.parentRevision',
                'currentRevision',
            ])
            ->where('owner_type', 'system')
            ->where('owner_id', 'skillvlt')
            ->where('lifecycle_status', 'active')
            ->whereHas(
                'currentRevision',
                static fn ($query) => $query
                    ->where('frozen', true)
                    ->where('policies->visibility', 'public'),
            )
            ->orderBy('created_at')
            ->get()
            ->map(
                fn (BlueprintModel $model): Blueprint =>
                    $this->toDomain($model),
            )
            ->all();
    }
}

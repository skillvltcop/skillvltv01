<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;
use App\Domain\Blueprint\Entities\BlueprintRevision as DomainBlueprintRevision;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;
use App\Models\Blueprint as BlueprintModel;
use RuntimeException;

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

public function save(Blueprint $blueprint): void
{
    $ownership = $blueprint->ownership();

    $model = BlueprintModel::query()->updateOrCreate(
        [
            'id' => (string) $blueprint->id(),
        ],
        [
            'canonical_name' => (string) $blueprint->canonicalName(),
            'namespace' => (string) $blueprint->namespace(),
            'owner_type' => $ownership['type'],
            'owner_id' => $ownership['id'],
            'lifecycle_status' => $blueprint->lifecycleStatus()->value,

            // Important:
            // current_revision_id is set only after revisions are persisted.
            'current_revision_id' => null,
        ],
    );

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

    // Now the referenced revision definitely exists.
    if ($blueprint->currentRevisionId() !== null) {
        $model->update([
            'current_revision_id' => (string) $blueprint->currentRevisionId(),
        ]);
    }
}

    private function toDomain(BlueprintModel $model): Blueprint
    {
        return Blueprint::reconstitute(
            id: new BlueprintId((string) $model->id),
            canonicalName: new CanonicalName($model->canonical_name),
            namespace: new BlueprintNamespace($model->namespace),
            ownership: [
                'type' => $model->owner_type,
                'id' => $model->owner_id,
            ],
            metadata: $model->metadata
                ? [
                    'taxonomy' => $model->metadata->taxonomy,
                    'documentation' => $model->metadata->documentation,
                    'discovery' => $model->metadata->discovery,
                    'lifecycle_metadata' => $model->metadata->lifecycle_metadata,
                ]
                : [],
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
            ->orderBy('created_at')
            ->get()
            ->map(
                fn (BlueprintModel $model): Blueprint =>
                    $this->toDomain($model),
            )
            ->all();
    }
}
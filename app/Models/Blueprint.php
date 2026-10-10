<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Blueprint\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Throwable;

class Blueprint extends Model
{
    protected $table = 'blueprints';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'canonical_name',
        'namespace',
        'owner_type',
        'owner_id',
        'lifecycle_status',
        'current_revision_id',
    ];

    public function delete(): ?bool
    {
        try {
            return DB::transaction(function (): ?bool {
                if ($this->current_revision_id !== null) {
                    $this->newQuery()
                        ->whereKey($this->getKey())
                        ->update(['current_revision_id' => null]);

                    $this->setAttribute('current_revision_id', null);
                }

                // MySQL checks the self-referencing parent_revision_id foreign key
                // while cascading blueprint deletion. Delete revisions leaf-first
                // so child revisions never block deletion of their parents.
                $remainingRevisions = $this->revisions()->get();

                while ($remainingRevisions->isNotEmpty()) {
                    $parentIds = $remainingRevisions
                        ->pluck('parent_revision_id')
                        ->filter()
                        ->map(fn ($id): string => (string) $id)
                        ->all();

                    $leaves = $remainingRevisions->reject(
                        fn (BlueprintRevision $revision): bool => in_array(
                            (string) $revision->id,
                            $parentIds,
                            true,
                        ),
                    );

                    if ($leaves->isEmpty()) {
                        // Leave invalid/cyclic revision graphs to the database
                        // constraints; the transaction will roll back safely.
                        break;
                    }

                    foreach ($leaves as $revision) {
                        $revision->delete();
                    }

                    $remainingRevisions = $this->revisions()->get();
                }

                return parent::delete();
            });
        } catch (Throwable $exception) {
            if ($this->exists) {
                $this->refresh();
            }

            throw $exception;
        }
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(
            BlueprintRevision::class,
            'blueprint_id'
        );
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(
            BlueprintRevision::class,
            'current_revision_id'
        );
    }

    protected function casts(): array
    {
        return [
            'lifecycle_status' => LifecycleStatus::class,
        ];
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(
            BlueprintMetadata::class,
            'blueprint_id',
            'id',
        );
    }
}
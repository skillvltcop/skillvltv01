<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Blueprint\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlueprintRevision extends Model
{
    protected $table = 'blueprint_revisions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'blueprint_id',
        'revision_number',
        'parent_revision_id',
        'behavior_digest',
        'contracts',
        'logic',
        'outputs',
        'policies',
        'frozen',
    ];

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(
            Blueprint::class,
            'blueprint_id'
        );
    }

    public function parentRevision(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_revision_id'
        );
    }

    public function childRevisions(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_revision_id'
        );
    }

    protected function casts(): array
    {
        return [
            'contracts' => 'array',
            'logic' => 'array',
            'outputs' => 'array',
            'policies' => 'array',
            'frozen' => 'boolean',
        ];
    }
}
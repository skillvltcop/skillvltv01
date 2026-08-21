<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintMetadata extends Model
{
    protected $table = 'blueprint_metadata';

    protected $primaryKey = 'blueprint_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'blueprint_id',
        'taxonomy',
        'documentation',
        'discovery',
        'lifecycle_metadata',
    ];

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(
            Blueprint::class,
            'blueprint_id',
            'id',
        );
    }

    protected function casts(): array
    {
        return [
            'taxonomy' => 'array',
            'documentation' => 'array',
            'discovery' => 'array',
            'lifecycle_metadata' => 'array',
        ];
    }
}
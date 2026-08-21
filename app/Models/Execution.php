<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Execution\Enums\ExecutionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

final class Execution extends Model
{
    use HasUlids;

    protected $table = 'executions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'blueprint_id',
        'revision_id',
        'input',
        'context',
        'status',
        'output',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'context' => 'array',
            'status' => ExecutionStatus::class,
            'output' => 'array',
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\Domain\Execution\Enums;

enum ExecutionStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
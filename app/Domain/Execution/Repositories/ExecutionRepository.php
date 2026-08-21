<?php

declare(strict_types=1);

namespace App\Domain\Execution\Repositories;

use App\Domain\Execution\Entities\Execution;
use App\Domain\Execution\ValueObjects\ExecutionId;

interface ExecutionRepository
{
    public function find(ExecutionId $id): ?Execution;

    public function save(Execution $execution): void;
}
<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\Repositories;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\ValueObjects\BlueprintId;

interface BlueprintRepository
{
    public function find(BlueprintId $id): ?Blueprint;

    public function findOwnedBy(
        string $ownerType,
        string $ownerId,
    ): array;

    public function discover(): array;

    public function save(Blueprint $blueprint): void;
}
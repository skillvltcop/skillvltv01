<?php

declare(strict_types=1);

namespace App\Application\Blueprint\Commands;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use RuntimeException;

final class SunsetBlueprint
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function handle(string $blueprintId): Blueprint
    {
        $blueprint = $this->repository->find(
            new BlueprintId($blueprintId)
        );

        if ($blueprint === null) {
            throw new RuntimeException(
                'Blueprint not found.'
            );
        }

        $blueprint->sunset();

        $this->repository->save($blueprint);

        return $blueprint;
    }
}
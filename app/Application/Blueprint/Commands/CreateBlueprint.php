<?php

namespace App\Application\Blueprint\Commands;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintNamespace;
use App\Domain\Blueprint\ValueObjects\CanonicalName;

final class CreateBlueprint
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function handle(
        string $canonicalName,
        string $namespace,
        array $ownership,
        array $metadata = [],
    ): Blueprint {
        $blueprint = Blueprint::create(
            canonicalName: new CanonicalName($canonicalName),
            namespace: new BlueprintNamespace($namespace),
            ownership: $ownership,
            metadata: $metadata,
        );

        $this->repository->save($blueprint);

        return $blueprint;
    }
}
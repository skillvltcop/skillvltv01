<?php

declare(strict_types=1);

namespace App\Application\Blueprint\Commands;

use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

final class AddBlueprintRevision
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function handle(
        string $blueprintId,
        string $number,
        string $behaviorDigest,
        array $contracts,
        array $logic,
        array $outputs,
        array $policies,
    ): BlueprintRevision {
        $blueprint = $this->repository->find(
            new BlueprintId($blueprintId)
        );

        if ($blueprint === null) {
            throw new \RuntimeException(
                'Blueprint not found.'
            );
        }

        $revision = $blueprint->addRevision(
            number: new RevisionNumber($number),
            behaviorDigest: new BehaviorDigest($behaviorDigest),
            contracts: $contracts,
            logic: $logic,
            outputs: $outputs,
            policies: $policies,
        );

        $this->repository->save($blueprint);

        return $revision;
    }
}
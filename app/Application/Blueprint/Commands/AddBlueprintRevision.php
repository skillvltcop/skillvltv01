<?php

declare(strict_types=1);

namespace App\Application\Blueprint\Commands;

use App\Application\Behavior\BehaviorContractValidator;
use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

final class AddBlueprintRevision
{
    public function __construct(
        private BlueprintRepository $repository,
        private BehaviorContractValidator $behaviorContractValidator,
        private \App\Application\Behavior\BehaviorDigestCalculator $behaviorDigestCalculator,
    ) {
    }

    public function handle(
        string $blueprintId,
        string $number,
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

        $this->behaviorContractValidator->validate($logic);

        $behaviorDigest = $this->behaviorDigestCalculator->calculate($logic);

        $revision = $blueprint->addRevision(
            number: new RevisionNumber($number),
            behaviorDigest: $behaviorDigest,
            contracts: $contracts,
            logic: $logic,
            outputs: $outputs,
            policies: $policies,
        );

        $this->repository->save($blueprint);

        return $revision;
    }
}
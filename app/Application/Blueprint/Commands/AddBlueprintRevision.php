<?php

declare(strict_types=1);

namespace App\Application\Blueprint\Commands;

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Execution\InputContractDefinitionValidator;
use App\Application\Behavior\BehaviorDigestCalculator;
use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionNumber;

final class AddBlueprintRevision
{
    public function __construct(
        private BlueprintRepository $repository,
        private BehaviorContractValidator $behaviorContractValidator,
        private BehaviorDigestCalculator $behaviorDigestCalculator = new BehaviorDigestCalculator(),
        private InputContractDefinitionValidator $inputContractDefinitionValidator = new InputContractDefinitionValidator(),
    ) {
    }

    public function handle(
        string $blueprintId,
        string $number,
        array $contracts,
        array $logic,
        array $outputs,
        array $policies,
        ?string $behaviorDigest = null,
    ): BlueprintRevision {
        $blueprint = $this->repository->find(
            new BlueprintId($blueprintId)
        );

        if ($blueprint === null) {
            throw new \RuntimeException(
                'Blueprint not found.'
            );
        }

        $this->inputContractDefinitionValidator->validate($contracts);
        $this->behaviorContractValidator->validate($logic);

        // Kept only for backward compatibility with existing application callers.
        // The supplied value is intentionally ignored; the digest is always derived
        // from the behavior logic to preserve content-derived integrity.
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
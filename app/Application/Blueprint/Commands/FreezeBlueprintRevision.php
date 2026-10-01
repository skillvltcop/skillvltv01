<?php

declare(strict_types=1);

namespace App\Application\Blueprint\Commands;

use App\Domain\Blueprint\Entities\Blueprint;
use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use App\Domain\Blueprint\Enums\LifecycleStatus;

use RuntimeException;

final class FreezeBlueprintRevision
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function handle(
        string $blueprintId,
        string $revisionId,
    ): BlueprintRevision {
        $blueprint = $this->repository->find(
            new BlueprintId($blueprintId)
        );

        if ($blueprint === null) {
            throw new RuntimeException(
                'Blueprint not found.'
            );
        }

        $revision = $blueprint->revision(
            new RevisionId($revisionId)
        );

        if ($revision === null) {
            throw new RuntimeException(
                'Blueprint revision not found.'
            );
        }

        if ($blueprint->lifecycleStatus() === LifecycleStatus::SUNSET) {
            throw new \DomainException(
                'A sunset Blueprint cannot freeze a Revision.'
            );
        }

        $revision->freeze();

        $this->repository->save($blueprint);

        return $revision;
    }
}
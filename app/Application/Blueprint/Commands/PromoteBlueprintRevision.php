<?php

declare(strict_types=1);

namespace App\Application\Blueprint\Commands;

use App\Domain\Blueprint\Entities\BlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use RuntimeException;

final class PromoteBlueprintRevision
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

        $blueprint->promoteRevision(
            new RevisionId($revisionId)
        );

        $this->repository->save($blueprint);

        return $revision;
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use Illuminate\Http\JsonResponse;

final class ShowBlueprintRevisionController
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(
        string $blueprint,
        string $revision,
    ): JsonResponse {
        $blueprintEntity = $this->repository->find(
            new BlueprintId($blueprint),
        );

        if ($blueprintEntity === null) {
            abort(404, 'Blueprint not found.');
        }

        $revisionEntity = $blueprintEntity->revision(
            new RevisionId($revision),
        );

        if ($revisionEntity === null) {
            abort(404, 'Blueprint revision not found.');
        }

        return response()->json([
            'id' => (string) $revisionEntity->id(),
            'blueprint_id' => (string) $blueprintEntity->id(),
            'number' => (string) $revisionEntity->number(),
            'parent_revision_id' => $revisionEntity->parentRevisionId()
                ? (string) $revisionEntity->parentRevisionId()
                : null,
            'behavior_digest' => (string) $revisionEntity->behaviorDigest(),
            'contracts' => $revisionEntity->contracts(),
            'logic' => $revisionEntity->logic(),
            'outputs' => $revisionEntity->outputs(),
            'policies' => $revisionEntity->policies(),
            'frozen' => $revisionEntity->isFrozen(),
        ]);
    }
}
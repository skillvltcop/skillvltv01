<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintAuthorizationPolicy;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowBlueprintRevisionController
{
    public function __construct(
        private BlueprintRepository $repository,
        private BlueprintAuthorizationPolicy $authorization,
    ) {
    }

    public function __invoke(
        Request $request,
        string $blueprint,
        string $revision,
    ): JsonResponse {
        $entity = $this->repository->find(new BlueprintId($blueprint));

        if ($entity === null) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        $revisionEntity = $entity->revision(new RevisionId($revision));

        if ($revisionEntity === null) {
            return response()->json(['message' => 'Blueprint revision not found.'], 404);
        }

        if (! $this->authorization->canReadRevisionDetails($entity, $revisionEntity, $request->user())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'id' => (string) $revisionEntity->id(),
            'blueprint_id' => (string) $entity->id(),
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

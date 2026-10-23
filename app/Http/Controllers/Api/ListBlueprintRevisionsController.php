<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintAuthorizationPolicy;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListBlueprintRevisionsController
{
    public function __construct(
        private BlueprintRepository $repository,
        private BlueprintAuthorizationPolicy $authorization,
    ) {
    }

    public function __invoke(Request $request, string $blueprint): JsonResponse
    {
        $entity = $this->repository->find(new BlueprintId($blueprint));

        if ($entity === null) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        if (! $this->authorization->canReadBlueprintDetails($entity, $request->user())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $blueprintId = (string) $entity->id();

        return response()->json([
            'data' => array_values(array_map(
                static fn ($revision): array => [
                    'id' => (string) $revision->id(),
                    'blueprint_id' => $blueprintId,
                    'number' => (string) $revision->number(),
                    'parent_revision_id' => $revision->parentRevisionId()
                        ? (string) $revision->parentRevisionId()
                        : null,
                    'behavior_digest' => (string) $revision->behaviorDigest(),
                    'contracts' => $revision->contracts(),
                    'logic' => $revision->logic(),
                    'outputs' => $revision->outputs(),
                    'policies' => $revision->policies(),
                    'frozen' => $revision->isFrozen(),
                ],
                $entity->revisions(),
            )),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintAuthorizationPolicy;
use App\Authorization\BlueprintPublicProjection;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowBlueprintController
{
    public function __construct(
        private BlueprintRepository $repository,
        private BlueprintAuthorizationPolicy $authorization,
        private BlueprintPublicProjection $projection,
    ) {
    }

    public function __invoke(Request $request, string $blueprint): JsonResponse
    {
        $entity = $this->repository->find(new BlueprintId($blueprint));

        if ($entity === null) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        if ($this->authorization->canReadBlueprintDetails($entity, $request->user())) {
            return response()->json([
                'id' => (string) $entity->id(),
                'canonical_name' => (string) $entity->canonicalName(),
                'namespace' => (string) $entity->namespace(),
                'ownership' => $entity->ownership(),
                'metadata' => $entity->metadata(),
                'lifecycle_status' => $entity->lifecycleStatus()->value,
                'current_revision_id' => $entity->currentRevisionId()
                    ? (string) $entity->currentRevisionId()
                    : null,
                'revisions' => array_values(array_map(
                    static fn ($revision): array => [
                        'id' => (string) $revision->id(),
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

        if (! $this->authorization->canReadPublicMetadata($entity, $request->user())) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($this->projection->from($entity));
    }
}

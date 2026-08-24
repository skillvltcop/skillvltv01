<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Http\JsonResponse;

final class ShowBlueprintController
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(string $blueprint): JsonResponse
    {
        $blueprintEntity = $this->repository->find(
            new BlueprintId($blueprint),
        );

        if ($blueprintEntity === null) {
            return response()->json([
                'message' => 'Blueprint not found.',
            ], 404);
        }

        return response()->json([
            'id' => (string) $blueprintEntity->id(),
            'canonical_name' => (string) $blueprintEntity->canonicalName(),
            'namespace' => (string) $blueprintEntity->namespace(),
            'ownership' => $blueprintEntity->ownership(),
            'metadata' => $blueprintEntity->metadata(),
            'lifecycle_status' => $blueprintEntity->lifecycleStatus()->value,
            'current_revision_id' => $blueprintEntity->currentRevisionId()
                ? (string) $blueprintEntity->currentRevisionId()
                : null,

            'revisions' => array_values(
                array_map(
                    static function ($revision): array {
                        return [
                            'id' => (string) $revision->id(),
                            'number' => (string) $revision->number(),
                            'parent_revision_id' =>
                                $revision->parentRevisionId()
                                    ? (string) $revision->parentRevisionId()
                                    : null,
                            'behavior_digest' =>
                                (string) $revision->behaviorDigest(),
                            'contracts' => $revision->contracts(),
                            'logic' => $revision->logic(),
                            'outputs' => $revision->outputs(),
                            'policies' => $revision->policies(),
                            'frozen' => $revision->isFrozen(),
                        ];
                    },
                    $blueprintEntity->revisions(),
                ),
            ),
        ]);
    }
}
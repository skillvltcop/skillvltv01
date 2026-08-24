<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListBlueprintRevisionsController
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(
        Request $request,
        string $blueprint,
    ): JsonResponse {
        $blueprintEntity = $this->repository->find(
            new BlueprintId($blueprint),
        );

        if ($blueprintEntity === null) {
            return response()->json([
                'message' => 'Blueprint not found.',
            ], 404);
        }

        $ownership = $blueprintEntity->ownership();

        $user = $request->user();

        $isOwner =
            $ownership['type'] === 'user'
            && (string) $ownership['id'] === (string) $user->id;

        if (! $isOwner) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $blueprintId = (string) $blueprintEntity->id();

        return response()->json([
            'data' => array_values(
                array_map(
                    static function ($revision) use ($blueprintId): array {
                        return [
                            'id' => (string) $revision->id(),
                            'blueprint_id' => $blueprintId,
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
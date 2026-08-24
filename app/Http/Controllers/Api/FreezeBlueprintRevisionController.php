<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Blueprint\Commands\FreezeBlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FreezeBlueprintRevisionController
{
    public function __construct(
        private FreezeBlueprintRevision $command,
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(
        Request $request,
        string $blueprint,
        string $revision,
    ): JsonResponse {
        $blueprintEntity = $this->repository->find(
            new BlueprintId($blueprint),
        );

        $blueprintEntity = $this->repository->find(
            new BlueprintId($blueprint),
        );

        if ($blueprintEntity === null) {
            return response()->json([
                'message' => 'Blueprint not found.',
            ], 404);
        }

        $revisionEntity = $blueprintEntity->revision(
            new \App\Domain\Blueprint\ValueObjects\RevisionId($revision),
        );

        if ($revisionEntity === null) {
            return response()->json([
                'message' => 'Blueprint revision not found.',
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

        try {
            $revisionEntity = $this->command->handle(
                blueprintId: $blueprint,
                revisionId: $revision,
            );
        } catch (\RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'Blueprint not found.',
                'Blueprint revision not found.' => response()->json([
                    'message' => $exception->getMessage(),
                ], 404),

                default => throw $exception,
            };
        }

        return response()->json([
            'id' => (string) $revisionEntity->id(),
            'blueprint_id' => $blueprint,
            'number' => (string) $revisionEntity->number(),
            'behavior_digest' => (string) $revisionEntity->behaviorDigest(),
            'contracts' => $revisionEntity->contracts(),
            'logic' => $revisionEntity->logic(),
            'outputs' => $revisionEntity->outputs(),
            'policies' => $revisionEntity->policies(),
            'frozen' => $revisionEntity->isFrozen(),
        ]);
    }
}
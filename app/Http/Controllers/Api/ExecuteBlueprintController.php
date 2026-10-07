<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Http\Requests\Api\ExecuteBlueprintRequest;
use Illuminate\Http\JsonResponse;

final class ExecuteBlueprintController
{
    public function __construct(
        private ExecuteBlueprint $executeBlueprint,
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(
        ExecuteBlueprintRequest $request,
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

        /*
         * Execution authorization:
         *
         * - User-owned Blueprint → only its owner may execute it.
         * - System-owned Blueprint → authenticated users may execute it.
         *
         * Lifecycle, revision ownership, frozen state, and current
         * revision are domain rules and are handled by the Engine.
         */
        $ownership = $blueprintEntity->ownership();
        $user = $request->user();

        $isSystemOwned = $ownership['type'] === 'system';

        $isOwner =
            $ownership['type'] === 'user'
            && (string) $ownership['id'] === (string) $user->id;

        if (! $isSystemOwned && ! $isOwner) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $execution = $this->executeBlueprint->handle(
                blueprintId: $blueprint,
                revisionId: $request->string('revision_id')->toString(),
                ownerId: (string) $request->user()->id,
                input: $request->input('input', []),
                context: $request->input('context', []),
            );
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'execution_id' => (string) $execution->id(),
            'blueprint_id' => (string) $execution->blueprintId(),
            'revision_id' => (string) $execution->revisionId(),
            'status' => $execution->status()->value,
            'input' => $request->input('input', []),
            'context' => $request->input('context', []),
            'output' => $execution->output(),
            'error' => $execution->error(),
        ]);
    }
}
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

        $execution = $this->executeBlueprint->handle(
            blueprintId: $blueprint,
            revisionId: $request->string('revision_id')->toString(),
            input: $request->input('input', []),
            context: $request->input('context', []),
        );

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
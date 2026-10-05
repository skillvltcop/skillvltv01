<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Http\Requests\Api\TeacherExecuteBlueprintRequest;
use Illuminate\Http\JsonResponse;

final class ExecuteTeacherBlueprintController
{
    public function __construct(
        private ExecuteBlueprint $executeBlueprint,
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(
        TeacherExecuteBlueprintRequest $request,
        string $blueprint,
    ): JsonResponse {
        $tool = $request->route('tool');

        $blueprintEntity = $tool !== null
            ? $this->repository->findByCanonicalName((string) $tool)
            : $this->repository->find(new BlueprintId($blueprint));

        if ($blueprintEntity === null) {
            return response()->json([
                'message' => 'Blueprint not found.',
            ], 404);
        }

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

        $currentRevisionId = $blueprintEntity->currentRevisionId();

        if ($currentRevisionId === null) {
            return response()->json([
                'message' => 'Blueprint has no executable revision.',
            ], 422);
        }

        try {
            $execution = $this->executeBlueprint->handle(
                blueprintId: (string) $blueprintEntity->id(),
                revisionId: (string) $currentRevisionId,
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
            'status' => $execution->status()->value,
            'result' => $execution->output(),
            'error' => $execution->error(),
        ]);
    }
}

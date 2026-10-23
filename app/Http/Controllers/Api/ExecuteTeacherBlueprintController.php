<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Authorization\BlueprintAuthorizationPolicy;
use App\Authorization\TeacherToolRegistry;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Http\Requests\Api\TeacherExecuteBlueprintRequest;
use Illuminate\Http\JsonResponse;

final class ExecuteTeacherBlueprintController
{
    public function __construct(
        private ExecuteBlueprint $executeBlueprint,
        private BlueprintRepository $repository,
        private BlueprintAuthorizationPolicy $authorization,
        private TeacherToolRegistry $teacherTools,
    ) {
    }

    public function __invoke(
        TeacherExecuteBlueprintRequest $request,
        string $slug,
    ): JsonResponse {
        $registry = $this->teacherTools->all();
        $entry = $registry[$slug] ?? null;

        if (! is_array($entry) || ! isset($entry['namespace'])) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        $blueprintEntity = $this->repository->findByCanonicalName(
            (string) $entry['namespace'],
            $slug,
        );

        if (
            $blueprintEntity === null
            || ! $this->teacherTools->matches($blueprintEntity)
        ) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        // Teacher tools are published product capabilities, not a namespace
        // alias for arbitrary or private Blueprints.
        if (
            ! $this->authorization->isSystemOwned($blueprintEntity)
            || ! $this->authorization->isPubliclyAvailable($blueprintEntity)
        ) {
            return response()->json(['message' => 'Forbidden.'], 403);
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
                ownerId: (string) $request->user()->id,
                input: $request->input('input', []),
                context: $request->input('context', []),
            );
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'execution_id' => (string) $execution->id(),
            'status' => $execution->status()->value,
            'result' => $execution->output(),
            'error' => $execution->error(),
        ]);
    }
}

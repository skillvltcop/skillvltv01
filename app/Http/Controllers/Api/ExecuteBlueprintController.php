<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Execution\Commands\ExecuteBlueprint;
use App\Http\Requests\Api\ExecuteBlueprintRequest;
use Illuminate\Http\JsonResponse;

final class ExecuteBlueprintController
{
    public function __construct(
        private ExecuteBlueprint $executeBlueprint,
    ) {
    }

    public function __invoke(
        ExecuteBlueprintRequest $request,
        string $blueprint,
    ): JsonResponse {
        $execution = $this->executeBlueprint->handle(
            blueprintId: $blueprint,
            revisionId: $request->string('revision_id')->toString(),
            input: $request->input('input', []),
            context: $request->input('context', []),
        );

        return response()->json([
            'id' => (string) $execution->id(),
            'blueprint_id' => (string) $execution->blueprintId(),
            'revision_id' => (string) $execution->revisionId(),
            'status' => $execution->status()->value,
            'output' => $execution->output(),
            'error' => $execution->error(),
        ]);
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Execution\Repositories\ExecutionRepository;
use App\Domain\Execution\ValueObjects\ExecutionId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowExecutionController
{
    public function __construct(
        private ExecutionRepository $executionRepository,
    ) {
    }

    public function __invoke(
        Request $request,
        string $execution,
    ): JsonResponse {
        $executionEntity = $this->executionRepository->find(
            new ExecutionId($execution),
        );

        if ($executionEntity === null) {
            return response()->json([
                'message' => 'Execution not found.',
            ], 404);
        }

        $user = $request->user();

        if (
            $executionEntity->ownerId() === null
            || $executionEntity->ownerId() !== (string) $user->id
        ) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        return response()->json([
            'id' => (string) $executionEntity->id(),
            'blueprint_id' => (string) $executionEntity->blueprintId(),
            'revision_id' => (string) $executionEntity->revisionId(),
            'status' => $executionEntity->status()->value,
            'input' => $executionEntity->input(),
            'context' => $executionEntity->context(),
            'output' => $executionEntity->output(),
            'error' => $executionEntity->error(),
        ]);
    }
}
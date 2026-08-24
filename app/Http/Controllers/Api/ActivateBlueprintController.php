<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Blueprint\Commands\ActivateBlueprint;
use Illuminate\Http\JsonResponse;

final class ActivateBlueprintController
{
    public function __construct(
        private ActivateBlueprint $command,
    ) {
    }

    public function __invoke(string $blueprint): JsonResponse
    {
        try {
            $blueprintEntity = $this->command->handle(
                blueprintId: $blueprint,
            );
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'Blueprint not found.') {
                return response()->json([
                    'message' => 'Blueprint not found.',
                ], 404);
            }

            throw $exception;
        } catch (\DomainException $exception) {
            return match ($exception->getMessage()) {
                'A Blueprint cannot become active without a Revision.',
                'A Blueprint cannot become active with an unfrozen current Revision.'
                    => response()->json([
                        'message' => $exception->getMessage(),
                    ], 422),

                default => throw $exception,
            };
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
        ]);
    }
}
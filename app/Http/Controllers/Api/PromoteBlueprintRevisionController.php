<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use Illuminate\Http\JsonResponse;

final class PromoteBlueprintRevisionController
{
    public function __construct(
        private PromoteBlueprintRevision $command,
    ) {
    }

    public function __invoke(
        string $blueprint,
        string $revision,
    ): JsonResponse {
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
        } catch (\DomainException $exception) {
            return match ($exception->getMessage()) {
                'A Revision must be frozen before it can become current.' =>
                    response()->json([
                        'message' => $exception->getMessage(),
                    ], 422),

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
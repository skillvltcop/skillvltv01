<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintAuthorizationPolicy;

use App\Application\Blueprint\Commands\PromoteBlueprintRevision;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use App\Domain\Blueprint\ValueObjects\RevisionId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PromoteBlueprintRevisionController
{
    public function __construct(
        private PromoteBlueprintRevision $command,
        private BlueprintRepository $repository,
        private BlueprintAuthorizationPolicy $authorization,
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

    if ($blueprintEntity === null) {
        return response()->json([
            'message' => 'Blueprint not found.',
        ], 404);
    }

    $revisionEntity = $blueprintEntity->revision(
        new RevisionId($revision),
    );

    if ($revisionEntity === null) {
        return response()->json([
            'message' => 'Blueprint revision not found.',
        ], 404);
    }

    if (! $this->authorization->canManage(
        $blueprintEntity,
        $request->user(),
        'blueprint.system.manage_revisions',
        $request->input('authorization_reason'),
    )) {
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
        } catch (\DomainException $exception) {
            return match ($exception->getMessage()) {
                'A Revision must be frozen before it can become current.' =>
                    response()->json([
                        'message' => $exception->getMessage(),
                    ], 422),

                default => throw $exception,
            };
        }

        $response = [
            'id' => (string) $revisionEntity->id(),
            'blueprint_id' => $blueprint,
            'number' => (string) $revisionEntity->number(),
            'frozen' => $revisionEntity->isFrozen(),
        ];

        // Revision-management permission does not implicitly grant permission
        // to read the private behavior definition.
        if ($this->authorization->canReadRevisionDetails(
            $blueprintEntity,
            $revisionEntity,
            $request->user(),
            $request->input('authorization_reason'),
        )) {
            $response['behavior_digest'] = (string) $revisionEntity->behaviorDigest();
            $response['contracts'] = $revisionEntity->contracts();
            $response['logic'] = $revisionEntity->logic();
            $response['outputs'] = $revisionEntity->outputs();
            $response['policies'] = $revisionEntity->policies();
        }

        return response()->json($response);
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintAuthorizationPolicy;

use App\Application\Blueprint\Commands\DeprecateBlueprint;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use App\Domain\Blueprint\ValueObjects\BlueprintId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class DeprecateBlueprintController
{
    public function __construct(
        private DeprecateBlueprint $command,
        private BlueprintRepository $repository,
        private BlueprintAuthorizationPolicy $authorization,
    ) {
    }

    public function __invoke(
        Request $request,
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

        if (! $this->authorization->canManage(
            $blueprintEntity,
            $request->user(),
            'blueprint.system.manage_lifecycle',
            $request->input('authorization_reason'),
        )) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $blueprintEntity = $this->command->handle(
                blueprintId: $blueprint,
            );
        } catch (\DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Blueprint not found.') {
                return response()->json([
                    'message' => 'Blueprint not found.',
                ], 404);
            }

            throw $exception;
        }

        $response = [
            'id' => (string) $blueprintEntity->id(),
            'canonical_name' => (string) $blueprintEntity->canonicalName(),
            'lifecycle_status' => $blueprintEntity->lifecycleStatus()->value,
        ];

        // Lifecycle-management permission does not implicitly grant access to
        // private metadata or internal ownership/revision identifiers.
        if ($this->authorization->canReadBlueprintDetails(
            $blueprintEntity,
            $request->user(),
            $request->input('authorization_reason'),
        )) {
            $response['namespace'] = (string) $blueprintEntity->namespace();
            $response['ownership'] = $blueprintEntity->ownership();
            $response['metadata'] = $blueprintEntity->metadata();
            $response['current_revision_id'] = $blueprintEntity->currentRevisionId()
                ? (string) $blueprintEntity->currentRevisionId()
                : null;
        }

        return response()->json($response);
    }
}
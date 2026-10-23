<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintAuthorizationPolicy;

use App\Application\Blueprint\Commands\CreateBlueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class CreateBlueprintController
{
    public function __construct(
        private CreateBlueprint $command,
        private BlueprintAuthorizationPolicy $authorization,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = Validator::make(
            $request->all(),
            [
                'canonical_name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'namespace' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'metadata' => [
                    'sometimes',
                    'array',
                ],
                'ownership' => ['sometimes', 'array'],
                'ownership.type' => ['sometimes', 'string', 'in:user,system'],
            ],
        )->validate();

        $user = $request->user();
        $requestedOwnership = $validated['ownership']['type'] ?? 'user';

        if (
            $requestedOwnership === 'system'
            && ! $user->hasPermissionTo('blueprint.system.create')
        ) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $ownership = $requestedOwnership === 'system'
            ? ['type' => 'system', 'id' => 'skillvlt']
            : ['type' => 'user', 'id' => (string) $user->id];

        try {
            $blueprint = $this->command->handle(
                canonicalName: $validated['canonical_name'],
                namespace: $validated['namespace'],
                ownership: $ownership,
                metadata: $validated['metadata'] ?? [],
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'id' => (string) $blueprint->id(),
            'canonical_name' => (string) $blueprint->canonicalName(),
            'namespace' => (string) $blueprint->namespace(),
            'ownership' => $blueprint->ownership(),
            'metadata' => $blueprint->metadata(),
            'lifecycle_status' => $blueprint->lifecycleStatus()->value,
        ], 201);
    }
}

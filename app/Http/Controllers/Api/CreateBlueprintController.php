<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Blueprint\Commands\CreateBlueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class CreateBlueprintController
{
    public function __construct(
        private CreateBlueprint $command,
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
            ],
        )->validate();

        $user = $request->user();

        $blueprint = $this->command->handle(
            canonicalName: $validated['canonical_name'],
            namespace: $validated['namespace'],
            ownership: [
                'type' => 'user',
                'id' => (string) $user->id,
            ],
            metadata: $validated['metadata'] ?? [],
        );

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
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Blueprint\Repositories\BlueprintRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ListBlueprintsController
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $blueprints = $this->repository->findOwnedBy(
            ownerType: 'user',
            ownerId: (string) $request->user()->id,
        );

        return response()->json([
            'data' => array_map(
                static function ($blueprint): array {
                    return [
                        'id' => (string) $blueprint->id(),
                        'canonical_name' =>
                            (string) $blueprint->canonicalName(),
                        'namespace' =>
                            (string) $blueprint->namespace(),
                        'ownership' => $blueprint->ownership(),
                        'metadata' => $blueprint->metadata(),
                        'lifecycle_status' =>
                            $blueprint->lifecycleStatus()->value,
                        'current_revision_id' =>
                            $blueprint->currentRevisionId()
                                ? (string) $blueprint->currentRevisionId()
                                : null,
                    ];
                },
                $blueprints,
            ),
        ]);
    }
}
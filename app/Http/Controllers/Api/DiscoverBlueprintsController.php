<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintPublicProjection;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DiscoverBlueprintsController
{
    public function __construct(
        private BlueprintRepository $repository,
        private BlueprintPublicProjection $projection,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn ($blueprint): array => $this->projection->from($blueprint),
                $this->repository->discover(),
            ),
        ]);
    }
}

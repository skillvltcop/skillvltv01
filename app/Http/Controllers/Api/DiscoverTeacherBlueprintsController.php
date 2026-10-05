<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Blueprint\Repositories\BlueprintRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DiscoverTeacherBlueprintsController
{
    public function __construct(
        private BlueprintRepository $repository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $blueprints = $this->repository->discover();

        return response()->json([
            'data' => array_map(
                static function ($blueprint): array {
                    $metadata = $blueprint->metadata();
                    $revision = $blueprint->currentRevision();

                    return [
                        'title' => $metadata['title'] ?? (string) $blueprint->canonicalName(),
                        'target_level' => $metadata['target_level'] ?? null,
                        'purpose' => $metadata['purpose'] ?? ($metadata['description'] ?? null),
                        'version' => $revision !== null
                            ? 'v' . (string) $revision->number()
                            : null,
                    ];
                },
                $blueprints,
            ),
        ]);
    }
}

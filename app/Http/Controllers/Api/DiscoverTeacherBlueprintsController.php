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
                static function ($blueprint) use ($request): array {
                    $metadata = $blueprint->metadata();
                    $revision = $blueprint->currentRevision();

                    $locale = (string) $request->query('locale', $request->header('Accept-Language', app()->getLocale()));
                    $locale = in_array($locale, ['ar', 'fr', 'en'], true) ? $locale : app()->getLocale();

                    $localize = static function (mixed $value) use ($locale): mixed {
                        if (! is_array($value)) {
                            return $value;
                        }

                        return $value[$locale] ?? $value['ar'] ?? reset($value);
                    };

                    return [
                        'slug' => (string) $blueprint->canonicalName(),
                        'title' => $localize($metadata['title'] ?? (string) $blueprint->canonicalName()),
                        'target_level' => $localize($metadata['target_level'] ?? null),
                        'purpose' => $localize($metadata['purpose'] ?? ($metadata['description'] ?? null)),
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

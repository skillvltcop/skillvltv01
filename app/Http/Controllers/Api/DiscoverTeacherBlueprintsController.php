<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Authorization\BlueprintAuthorizationPolicy;
use App\Authorization\TeacherToolRegistry;
use App\Domain\Blueprint\Repositories\BlueprintRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DiscoverTeacherBlueprintsController
{
    public function __construct(
        private BlueprintRepository $repository,
        private BlueprintAuthorizationPolicy $authorization,
        private TeacherToolRegistry $teacherTools,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $blueprints = array_values(array_filter(
            $this->repository->discover(),
            fn ($blueprint): bool => $this->teacherTools->matches($blueprint)
                && $this->authorization->isPubliclyAvailable($blueprint),
        ));

        return response()->json([
            'data' => array_map(
                static function ($blueprint) use ($request): array {
                    $metadata = $blueprint->metadata();
                    $revision = $blueprint->currentRevision();

                    $requestedLocale = $request->query('locale');
                    $sessionLocale = $request->hasSession()
                        ? (string) $request->session()->get('locale', app()->getLocale())
                        : app()->getLocale();

                    $hasSupportedSessionLocale = $request->hasSession()
                        && $request->session()->has('locale')
                        && in_array($sessionLocale, ['ar', 'fr', 'en'], true);
                    $fallbackLocale = $hasSupportedSessionLocale
                        ? $sessionLocale
                        : app()->getLocale();

                    if (is_string($requestedLocale)) {
                        $locale = in_array($requestedLocale, ['ar', 'fr', 'en'], true)
                            ? $requestedLocale
                            : $fallbackLocale;
                    } else {
                        $locale = $fallbackLocale;
                        $acceptLanguage = $request->headers->get('Accept-Language');

                        if (! $hasSupportedSessionLocale && is_string($acceptLanguage) && trim($acceptLanguage) !== '') {
                            foreach ($request->getLanguages() as $preferredLanguage) {
                                $language = strtolower(substr($preferredLanguage, 0, 2));

                                if (in_array($language, ['ar', 'fr', 'en'], true)) {
                                    $locale = $language;
                                    break;
                                }
                            }
                        }
                    }

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

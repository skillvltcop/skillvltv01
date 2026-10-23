<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Blueprint\Entities\Blueprint;

/**
 * Stable, intentionally limited public API representation of a Blueprint.
 */
final class BlueprintPublicProjection
{
    /**
     * @return array<string, mixed>
     */
    public function from(Blueprint $blueprint, mixed $requestedLocale = null): array
    {
        $supportedLocales = ['ar', 'fr', 'en'];
        $applicationLocale = (string) app()->getLocale();
        $locale = is_string($requestedLocale) && in_array($requestedLocale, $supportedLocales, true)
            ? $requestedLocale
            : (in_array($applicationLocale, $supportedLocales, true) ? $applicationLocale : 'ar');

        $metadata = $blueprint->metadata();
        $revision = $blueprint->currentRevision();

        $localize = static function (mixed $value) use ($locale): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if (array_key_exists($locale, $value)) {
                return $value[$locale];
            }

            if (array_key_exists('ar', $value)) {
                return $value['ar'];
            }

            return $value === [] ? null : reset($value);
        };

        return [
            'canonical_name' => (string) $blueprint->canonicalName(),
            'title' => $localize($metadata['title'] ?? (string) $blueprint->canonicalName()),
            'description' => $localize(
                $metadata['description'] ?? ($metadata['documentation']['description'] ?? null),
            ),
            'category' => $metadata['taxonomy']['domain'] ?? null,
            'tags' => $metadata['discovery']['tags'] ?? [],
            'version' => $revision !== null ? (string) $revision->number() : null,
        ];
    }
}

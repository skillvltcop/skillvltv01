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
    public function from(Blueprint $blueprint): array
    {
        $metadata = $blueprint->metadata();
        $revision = $blueprint->currentRevision();

        return [
            'canonical_name' => (string) $blueprint->canonicalName(),
            'title' => $metadata['title'] ?? (string) $blueprint->canonicalName(),
            'description' => $metadata['description']
                ?? ($metadata['documentation']['description'] ?? null),
            'category' => $metadata['taxonomy']['domain'] ?? null,
            'tags' => $metadata['discovery']['tags'] ?? [],
            'version' => $revision !== null ? (string) $revision->number() : null,
        ];
    }
}

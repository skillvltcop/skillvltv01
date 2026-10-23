<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Blueprint\Entities\Blueprint;

/**
 * Product-specific allowlist for teacher-facing tools.
 *
 * Discovery and execution must consult this same registry. The registry is
 * configuration-driven so additional product domains can be added without
 * hard-coding their slugs into the platform's Blueprint core.
 */
final class TeacherToolRegistry
{
    /**
     * @return array<string, array{namespace: string, view: string}>
     */
    public function all(): array
    {
        $defaults = [
            'assessment-positioning' => [
                'namespace' => 'skillvlt.edu.assessment',
                'view' => 'teacher.tools.assessment-positioning',
            ],
            'assessment-score-calculator' => [
                'namespace' => 'skillvlt.edu.assessment',
                'view' => 'teacher.tools.assessment-score-calculator',
            ],
            'assessment-results-analyzer' => [
                'namespace' => 'skillvlt.edu.assessment',
                'view' => 'teacher.tools.assessment-results-analyzer',
            ],
        ];

        // Keep this small resolver independently testable without booting Laravel.
        if (! app()->bound('config')) {
            return $defaults;
        }

        return (array) config('teacher_tools.tools', $defaults);
    }

    public function contains(string $slug): bool
    {
        return array_key_exists($slug, $this->all());
    }

    public function matches(Blueprint $blueprint): bool
    {
        $slug = (string) $blueprint->canonicalName();
        $entry = $this->all()[$slug] ?? null;

        return is_array($entry)
            && ($entry['namespace'] ?? null) === (string) $blueprint->namespace();
    }

    public function viewFor(string $slug): ?string
    {
        $view = $this->all()[$slug]['view'] ?? null;

        return is_string($view) ? $view : null;
    }
}

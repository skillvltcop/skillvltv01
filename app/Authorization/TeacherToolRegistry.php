<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Domain\Blueprint\Entities\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product-specific allowlist for teacher-facing tools.
 *
 * Discovery and execution consult this same registry. Configuration provides
 * safe defaults; persisted administrative overrides can add, replace, or disable
 * entries without coupling the Blueprint core to the education domain.
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

        if (app()->bound('config')) {
            $defaults = (array) config('teacher_tools.tools', $defaults);
        }

        // Unit tests may instantiate the resolver without booting database services.
        if (! app()->bound('db') || ! Schema::hasTable('teacher_tool_registry')) {
            return $defaults;
        }

        $tools = $defaults;

        foreach (DB::table('teacher_tool_registry')->get() as $entry) {
            if (! (bool) $entry->is_enabled) {
                unset($tools[$entry->slug]);
                continue;
            }

            $tools[$entry->slug] = [
                'namespace' => (string) $entry->namespace,
                'view' => (string) $entry->view,
            ];
        }

        return $tools;
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

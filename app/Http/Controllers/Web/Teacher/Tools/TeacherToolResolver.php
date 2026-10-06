<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Teacher\Tools;

final class TeacherToolResolver
{
    public function viewFor(string $slug): ?string
    {
        return match ($slug) {
            'assessment-positioning' => 'teacher.tools.assessment-positioning',
            default => null,
        };
    }
}

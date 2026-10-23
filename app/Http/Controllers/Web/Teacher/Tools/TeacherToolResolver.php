<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Teacher\Tools;

use App\Authorization\TeacherToolRegistry;

final class TeacherToolResolver
{
    public function __construct(
        private TeacherToolRegistry $registry,
    ) {
    }

    public function viewFor(string $slug): ?string
    {
        return $this->registry->viewFor($slug);
    }
}

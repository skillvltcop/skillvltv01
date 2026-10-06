<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Teacher\Tools;

use Illuminate\View\View;

final class AssessmentPositioningController
{
    public function __invoke(string $slug): View
    {
        abort_unless($slug === 'assessment-positioning', 404);

        return view('teacher.tools.assessment-positioning');
    }
}

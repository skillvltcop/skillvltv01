<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Teacher\Tools;

use Illuminate\View\View;

final class AssessmentPositioningController
{
    public function __invoke(): View
    {
        return view('teacher.tools.assessment-positioning');
    }
}

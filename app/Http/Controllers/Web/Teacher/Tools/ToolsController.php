<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Teacher\Tools;

use Illuminate\View\View;

final class ToolsController
{
    public function __invoke(): View
    {
        return view('teacher.tools.index');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;

final class AuthorizationDashboardController
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('admin.authorization.index', [
            'currentUserId' => (string) $request->user()->id,
        ]);
    }
}

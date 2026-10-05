<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');

        if (! in_array($locale, ['ar', 'fr', 'en'], true)) {
            return back();
        }

        $request->session()->put('locale', $locale);

        return back();
    }
}

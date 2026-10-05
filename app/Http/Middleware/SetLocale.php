<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    private const SUPPORTED_LOCALES = ['ar', 'fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get(
            'locale',
            config('app.locale', 'ar'),
        );

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

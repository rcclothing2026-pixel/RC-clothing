<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Set the application locale for the storefront from the visitor's saved choice
 * (session, set by the header language toggle → /locale/{locale}). Defaults to
 * the app locale (en). Drives <html lang/dir>, Money formatting, and __() copy.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = (array) config('app.available_locales', ['en', 'fa']);
        $locale = (string) $request->session()->get('locale', config('app.locale', 'en'));

        if (! in_array($locale, $available, true)) {
            $locale = (string) config('app.locale', 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

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

        // ?lang=fa|en makes each language reachable at a stable, crawlable URL so
        // hreflang alternates point somewhere real (Google can't switch a
        // session). It also persists the choice, exactly like the header toggle.
        $qLocale = (string) $request->query('lang', '');
        if ($qLocale !== '' && in_array($qLocale, $available, true)) {
            $request->session()->put('locale', $qLocale);
        }

        $locale = (string) $request->session()->get('locale', config('app.locale', 'en'));

        if (! in_array($locale, $available, true)) {
            $locale = (string) config('app.locale', 'en');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

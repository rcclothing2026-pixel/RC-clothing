<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        $response = $next($request);

        // Never let a proxy or the browser serve a stale admin page — otherwise
        // a fresh deploy looks like "nothing changed" until the cache expires.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        // When an admin SAVES something (any write), tell LiteSpeed to drop its
        // full-page cache of the storefront. Otherwise a content change (popup,
        // promo bar, page blocks, settings…) sits in the DB while visitors keep
        // getting the cached old HTML until the TTL expires. LiteSpeed honours
        // this response header; it's a harmless no-op on non-LiteSpeed hosts.
        if (! $request->isMethodCacheable()) { // anything but GET/HEAD
            $response->headers->set('X-LiteSpeed-Purge', '*');
        }

        return $response;
    }
}

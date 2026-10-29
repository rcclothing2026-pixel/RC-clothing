<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gate for /api/ig/* (the Instagram DM responder's API): the live price &
 * stock feed, and creating /b/{code} purchase links. A link only prepares a
 * cart — price, stock and payment stay with the normal checkout — so the key
 * can read the catalogue and make links, nothing more.
 *
 * The caller sends X-Catalog-Key; it must equal IG_CATALOG_KEY (compared in
 * constant time). Anything else — no header, a wrong key, or the env unset on
 * this server — gets the router's own "route not found" 404, so a prober
 * can't tell the API exists.
 *
 * Responses are marked uncacheable: LiteSpeed's page cache does not vary on
 * this header, so a cached copy could reach a caller without the key.
 */
class VerifyIgCatalogKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ig_catalog.key', '');
        $got = (string) $request->header('X-Catalog-Key', '');

        if ($expected === '' || $got === '' || ! hash_equals($expected, $got)) {
            throw new NotFoundHttpException(sprintf('The route %s could not be found.', $request->path()));
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');

        return $response;
    }
}

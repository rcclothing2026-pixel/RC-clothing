<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for /api/campaigns/*: the IG catalogue key (X-Catalog-Key) as before, or — for the two
 * reads StoqS makes when it imports this shop's campaigns (GET /api/campaigns/plan and
 * /api/campaigns/editor) — StoqS's own signature, so no key has to be copied into StoqS:
 *
 *   X-Stoqs-Timestamp: <unix seconds>
 *   X-Stoqs-Nonce: <16 to 64 letters and digits, new for every request>
 *   X-Stoqs-Signature: sha256=<hmac_sha256("GET\n" + host + "\n" + path + "\n" + query + "\n"
 *                              + timestamp + "\n" + nonce, webhook_secret)>
 *
 * host is this shop's own (APP_URL's host, lower-case, without a leading «www.»), never the one
 * the request names; path is the request path as sent (e.g. /api/campaigns/plan) and query the
 * raw query string (empty when none). The secret is the one this shop already shares with StoqS
 * for webhooks (stockkeeping.webhook_secret); empty = the signature path is off. The timestamp
 * must be within 300 seconds, and a nonce is accepted once (kept in the cache for twice the
 * window), so a captured request cannot be replayed, here or on another site sharing the secret.
 * The older form without host, query and nonce is refused. Writes (PUT/POST/DELETE) and every
 * other path still need the key.
 *
 * A request carrying a StoqS signature (and no key) that does not check out gets 401, so StoqS
 * can say why; anything else goes to the key check, whose answer is the router's 404.
 */
class VerifyCampaignReadAccess
{
    public const WINDOW = 300;

    public const SIGNED_PATHS = ['api/campaigns/plan', 'api/campaigns/editor'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('X-Stoqs-Signature') && ! $request->hasHeader('X-Catalog-Key')) {
            if (! self::signatureOk($request)) {
                return response()->json(['message' => 'Invalid signature'], 401)
                    ->header('Cache-Control', 'no-store, private');
            }
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-LiteSpeed-Cache-Control', 'no-cache');

            return $response;
        }

        return app(VerifyIgCatalogKey::class)->handle($request, $next);
    }

    public static function signatureOk(Request $request, ?int $now = null): bool
    {
        $secrets = \App\Services\StockKeeping\StoqsConnect::secrets();   // the current one, and the previous one for an hour after a rotation
        if (! $secrets || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }
        if (! in_array(trim($request->path(), '/'), self::SIGNED_PATHS, true)) {
            return false;
        }
        $ts = (string) $request->header('X-Stoqs-Timestamp', '');
        if (! ctype_digit($ts) || abs(($now ?? time()) - (int) $ts) > self::WINDOW) {
            return false;
        }
        $nonce = (string) $request->header('X-Stoqs-Nonce', '');
        $host = self::ownHost();
        if (! preg_match('/^[A-Za-z0-9]{16,64}$/', $nonce) || $host === '') {
            return false;
        }
        $uri = $request->getRequestUri();
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $query = (string) parse_url($uri, PHP_URL_QUERY);
        $given = (string) $request->header('X-Stoqs-Signature', '');
        $ok = false;
        foreach ($secrets as $secret) {
            $ok = hash_equals(self::sign('GET', $host, $path, $query, (int) $ts, $nonce, $secret), $given) || $ok;
        }
        if (! $ok) {
            return false;
        }

        return Cache::add('stoqs-sig-nonce:'.$nonce, 1, self::WINDOW * 2);   // false: seen already (a replay)
    }

    /** This shop's own host as signed: APP_URL's, lower-case, without a leading «www.». */
    public static function ownHost(): string
    {
        return (string) preg_replace('~^www\.~', '', strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)));
    }

    public static function sign(string $method, string $host, string $path, string $query, int $ts, string $nonce, string $secret): string
    {
        $host = (string) preg_replace('~^www\.~', '', strtolower($host));

        return 'sha256='.hash_hmac('sha256', strtoupper($method)."\n".$host."\n".$path."\n".$query."\n".$ts."\n".$nonce, $secret);
    }
}

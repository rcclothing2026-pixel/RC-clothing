<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for /api/campaigns/*: the IG catalogue key (X-Catalog-Key) as before, or — for the two
 * reads StoqS makes when it imports this shop's campaigns (GET /api/campaigns/plan and
 * /api/campaigns/editor) — StoqS's own signature, so no key has to be copied into StoqS:
 *
 *   X-Stoqs-Timestamp: <unix seconds>
 *   X-Stoqs-Signature: sha256=<hmac_sha256("GET\n" + path + "\n" + timestamp, webhook_secret)>
 *
 * path is the request path as sent (e.g. /api/campaigns/plan). The secret is the one this shop
 * already shares with StoqS for webhooks (stockkeeping.webhook_secret); empty = the signature
 * path is off. The timestamp must be within 300 seconds; the compare is constant-time.
 * Writes (PUT/POST/DELETE) and every other path still need the key.
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
        $secret = (string) config('stockkeeping.webhook_secret', '');
        if ($secret === '' || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }
        if (! in_array(trim($request->path(), '/'), self::SIGNED_PATHS, true)) {
            return false;
        }
        $ts = (string) $request->header('X-Stoqs-Timestamp', '');
        if (! ctype_digit($ts) || abs(($now ?? time()) - (int) $ts) > self::WINDOW) {
            return false;
        }
        $path = (string) parse_url($request->getRequestUri(), PHP_URL_PATH);

        return hash_equals(self::sign($path, (int) $ts, $secret), (string) $request->header('X-Stoqs-Signature', ''));
    }

    public static function sign(string $path, int $ts, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', "GET\n".$path."\n".$ts, $secret);
    }
}

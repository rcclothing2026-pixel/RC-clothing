<?php

namespace App\Services\StockKeeping;

use App\Models\Setting;
use App\Models\StockkeeepingLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin JSON client for the StoqS write endpoints (catalog:write, discounts:*):
 * /products, /variants, /media, /sites, /discounts/codes. No retries here — the
 * outbox (IntegrationEvent) retries. A refusal throws StoqsApiError.
 *
 * A key without the scope (403 forbidden_scope) is remembered in the setting
 * stockkeeping.missing_scopes and logged ONCE; the admin sees it on the StoqS
 * settings page. The first successful call of that scope clears it again.
 */
class StoqsApi
{
    /** @return array<string, mixed> decoded JSON body */
    public static function call(string $method, string $path, ?array $body = null, array $query = [], ?string $file = null, array $fields = []): array
    {
        $req = self::client();

        $path = ltrim($path, '/');
        if ($file !== null) {
            $resp = $req->attach('file', (string) file_get_contents($file), basename($file))->post($path, $fields);
        } else {
            $opts = $query ? ['query' => $query] : [];
            if ($body !== null) {
                $opts['json'] = $body;
            }
            $resp = $req->send(strtoupper($method), $path, $opts);
        }

        $scope = self::scopeOf($method, $path);
        if ($resp->successful()) {
            self::scopeOk($scope);

            return (array) $resp->json();
        }

        throw self::error($resp, $scope);
    }

    /**
     * A report only this site can make (POST promotions/ack): the JSON body sent raw and signed
     * with the webhook secret StoqS shares with this site, so a copy of the API key alone cannot
     * confirm a switch or report the shop's own discounts. X-Stoqs-Site-Timestamp and
     * X-Stoqs-Site-Nonce (32 hex, new per call) and X-Stoqs-Site-Signature: sha256=hmac_sha256(METHOD\nop\ntimestamp\nnonce\nraw body,
     * secret), op = the path ("promotions/ack"). Without a secret it goes unsigned (StoqS ignores those fields).
     *
     * @return array<string, mixed> decoded JSON body
     */
    public static function signedCall(string $method, string $path, array $body): array
    {
        $path = ltrim($path, '/');
        $raw = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resp = self::siteSigned(self::client(), $method, $path, $raw)->withBody($raw, 'application/json')->send(strtoupper($method), $path);
        $scope = self::scopeOf($method, $path);
        if ($resp->successful()) {
            self::scopeOk($scope);

            return (array) $resp->json();
        }
        self::noteSignatureRefusal($resp);

        throw self::error($resp, $scope);
    }

    /**
     * sha256=hmac(METHOD\nop\nts\nnonce\nraw, secret); without a nonce the older form
     * METHOD\nop\nts\nraw (StoqS still reads it).
     */
    public static function siteSignature(string $method, string $op, int $ts, string $raw, string $secret, ?string $nonce = null): string
    {
        $mid = $nonce === null ? '' : $nonce."\n";

        return 'sha256='.hash_hmac('sha256', strtoupper($method)."\n".$op."\n".$ts."\n".$mid.$raw, $secret);
    }

    /** A 401 «signature_*» from StoqS: this shop's secret no longer fits, so hello sends `connect` again. */
    private static function noteSignatureRefusal(Response $resp): void
    {
        if (StoqsConnect::isSignatureRefusal($resp->status(), (array) $resp->json())) {
            StoqsConnect::reconnectNeeded();
        }
    }

    private static function siteSigned(PendingRequest $req, string $method, string $op, string $raw): PendingRequest
    {
        $secret = (string) config('stockkeeping.webhook_secret', '');
        if ($secret === '') {
            return $req;
        }
        $ts = time();
        $nonce = bin2hex(random_bytes(16));

        return $req->withHeaders([
            'X-Stoqs-Site-Timestamp' => (string) $ts, 'X-Stoqs-Site-Nonce' => $nonce,
            'X-Stoqs-Site-Signature' => self::siteSignature($method, $op, $ts, $raw, $secret, $nonce),
        ]);
    }

    /**
     * PUT /promotions/source (scope discounts:write): the site asks StoqS to switch who manages
     * its promotions. Unlike call(), a 409 (stale) and a 422 (guard) are answers, not errors:
     * [status, decoded body]. A 403 remembers the missing scope.
     *
     * @return array{status: int, json: array}
     */
    public static function putSource(array $body): array
    {
        $raw = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);   // signed, like the ack
        $resp = self::siteSigned(self::client(), 'PUT', 'promotions/source', $raw)->withBody($raw, 'application/json')->send('PUT', 'promotions/source');
        if ($resp->successful()) {
            self::scopeOk('discounts:write');
        } elseif ($resp->status() === 403) {
            self::error($resp, 'discounts:write');
        } else {
            self::noteSignatureRefusal($resp);
        }

        return ['status' => $resp->status(), 'json' => (array) $resp->json()];
    }

    /** The authenticated /api/v1 client every call starts from. */
    public static function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('stockkeeping.base_url'), '/').'/api/v1')
            ->withToken((string) config('stockkeeping.api_key'))
            ->withHeader('X-Instance-ID', (string) config('stockkeeping.instance_id', ''))
            ->acceptJson()
            ->timeout(15);
    }

    /** A refused response as StoqsApiError, remembering a missing scope (for callers that send their own request). */
    public static function failure(Response $resp, string $scope): StoqsApiError
    {
        return self::error($resp, $scope);
    }

    private static function error(Response $resp, string $scope): StoqsApiError
    {
        $json = (array) $resp->json();
        $err = (array) ($json['error'] ?? []);
        $e = new StoqsApiError($resp->status(), (string) ($err['code'] ?? ''), (string) ($err['message'] ?? ''), $json);
        if ($e->missingScope()) {
            self::scopeMissing($scope, $e->getMessage());
        }

        return $e;
    }

    public static function scopeOf(string $method, string $path): string
    {
        if ($path === 'promotions/source' && strtoupper($method) !== 'GET') {
            return 'discounts:write';
        }
        if (str_starts_with($path, 'promotions')) {
            return 'discounts:read';   // GET /promotions and POST /promotions/evaluate, /ack
        }
        if (str_starts_with($path, 'discounts')) {
            return strtoupper($method) === 'GET' ? 'discounts:read' : 'discounts:write';
        }

        return strtoupper($method) === 'GET' ? 'catalog:read' : 'catalog:write';
    }

    /** @return array<string, string> scope => since (ISO) */
    public static function missingScopes(): array
    {
        return (array) Setting::get('stockkeeping.missing_scopes', []);
    }

    private static function scopeMissing(string $scope, string $message): void
    {
        $missing = self::missingScopes();
        if (isset($missing[$scope])) {
            return; // already known — logged once
        }
        $missing[$scope] = now()->toIso8601String();
        Setting::put('stockkeeping.missing_scopes', $missing);
        Log::warning("[stoqs] API key lacks scope {$scope}: {$message}");
        StockkeeepingLog::record(
            StockkeeepingLog::TYPE_CONTENT_PUSH, 'out', $scope, null, ['message' => $message], 'failed',
            error: $message, source: StockkeeepingLog::SOURCE_AUTO,
            summary: "کلید StoqS دسترسی {$scope} ندارد — تا اضافه شدن دسترسی، این بخش ارسال نمی‌شود",
        );
    }

    /** The scope works again: drop it from the missing list (and the admin banner). */
    public static function scopeOk(string $scope): void
    {
        $missing = self::missingScopes();
        if (isset($missing[$scope])) {
            unset($missing[$scope]);
            Setting::put('stockkeeping.missing_scopes', $missing);
        }
    }
}

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
     * PUT /promotions/source (scope discounts:write): the site asks StoqS to switch who manages
     * its promotions. Unlike call(), a 409 (stale) and a 422 (guard) are answers, not errors:
     * [status, decoded body]. A 403 remembers the missing scope.
     *
     * @return array{status: int, json: array}
     */
    public static function putSource(array $body): array
    {
        $resp = self::client()->put('promotions/source', $body);
        if ($resp->successful()) {
            self::scopeOk('discounts:write');
        } elseif ($resp->status() === 403) {
            self::error($resp, 'discounts:write');
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

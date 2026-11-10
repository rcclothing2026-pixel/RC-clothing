<?php

namespace App\Services\StockKeeping;

use App\Models\Setting;
use App\Models\StockkeeepingLog;
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
        $req = Http::baseUrl(rtrim((string) config('stockkeeping.base_url'), '/').'/api/v1')
            ->withToken((string) config('stockkeeping.api_key'))
            ->withHeader('X-Instance-ID', (string) config('stockkeeping.instance_id', ''))
            ->acceptJson()
            ->timeout(15);

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

    private static function scopeOk(string $scope): void
    {
        $missing = self::missingScopes();
        if (isset($missing[$scope])) {
            unset($missing[$scope]);
            Setting::put('stockkeeping.missing_scopes', $missing);
        }
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reports meaningful client (4xx) responses to the NJ Focus hub — the "user
 * error" class Laravel neither throws nor logs. 403/419/429 report as `warning`;
 * 404/422 as `debug` (hidden by default in the hub). 404/422 are throttled to
 * one report per path per hour so a bot scan can't burst the hub. Self-contained
 * (inline Http + TLS), runs in terminate() after the response is sent.
 */
class ReportClientErrors
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        try {
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 0;

            $level = match (true) {
                in_array($status, [403, 419, 429], true) => 'warning',
                in_array($status, [404, 422], true)      => 'debug',
                default                                   => null,
            };
            if ($level === null) return;

            $key = config('services.nj_focus.key');
            if (! $key) return;

            if ($level === 'debug' && ! Cache::add('hub4xx:' . md5($status . '|' . $request->path()), 1, 3600)) {
                return;
            }

            $base = rtrim((string) config('services.nj_focus.base_url'), '/') ?: 'https://chiacoservice.ir';

            Http::withHeaders(['X-API-Key' => $key])
                ->withOptions(['curl' => [
                    CURLOPT_SSLVERSION   => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ]])
                ->timeout(3)
                ->post($base . '/api/hub/log', [
                    'app'         => config('services.nj_focus.app'),
                    'type'        => 'error',
                    'level'       => $level,
                    'title'       => "HTTP {$status} · " . $request->method() . ' /' . ltrim($request->path(), '/'),
                    'message'     => $status . ' · ' . $request->fullUrl(),
                    'source'      => 'server',
                    'environment' => config('services.nj_focus.env'),
                    'metadata'    => array_filter([
                        'status' => $status,
                        'ip'     => $request->ip(),
                        'ua'     => Str::limit((string) $request->userAgent(), 200),
                    ], fn ($v) => $v !== null && $v !== ''),
                ]);
        } catch (\Throwable $e) {
            // reporting must never affect the response
        }
    }
}

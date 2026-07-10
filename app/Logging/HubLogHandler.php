<?php

namespace App\Logging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Forwards Log::error()/critical() (and anything the framework logs at ERROR+)
 * to the NJ Focus hub, so serious issues that are *logged* rather than thrown
 * still surface. Fire-and-forget; wrapped so it can never recurse or break the
 * logger. Self-contained (no service dependency) so it drops into any app.
 */
class HubLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        try {
            $key = config('services.nj_focus.key');
            if (! $key) return;

            $base = rtrim((string) config('services.nj_focus.base_url'), '/') ?: 'https://chiacoservice.ir';
            $msg  = (string) $record->message;

            Http::withHeaders(['X-API-Key' => $key])
                ->withOptions(['curl' => [
                    CURLOPT_SSLVERSION   => CURL_SSLVERSION_TLSv1_2,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ]])
                ->timeout(3)
                ->post($base . '/api/hub/log', [
                    'app'         => config('services.nj_focus.app'),
                    'type'        => 'error',
                    'level'       => $record->level->value >= Level::Critical->value ? 'critical' : 'error',
                    'title'       => Str::limit($msg, 200),
                    'message'     => $msg,
                    'source'      => 'server',
                    'environment' => config('services.nj_focus.env'),
                    'metadata'    => array_filter([
                        'channel' => $record->channel,
                        'context' => $record->context ? Str::limit((string) json_encode($record->context), 1000) : null,
                    ], fn ($v) => $v !== null && $v !== ''),
                ]);
        } catch (\Throwable $e) {
            // never let hub log-forwarding recurse or break the logger
        }
    }
}

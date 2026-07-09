<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
        // Storefront locale (en/fa) from the visitor's saved choice — drives
        // <html lang/dir>, currency formatting, and translated chrome copy.
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            // Cron-less scheduler: web traffic kicks `schedule:run` once a minute
            // (in terminate(), after the response) since this host's OS cron does
            // not reliably fire. Safe to keep even if a real cron is added later.
            \App\Http\Middleware\RunDueSchedule::class,
        ]);
        // Inbound webhooks authenticate via signature / path secret, not CSRF.
        $middleware->validateCsrfTokens(except: [
            'webhooks/stoqs',
            'webhooks/telegram/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        // Forward unhandled exceptions to Sentry when configured. Without
        // SENTRY_LARAVEL_DSN the SDK is a no-op, so this is safe to deploy
        // before the DSN is set.
        \Sentry\Laravel\Integration::handles($exceptions);

        // Also forward to the NJ Focus cross-app event hub (nj-focus-IR), so
        // racketclub.ir shows up alongside the other sites. Fire-and-forget and
        // fully guarded — a hub failure can never affect the response. No-op
        // when NJ_FOCUS_API_KEY is unset. Skips routine 4xx.
        $exceptions->report(function (\Throwable $e): void {
            try {
                $key = config('services.nj_focus.key');
                if (! $key) return;
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                    && $e->getStatusCode() < 500) return;

                $base = rtrim((string) config('services.nj_focus.base_url'), '/');
                \Illuminate\Support\Facades\Http::withHeaders(['X-API-Key' => $key])
                    ->timeout(3)
                    ->post($base . '/api/hub/log', [
                        'app'         => config('services.nj_focus.app', 'rc-clothing'),
                        'type'        => 'error',
                        'level'       => 'critical',
                        'title'       => class_basename($e) . ': ' . $e->getMessage(),
                        'message'     => \Illuminate\Support\Str::limit($e->getTraceAsString(), 4000),
                        'file'        => $e->getFile(),
                        'line'        => $e->getLine(),
                        'environment' => config('services.nj_focus.env'),
                    ]);
            } catch (\Throwable $ignore) {
                // reporting must never break error handling
            }
        });
    })->create();

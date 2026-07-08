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
    })->create();

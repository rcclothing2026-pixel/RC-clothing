<?php

namespace App\Support;

/**
 * Stable, secret token for the terminal-free HTTP scheduler trigger
 * (GET /cron/run/{token}). Derived from APP_KEY so it needs no extra env var
 * and never changes unless the app key is rotated. Shown to the admin on the
 * StoqS integration page so they can point a host cron (wget/curl) at the URL
 * on shared hosts that can't run `artisan schedule:run` from a CLI cron.
 */
class CronToken
{
    public static function value(): string
    {
        return substr(hash_hmac('sha256', 'scheduler-cron', (string) config('app.key')), 0, 40);
    }
}

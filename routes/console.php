<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler heartbeat: stamps the time every run so the admin can confirm the
// `schedule:run` cron is actually firing (and catch a broken/missing cron) —
// surfaced on the StoqS integration page. No-op work, just a timestamp.
Schedule::call(fn () => Cache::put('scheduler:last_run', now()->timestamp, now()->addDays(2)))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// IMPORTANT: these run via Schedule::call(Artisan::call(...)) — i.e. IN-PROCESS,
// exactly like the admin "manual sync" buttons — instead of Schedule::command,
// which spawns a subprocess via proc_open(). Many cPanel/shared hosts disable
// proc_open/exec, so Schedule::command jobs silently never launch there (while
// in-process closures like the heartbeat above still run). Running in-process is
// the same code path as the manual buttons, so it works wherever those work.

// Retry delivery of any stock-keeping events that didn't go through immediately.
Schedule::call(fn () => Artisan::call('stockkeeping:flush'))
    ->everyFiveMinutes()->name('sk-flush')->withoutOverlapping();

// Reconcile local stock from StoqS (authoritative, idempotent). Webhooks handle
// real-time; this guarantees the cache can't drift.
Schedule::call(fn () => Artisan::call('stockkeeping:pull', ['--source' => 'auto']))
    ->everyTenMinutes()->name('sk-pull')->withoutOverlapping();

// Import products flagged "Send to website" in StoqS (webhooks handle real-time).
// Full re-sync (not incremental) so a product's active status (enable/disable in
// StoqS) is reconciled even when only `active` changed — that toggle bumps no
// web_published_at cursor and fires no webhook, so an incremental import misses it.
Schedule::call(fn () => Artisan::call('stockkeeping:catalog', ['--full' => true, '--source' => 'auto']))
    ->hourly()->name('sk-catalog')->withoutOverlapping();

// Keep local collections in sync with StoqS.
Schedule::call(fn () => Artisan::call('stockkeeping:collections', ['--source' => 'auto']))
    ->daily()->name('sk-collections')->withoutOverlapping();

// Daily low-stock digest to Telegram admins.
Schedule::call(fn () => Artisan::call('alerts:low-stock'))->dailyAt('09:00')->name('sk-lowstock');

// Abandoned-cart recovery SMS (idle 1–48h carts).
Schedule::call(fn () => Artisan::call('cart:recover'))->hourly()->name('cart-recover')->withoutOverlapping();

// Abandoned-cart recovery email (complements the SMS above).
Schedule::call(fn () => Artisan::call('cart:recover-email'))->hourly()->name('cart-recover-email')->withoutOverlapping();

// Import search index from DB to Meilisearch (keeps Scout in sync).
Schedule::call(fn () => Artisan::call('scout:import', ['model' => \App\Models\Product::class]))
    ->hourly()->name('scout-products')->withoutOverlapping();
Schedule::call(fn () => Artisan::call('scout:import', ['model' => \App\Models\ProductVariant::class]))
    ->hourly()->name('scout-variants')->withoutOverlapping();

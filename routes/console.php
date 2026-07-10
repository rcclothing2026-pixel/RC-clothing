<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler heartbeat: stamps the time every run so the admin can confirm the
// scheduler is actually ticking — surfaced on the StoqS integration page.
Schedule::call(fn () => Cache::put('scheduler:last_run', now()->timestamp, now()->addDays(2)))
    ->everyMinute()
    ->name('scheduler-heartbeat');

// ELAPSED-TIME SCHEDULING (not clock-minute). Every reconciler below is
// registered ->everyMinute() but gated by $due(): it runs only when at least N
// minutes have passed since its last run. This is deliberate — the scheduler is
// driven both by any real cron AND by web traffic (App\Http\Middleware\
// RunDueSchedule), and traffic doesn't arrive on tidy :00/:10 boundaries. With a
// fixed everyTenMinutes() a task would run ONLY if a tick happened to land on the
// exact due minute; with $due() ANY tick after the interval elapses catches it up.
// So a single page load (or one cron hit) reconciles everything that's due.
//
// IMPORTANT: these run via Schedule::call(Artisan::call(...)) — IN-PROCESS, like
// the admin "manual sync" buttons — not Schedule::command (which needs proc_open,
// often disabled on shared hosts). $due marks BEFORE running so a failing task
// backs off for its interval instead of hammering every minute; the next window
// retries.
$due = function (string $key, int $minutes): bool {
    $k = 'sched:last:'.$key;
    if (now()->timestamp - (int) Cache::get($k, 0) < $minutes * 60) {
        return false;
    }
    Cache::put($k, now()->timestamp, now()->addDays(2));

    return true;
};

// Retry delivery of any stock-keeping events that didn't go through immediately.
Schedule::call(function () use ($due) {
    if ($due('flush', 5)) {
        Artisan::call('stockkeeping:flush');
    }
})->everyMinute()->name('sk-flush')->withoutOverlapping();

// Reconcile local stock from StoqS (authoritative, idempotent). Webhooks handle
// real-time; this guarantees the cache can't drift.
Schedule::call(function () use ($due) {
    if ($due('pull', 10)) {
        Artisan::call('stockkeeping:pull', ['--source' => 'auto']);
    }
})->everyMinute()->name('sk-pull')->withoutOverlapping();

// Import products flagged "Send to website" in StoqS (webhooks handle real-time).
// Full re-sync (not incremental) so a product's active status (enable/disable in
// StoqS) is reconciled even when only `active` changed — that toggle bumps no
// web_published_at cursor and fires no webhook, so an incremental import misses it.
Schedule::call(function () use ($due) {
    if ($due('catalog', 60)) {
        Artisan::call('stockkeeping:catalog', ['--full' => true, '--source' => 'auto']);
    }
})->everyMinute()->name('sk-catalog')->withoutOverlapping();

// Keep local collections in sync with StoqS.
Schedule::call(function () use ($due) {
    if ($due('collections', 1440)) {
        Artisan::call('stockkeeping:collections', ['--source' => 'auto']);
    }
})->everyMinute()->name('sk-collections')->withoutOverlapping();

// Daily low-stock digest to Telegram admins.
Schedule::call(function () use ($due) {
    if ($due('lowstock', 1440)) {
        Artisan::call('alerts:low-stock');
    }
})->everyMinute()->name('sk-lowstock')->withoutOverlapping();

// Abandoned-cart recovery SMS (idle 1–48h carts).
Schedule::call(function () use ($due) {
    if ($due('cart-recover', 60)) {
        Artisan::call('cart:recover');
    }
})->everyMinute()->name('cart-recover')->withoutOverlapping();

// Abandoned-cart recovery email (complements the SMS above).
Schedule::call(function () use ($due) {
    if ($due('cart-recover-email', 60)) {
        Artisan::call('cart:recover-email');
    }
})->everyMinute()->name('cart-recover-email')->withoutOverlapping();

// Reconcile the Scout search index. Uses the model API (makeAllSearchable)
// instead of the `scout:import` Artisan command: Scout registers that command
// ONLY in console context, but the scheduler runs in-process from a web request
// (soft-cron), where it doesn't exist — calling it there throws
// CommandNotFoundException. The Searchable trait already reindexes on every save
// (catalog import, admin edits), so this is just a periodic safety reconcile;
// swallow errors so a search-driver hiccup can't spam the error log.
Schedule::call(function () use ($due) {
    if (! $due('scout-reindex', 60)) {
        return;
    }
    try {
        \App\Models\Product::makeAllSearchable();
        \App\Models\ProductVariant::makeAllSearchable();
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('[scheduler] scout reindex failed', ['error' => $e->getMessage()]);
    }
})->everyMinute()->name('scout-reindex')->withoutOverlapping();

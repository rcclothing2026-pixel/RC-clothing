<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cron-less scheduler: runs `schedule:run` in-process, at most once a minute,
 * kicked by ordinary web traffic. This host's OS cron does not reliably execute
 * jobs (every command form failed identically while the web path always worked),
 * so the site drives its own scheduler instead — the same in-process path the
 * /cron/run endpoint uses.
 *
 * The work runs in terminate() (after the response is flushed to the client),
 * so it never adds latency to the page. An atomic cache lock throttles it to one
 * run per ~minute no matter how much traffic arrives; scheduled tasks that are
 * heavier carry their own withoutOverlapping() guard. A real OS cron, if it ever
 * starts firing, is harmless alongside this — schedule:run is idempotent.
 */
class RunDueSchedule
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        // Skip the dedicated cron endpoint (it already runs the scheduler) to
        // avoid a redundant back-to-back run.
        if ($request->is('cron/run/*')) {
            return;
        }

        try {
            // TTL 58s: the lock auto-expires just under a minute later, so the
            // next request after ~a minute triggers the next run. Never released,
            // so bursts of traffic can't run it more than once per window.
            if (Cache::lock('scheduler:web-kick', 58)->get()) {
                Artisan::call('schedule:run');
            }
        } catch (\Throwable $e) {
            // The response is already sent; a scheduler hiccup must never surface
            // to the visitor. Log and move on — the next request retries.
            Log::warning('[web-scheduler] schedule:run failed', ['error' => $e->getMessage()]);
        }
    }
}

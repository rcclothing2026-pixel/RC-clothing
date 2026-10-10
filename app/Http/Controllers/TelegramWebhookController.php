<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramNotifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Telegram bot updates: /start (subscriber registration) and the
 * order status button callbacks. The secret in the URL path ensures only
 * Telegram (which we gave the URL to via setWebhook) can post here.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, TelegramNotifier $tg): Response
    {
        if (! $tg->enabled() || ! hash_equals($tg->webhookSecret(), $secret)) {
            return response('forbidden', 403);
        }

        $update = $request->all();

        // Updates arrive via the Google Apps Script relay, whose /exec endpoint
        // always answers Telegram with a 302 — so Telegram treats every delivery
        // as failed and re-sends the SAME update on retry. Process each update_id
        // only once so retries don't double-react (e.g. a second status change).
        $updateId = $update['update_id'] ?? null;
        if ($updateId !== null && ! Cache::add('tg:update:'.$updateId, true, now()->addHours(6))) {
            return response('ok', 200);
        }

        try {
            $tg->handleUpdate($update);
        } catch (\Throwable $e) {
            report($e);
        }

        return response('ok', 200); // always 200 so Telegram doesn't retry-storm
    }
}

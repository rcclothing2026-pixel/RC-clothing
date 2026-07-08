<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use App\Models\Order;
use App\Models\Setting;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\NullStockKeepingClient;
use App\Services\StockKeeping\StockKeepingClient;
use App\Services\StockKeeping\StockKeepingReporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * StoqS integration control panel: connection settings, the location picker
 * (mirror + fulfilment), one-click catalog/stock sync, and live status.
 */
class IntegrationController extends Controller
{
    public function edit(): View
    {
        $recentLogs = StockkeeepingLog::whereIn('event_type', [
            StockkeeepingLog::TYPE_CATALOG_IMPORT,
            StockkeeepingLog::TYPE_COLLECTIONS_IMPORT,
            StockkeeepingLog::TYPE_STOCK_PULL,
        ])->latest()->limit(20)->get();

        return view('admin.settings.integration', [
            'cfg' => $this->config(),
            'locations' => Cache::get('stockkeeping:locations', []),
            'recentLogs' => $recentLogs,
            'outbox' => IntegrationEvent::whereIn('status', ['pending', 'failed'])
                ->orderBy('id')->limit(50)->get(),
            'lastSyncs' => [
                'catalog' => StockkeeepingLog::where('event_type', StockkeeepingLog::TYPE_CATALOG_IMPORT)->where('status', 'ok')->latest()->first(),
                'collections' => StockkeeepingLog::where('event_type', StockkeeepingLog::TYPE_COLLECTIONS_IMPORT)->where('status', 'ok')->latest()->first(),
                'stock' => StockkeeepingLog::where('event_type', StockkeeepingLog::TYPE_STOCK_PULL)->where('status', 'ok')->latest()->first(),
            ],
            'status' => [
                'last_pull' => Cache::get('stockkeeping:last_pull'),
                'last_catalog' => Cache::get('stockkeeping:catalog_since'),
                'outbox_pending' => IntegrationEvent::whereIn('status', ['pending', 'failed'])->count(),
                'outbox_sent' => IntegrationEvent::where('status', 'sent')->count(),
                // Heartbeat written every minute by the schedule:run cron. If it's
                // stale/missing, the cron isn't running → auto-sync is off.
                'scheduler_last_run' => Cache::get('scheduler:last_run'),
            ],
            // Sales StoqS rejected because an ordered item doesn't exist there yet
            // (sku_not_found). The whole sale is rejected atomically, so it keeps
            // retrying forever — surface it so the admin can create/link the
            // product in StoqS, after which the next flush delivers the sale.
            'unmatchedSales' => IntegrationEvent::where('type', IntegrationEvent::TYPE_SALE_CREATED)
                ->where('status', 'failed')
                ->where(fn ($q) => $q->where('last_error', 'like', '%sku_not_found%')
                    ->orWhere('last_error', 'like', '%SKU not found%'))
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($e) => [
                    'order_number' => $e->payload['order_number'] ?? '—',
                    'items' => collect($e->payload['items'] ?? [])
                        ->map(fn ($i) => trim(($i['name'] ?? '?').' '.($i['size'] ?? '').' '.($i['color'] ?? '').(($i['sku'] ?? '') ? ' · '.$i['sku'] : '')))
                        ->all(),
                    'attempts' => (int) $e->attempts,
                    'error' => (string) $e->last_error,
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'import_in_stock_only' => ['nullable', 'boolean'],
            'base_url' => ['nullable', 'string', 'max:200'],
            'api_key' => ['nullable', 'string', 'max:200'],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['string', 'max:40'],
            'fulfillment_location' => ['nullable', 'string', 'max:40'],
            'webhook_secret' => ['nullable', 'string', 'max:120'],
        ]);

        $values = [
            'stockkeeping.enabled' => $request->boolean('enabled'),
            'stockkeeping.import_in_stock_only' => $request->boolean('import_in_stock_only'),
            'stockkeeping.base_url' => $v['base_url'] ?? null,
            'stockkeeping.locations' => array_values($v['locations'] ?? []),
            'stockkeeping.fulfillment_location' => $v['fulfillment_location'] ?? null,
        ];
        // Only overwrite secrets when a new value is actually entered.
        if (! empty($v['api_key'])) {
            $values['stockkeeping.api_key'] = $v['api_key'];
        }
        if (! empty($v['webhook_secret'])) {
            $values['stockkeeping.webhook_secret'] = $v['webhook_secret'];
        }
        Setting::putMany($values);

        return back()->with('success', 'تنظیمات اتصال به StoqS ذخیره شد.');
    }

    /** Refresh the warehouse/shop directory from StoqS for the picker. */
    public function locations(StockKeepingClient $client): RedirectResponse
    {
        if ($client instanceof NullStockKeepingClient) {
            return back()->with('error', 'ابتدا اتصال را فعال و کلید را وارد کنید.');
        }
        try {
            $names = [];
            $list = $client->listLocations();
            foreach ($list as $loc) {
                if (! empty($loc['token'])) {
                    $names[$loc['token']] = $loc['name'] ?? $loc['token'];
                }
            }
            Cache::put('stockkeeping:locations', $names, now()->addDay());

            return back()->with('success', count($names).' مکان از StoqS دریافت شد.');
        } catch (\Throwable $e) {
            return back()->with('error', 'دریافت مکان‌ها ناموفق بود: '.$e->getMessage());
        }
    }

    public function importCatalog(): RedirectResponse
    {
        Artisan::call('stockkeeping:catalog', ['--full' => true, '--source' => 'manual']);

        return back()->with('success', trim(Artisan::output()) ?: 'وارد کردن کاتالوگ انجام شد.');
    }

    public function importCollections(): RedirectResponse
    {
        Artisan::call('stockkeeping:collections', ['--source' => 'manual']);

        return back()->with('success', trim(Artisan::output()) ?: 'وارد کردن مجموعه‌ها انجام شد.');
    }

    public function pullStock(): RedirectResponse
    {
        Artisan::call('stockkeeping:pull', ['--full' => true, '--source' => 'manual']);

        return back()->with('success', trim(Artisan::output()) ?: 'همگام‌سازی موجودی انجام شد.');
    }

    /**
     * Re-attempt every queued/failed StoqS push now. Sale events get their payload
     * rebuilt from the order's CURRENT state first (so a just-added barcode / colour
     * mapping is picked up), then the whole outbox is flushed.
     */
    public function retryOutbox(StockKeepingReporter $reporter): RedirectResponse
    {
        $events = IntegrationEvent::whereIn('status', ['pending', 'failed'])->get();
        $before = $events->count();
        if ($before === 0) {
            return back()->with('success', 'صف ارسال خالی است — چیزی برای تلاش مجدد نیست.');
        }

        foreach ($events as $e) {
            $attrs = ['status' => 'pending', 'available_at' => now(), 'attempts' => 0];
            if ($e->type === IntegrationEvent::TYPE_SALE_CREATED && ! empty($e->payload['order_number'])) {
                $order = Order::where('number', $e->payload['order_number'])->first();
                if ($order) {
                    $attrs['payload'] = $reporter->buildSalePayload($order);
                }
            }
            $e->update($attrs);
        }

        $reporter->flushPending(200);
        $remaining = IntegrationEvent::whereIn('status', ['pending', 'failed'])->count();
        $done = max(0, $before - $remaining);
        $msg = "تلاش مجدد برای {$before} مورد: {$done} ارسال شد، {$remaining} در صف باقی ماند.";

        return back()->with($remaining === 0 ? 'success' : 'error', $msg);
    }

    /**
     * Drop a stuck push from the outbox so it stops retrying forever — for pushes
     * that can never succeed (a website-only product with no StoqS counterpart, a
     * test order, etc.). Marks it 'dismissed' so it leaves the pending/failed set.
     */
    public function dismissOutbox(IntegrationEvent $event): RedirectResponse
    {
        $event->update(['status' => 'dismissed', 'processed_at' => now()]);

        return back()->with('success', 'مورد از صف ارسال حذف شد (دیگر تلاش نمی‌شود).');
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'enabled' => (bool) config('stockkeeping.enabled'),
            'import_in_stock_only' => (bool) config('stockkeeping.import_in_stock_only', true),
            'base_url' => (string) config('stockkeeping.base_url'),
            'has_key' => (bool) config('stockkeeping.api_key'),
            'has_secret' => (bool) config('stockkeeping.webhook_secret'),
            'locations' => (array) config('stockkeeping.locations', []),
            'fulfillment_location' => (string) config('stockkeeping.fulfillment_location'),
        ];
    }
}

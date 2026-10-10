<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Authoritative stock reconciler: pulls aggregated on-hand across the configured
 * StoqS locations and writes it onto the local stock cache (matched by barcode =
 * product_variants.sku). Idempotent — safe to run on a schedule. Webhooks lower
 * latency; this guarantees correctness.
 *
 *   php artisan stockkeeping:pull            incremental (since last pull)
 *   php artisan stockkeeping:pull --full     ignore the cursor, pull everything
 */
class PullStockKeeping extends Command
{
    protected $signature = 'stockkeeping:pull {--full : Ignore the since-cursor and pull all items} {--source=auto : manual|auto}';

    protected $description = 'Reconcile local stock from StoqS across the configured locations';

    public function handle(StockKeepingClient $client): int
    {
        if (! config('stockkeeping.enabled')) {
            $this->info('StoqS integration disabled — nothing to pull.');
            return self::SUCCESS;
        }

        $startedAt = Carbon::now();
        $ref = $this->option('full') ? 'full' : 'incremental';

        // Refresh the location directory (token => name) for order display + UI.
        try {
            $names = [];
            foreach ($client->listLocations() as $loc) {
                if (! empty($loc['token'])) {
                    $names[$loc['token']] = $loc['name'] ?? $loc['token'];
                }
            }
            if ($names) {
                Cache::put('stockkeeping:locations', $names, now()->addDay());
            }
        } catch (\Throwable $e) {
            $this->warn('Could not refresh locations: '.$e->getMessage());
        }

        $since = $this->option('full') ? null : Cache::get('stockkeeping:last_pull');

        $matched = 0;
        $unmatchedItems = [];
        try {
            $items = $client->inventory($since);
        } catch (\Throwable $e) {
            $duration = $startedAt->diffInMilliseconds(now());
            $this->error('Inventory pull failed: '.$e->getMessage());
            StockkeeepingLog::record(
                StockkeeepingLog::TYPE_STOCK_PULL, 'in', $ref, null, ['error' => $e->getMessage()],
                'failed', null, $e->getMessage(), source: $this->option('source'), durationMs: $duration,
            );
            return self::FAILURE;
        }

        foreach ($items as $row) {
            $barcode = (string) ($row['barcode'] ?? $row['sku'] ?? '');
            if ($barcode === '') {
                continue;
            }

            $variant = ProductVariant::where('sku', $barcode)->first();
            if (! $variant) {
                $unmatchedItems[] = [
                    'barcode' => $barcode,
                    'product' => $row['product'] ?? ($row['product_name'] ?? '?'),
                ];
                continue;
            }

            $variant->stock_qty = max(0, (int) ($row['stock'] ?? 0));
            if (empty($variant->stockkeeping_variant_id) && ! empty($row['variant_id'])) {
                $variant->stockkeeping_variant_id = (int) $row['variant_id'];
            }
            $variant->save();

            Product::where('id', $variant->product_id)->update(['stock_synced_at' => now()]);
            $matched++;
        }

        Cache::put('stockkeeping:last_pull', $startedAt->copy()->subMinute()->toDateString(), now()->addYears(5));

        $duration = $startedAt->diffInMilliseconds(now());
        $unmatched = count($unmatchedItems);
        $summary = "{$matched} به‌روز شد · {$unmatched} ناشناخته · ".count($items)." کل";
        $this->info($summary);

        StockkeeepingLog::record(
            StockkeeepingLog::TYPE_STOCK_PULL, 'in', $ref, null,
            ['matched' => $matched, 'unmatched' => $unmatched, 'total' => count($items), 'unmatched_items' => $unmatchedItems],
            source: $this->option('source'), summary: $summary, durationMs: $duration,
        );

        return self::SUCCESS;
    }
}

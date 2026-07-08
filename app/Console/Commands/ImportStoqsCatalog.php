<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockkeeepingLog;
use App\Services\StockKeeping\CatalogImporter;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Import/refresh products flagged "Send to website" in StoqS.
 *
 *   php artisan stockkeeping:catalog          incremental (since last import)
 *   php artisan stockkeeping:catalog --full   re-import everything published
 */
class ImportStoqsCatalog extends Command
{
    protected $signature = 'stockkeeping:catalog {--full : Re-import all published products} {--source=auto : manual|auto}';

    protected $description = 'Create/update local products from StoqS published catalog';

    public function handle(StockKeepingClient $client, CatalogImporter $importer): int
    {
        if (! config('stockkeeping.enabled')) {
            $this->info('StoqS integration disabled — nothing to import.');
            return self::SUCCESS;
        }

        $since = $this->option('full') ? null : Cache::get('stockkeeping:catalog_since');
        $startedAt = Carbon::now();

        try {
            $products = $client->catalog($since);
        } catch (\Throwable $e) {
            $this->error('Catalog pull failed: '.$e->getMessage());
            StockkeeepingLog::record(
                StockkeeepingLog::TYPE_CATALOG_IMPORT, 'in', $this->option('full') ? 'full' : 'incremental',
                null, ['error' => $e->getMessage()], 'failed', null, $e->getMessage(),
                source: $this->option('source'),
            );
            return self::FAILURE;
        }

        // Only import products that currently have stock in StoqS (toggle in
        // admin → StoqS settings). Stock is read from the inventory endpoint and
        // matched by StoqS variant id, falling back to barcode/sku.
        $inStockOnly = (bool) config('stockkeeping.import_in_stock_only', true);
        $stockMap = ['vid' => [], 'bc' => []];
        if ($inStockOnly) {
            try {
                foreach ($client->inventory() as $r) {
                    $st = (int) ($r['stock'] ?? 0);
                    if (! empty($r['variant_id'])) {
                        $stockMap['vid'][(string) $r['variant_id']] = $st;
                    }
                    $bc = (string) ($r['barcode'] ?? $r['sku'] ?? '');
                    if ($bc !== '') {
                        $stockMap['bc'][$bc] = $st;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('  ⚠ نتوانستم موجودی را از StoqS بخوانم — این بار همهٔ محصولات وارد می‌شوند: '.$e->getMessage());
                $inStockOnly = false;
            }
        }

        $count = 0;
        $errors = 0;
        $skipped = 0;
        $failed = [];
        foreach ($products as $row) {
            // The in-stock filter only gates CREATING new products (avoid importing
            // never-stocked clutter). Products that already exist locally must still
            // be UPDATED so enable/disable (and price, etc.) keep syncing both ways
            // — critical because a disabled product is absent from the inventory
            // feed, so it always looks "no stock" here and would otherwise be skipped
            // and never get is_active=false on the site.
            if ($inStockOnly && ! $this->rowHasStock($row, $stockMap)) {
                $skId = (int) ($row['product_id'] ?? 0);
                if (! $skId || ! Product::where('stockkeeping_id', $skId)->exists()) {
                    $skipped++;
                    continue;
                }
            }
            try {
                $product = $importer->importProduct($row);
                $this->line("  ✓ {$product->name} (#{$product->stockkeeping_id}) — ".count($row['variants'] ?? [])." variants");
                $count++;
            } catch (\Throwable $e) {
                $name = $row['name'] ?? '?';
                $this->warn('  ✗ '.$name.': '.$e->getMessage());
                $errors++;
                $failed[] = ['name' => $name, 'error' => $e->getMessage()];
            }
        }

        Cache::put('stockkeeping:catalog_since', $startedAt->copy()->subMinute()->toDateString(), now()->addYears(5));

        $duration = $startedAt->diffInMilliseconds(now());
        $summary = "{$count} محصول وارد/به‌روز شد"
            .($skipped ? "، {$skipped} بدون موجودی رد شد" : '')
            .($errors ? "، {$errors} خطا" : '');
        $this->info($summary);

        StockkeeepingLog::record(
            StockkeeepingLog::TYPE_CATALOG_IMPORT, 'in', $this->option('full') ? 'full' : 'incremental',
            null, ['imported' => $count, 'skipped_no_stock' => $skipped, 'errors' => $errors, 'total' => count($products), 'failed' => $failed],
            'ok', null, null,
            source: $this->option('source'), summary: $summary, durationMs: $duration,
        );

        return self::SUCCESS;
    }

    /**
     * True if any variant of this StoqS product currently has stock > 0.
     * Reads stock from the product/variant payload when present, else from the
     * inventory map (by StoqS variant id, then barcode/sku).
     *
     * @param  array<string, mixed>  $row
     * @param  array{vid: array<string,int>, bc: array<string,int>}  $map
     */
    private function rowHasStock(array $row, array $map): bool
    {
        if (isset($row['stock']) && (int) $row['stock'] > 0) {
            return true;
        }

        foreach ($row['variants'] ?? [] as $v) {
            $st = null;
            if (isset($v['stock'])) {
                $st = (int) $v['stock'];
            }
            if ($st === null && ! empty($v['variant_id'])) {
                $st = $map['vid'][(string) $v['variant_id']] ?? null;
            }
            if ($st === null) {
                $bc = (string) ($v['barcode'] ?? $v['sku'] ?? '');
                if ($bc !== '') {
                    $st = $map['bc'][$bc] ?? null;
                }
            }
            if ((int) $st > 0) {
                return true;
            }
        }

        return false;
    }
}

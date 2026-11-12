<?php

namespace App\Services\StockKeeping;

use App\Models\Coupon;
use App\Models\IntegrationEvent;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockkeeepingLog;

/**
 * Tells StoqS where each product lives on this shop: POST /sites/hello, then
 * POST /sites/links (StoqS's product card links here). Runs daily and after the
 * hourly full catalog import. Also re-tries outbox rows a missing scope parked
 * («blocked») — at most once a day, so a key without the scope never retry-storms —
 * and registers coupons that were never sent to StoqS.
 */
class StoqsSites
{
    public const CAPABILITIES = ['stock', 'sales', 'content', 'images', 'prices', 'create_products', 'links', 'discount_report', 'codes', 'ping'];

    /**
     * $daily: also re-try «blocked» rows and register unsent coupons (the daily
     * run only; the hourly import sends just hello + links).
     *
     * @return string summary line
     */
    public static function sync(bool $daily = true): string
    {
        if (! config('stockkeeping.enabled')) {
            return 'StoqS خاموش است';
        }
        if ($daily) {
            IntegrationEvent::where('status', 'blocked')->update(['status' => 'pending', 'available_at' => now()]);
            foreach (Coupon::whereNull('stoqs_code_id')->whereNull('stoqs_error')->pluck('id') as $id) {
                StoqsCodes::enqueue((int) $id);
            }
        }

        try {
            StoqsApi::call('POST', 'sites/hello', [
                'kind' => 'laravel', 'url' => rtrim((string) config('app.url'), '/'),
                'plugin_version' => (string) config('stockkeeping.site_version', '1.0.0'), 'capabilities' => self::capabilities(),
            ] + (StoqsPromotions::managed() ? ['engine_schema' => 3] : []));
            $links = self::links();
            $count = 0;
            foreach (array_chunk($links, 5000) as $chunk) {
                $count += self::post($chunk);
            }
        } catch (StoqsApiError $e) {
            StockkeeepingLog::record(StockkeeepingLog::TYPE_SITES, 'out', 'links', null, ['code' => $e->errorCode], 'failed',
                error: $e->getMessage(), source: StockkeeepingLog::SOURCE_AUTO, summary: 'معرفی سایت به StoqS نشد: '.$e->getMessage());

            return 'خطا: '.$e->getMessage();
        }
        Setting::put('stockkeeping.sites_synced_at', now()->toIso8601String());
        $line = 'پیوند '.$count.' محصول به StoqS فرستاده شد';
        StockkeeepingLog::record(StockkeeepingLog::TYPE_SITES, 'out', 'links', null, ['count' => $count],
            source: StockkeeepingLog::SOURCE_AUTO, summary: $line);

        return $line;
    }

    /**
     * What hello reports. While StoqS manages this site's promotions the shop prices each size
     * from /promotions and runs the schema 2 cart engine, so it says so; otherwise the list is
     * the same as before.
     */
    public static function capabilities(): array
    {
        return StoqsPromotions::managed() ? [...self::CAPABILITIES, 'size_prices', 'engine_schema_2', 'engine_schema_3'] : self::CAPABILITIES;
    }

    /** One link per StoqS product behind each linked shop product. */
    public static function links(): array
    {
        $out = [];
        $root = rtrim((string) config('app.url'), '/');
        Product::whereNotNull('stockkeeping_id')->where('stockkeeping_id', '!=', '')->where('stoqs_sync_mode', '!=', 'ignore')
            ->select(['id', 'slug', 'stockkeeping_id', 'stoqs_meta'])->chunkById(500, function ($products) use (&$out, $root) {
                foreach ($products as $p) {
                    foreach (StoqsContent::pids($p) as $spid) {
                        $out[] = ['product_id' => $spid, 'site_product_id' => (string) $p->id, 'url' => $root.route('product.show', $p, false)];
                    }
                }
            });

        return $out;
    }

    /** Links whose StoqS product no longer exists are dropped and the rest sent again. */
    private static function post(array $links): int
    {
        try {
            return (int) (StoqsApi::call('POST', 'sites/links', ['links' => $links])['count'] ?? count($links));
        } catch (StoqsApiError $e) {
            $bad = array_map('intval', (array) ($e->body['error']['product_ids'] ?? []));
            if ($e->errorCode !== 'unknown_product' || ! $bad) {
                throw $e;
            }
            $links = array_values(array_filter($links, fn ($l) => ! in_array((int) $l['product_id'], $bad, true)));

            return $links ? (int) (StoqsApi::call('POST', 'sites/links', ['links' => $links])['count'] ?? count($links)) : 0;
        }
    }
}

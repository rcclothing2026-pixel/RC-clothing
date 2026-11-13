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
    public static function sync(bool $daily = true, bool $forceConnect = false): string
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
            self::hello($forceConnect);
            if ($daily) {
                self::pullLinks();   // links StoqS made (a kit it created): adopted before the full list goes up
            }
            $links = self::links();
            $count = 0;
            $chunks = array_chunk($links, 5000);
            foreach ($chunks as $chunk) {
                // the whole list in one call (daily): links this shop no longer has are dropped in StoqS
                $count += self::post($chunk, $daily && count($chunks) === 1);
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
     * POST /sites/hello. Carries `connect` {webhook_url, claim} while this shop has no webhook
     * secret, after StoqS refused a signature, or when forced («اتصال دوباره»); StoqS then pushes
     * site.connect to the webhook (StoqsConnect). Returns StoqS's answer.
     */
    public static function hello(bool $forceConnect = false): array
    {
        $connect = StoqsConnect::block($forceConnect);
        $res = StoqsApi::call('POST', 'sites/hello', [
            'kind' => 'laravel', 'url' => rtrim((string) config('app.url'), '/'),
            'plugin_version' => (string) config('stockkeeping.site_version', '1.0.0'), 'capabilities' => self::capabilities(),
        ] + (StoqsPromotions::managed() ? ['engine_schema' => 3] : []) + ($connect ? ['connect' => $connect] : []));
        if ($connect) {
            StoqsConnect::noteAnswer($res);
        }

        return $res;
    }

    /** The «اتصال دوباره» button: hello with `connect`, and what StoqS answered in Persian. */
    public static function reconnect(): array
    {
        if (! config('stockkeeping.enabled')) {
            return ['ok' => false, 'message' => 'اتصال StoqS خاموش است.'];
        }
        try {
            $res = self::hello(true);
        } catch (StoqsApiError $e) {
            return ['ok' => false, 'message' => 'اتصال به StoqS ناموفق بود: '.$e->getMessage()];
        }
        $c = (array) ($res['connect'] ?? []);
        switch ((string) ($c['status'] ?? '')) {
            case 'pending':
                return ['ok' => true, 'message' => 'درخواست اتصال فرستاده شد. چند لحظه دیگر همین صفحه را دوباره باز کنید.'];
            case 'approval_needed':
                return ['ok' => true, 'message' => 'منتظر تأیید در StoqS: مدیر حساب باید در StoqS روی «تأیید این سایت» بزند.'];
            case 'refused':
                $why = match ((string) ($c['reason'] ?? '')) {
                    'bad_webhook_url' => 'آدرس وبهوک این سایت پذیرفته نشد (باید https و روی همین دامنه باشد).',
                    'rate_limited' => 'درخواست‌ها زیاد بود؛ کمی بعد دوباره امتحان کنید.',
                    'not_ready' => 'StoqS هنوز آمادهٔ اتصال نیست.',
                    default => 'StoqS اتصال را نپذیرفت.',
                };

                return ['ok' => false, 'message' => $why];
        }

        return ['ok' => true, 'message' => 'ارتباط با StoqS برقرار است.'];
    }

    /** GET /sites/links: the links StoqS made for this shop are adopted (a local product with no StoqS id yet). */
    private static function pullLinks(): void
    {
        try {
            $resp = StoqsApi::client()->get('sites/links', ['site_url' => StoqsPromotions::siteUrl()]);
            if (! $resp->successful()) {
                return;
            }
            foreach ((array) $resp->json('links', []) as $l) {
                if (is_array($l) && ($l['origin'] ?? '') === 'stoqs') {
                    StoqsLinks::adopt($l);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[stoqs-sites] links pull failed', ['error' => $e->getMessage()]);
        }
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
    private static function post(array $links, bool $replaceAll = false): int
    {
        $extra = $replaceAll ? ['replace_all' => true] : [];
        try {
            return (int) (StoqsApi::call('POST', 'sites/links', ['links' => $links] + $extra)['count'] ?? count($links));
        } catch (StoqsApiError $e) {
            $bad = array_map('intval', (array) ($e->body['error']['product_ids'] ?? []));
            if ($e->errorCode !== 'unknown_product' || ! $bad) {
                throw $e;
            }
            $links = array_values(array_filter($links, fn ($l) => ! in_array((int) $l['product_id'], $bad, true)));

            return $links ? (int) (StoqsApi::call('POST', 'sites/links', ['links' => $links] + $extra)['count'] ?? count($links)) : 0;
        }
    }
}

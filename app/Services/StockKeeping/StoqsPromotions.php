<?php

namespace App\Services\StockKeeping;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockkeeepingLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions managed by StoqS (Promotions API v2, GET /api/v1/promotions).
 *
 * StoqS decides per connected site who runs its promotions: «مدیریت با StoqS» or
 * «مدیریت با فروشگاه» (or not chosen yet). The answer arrives as `managed` in every
 * /promotions pull and is kept in the one-row table stoqs_promotions.
 *
 * Not managed (no row, unset or shop): nothing here does anything and the shop runs
 * its own discount rules, coupons and outlet exactly as before.
 *
 * Managed:
 *  - apply(): every size sells at StoqS's window price at this moment (the lowest window
 *    price covering now, else its list price), with the list price struck through
 *    (products.compare_at_price). A size whose price differs from what its product shows
 *    carries it in product_variants.promo_price (ProductVariant::effectivePrice). It runs
 *    every minute (stoqs:promotions-sync), so a window starts and ends on time without
 *    a webhook.
 *  - the cart runs StoqS's engine (StoqsEngine) on the payload's rules and codes, and the
 *    checkout asks POST /promotions/evaluate; the shop's own rules, coupons and outlet
 *    are set aside (not changed), so switching back restores them as they were.
 *  - ack(): the version applied is reported (POST /promotions/ack).
 *  - reportSitePrices(): a size priced in the shop is reported
 *    (PUT /catalog/variants/{id}/site-price) so StoqS prices this site's windows from it.
 */
class StoqsPromotions
{
    private const ATTR = 'stoqs.promotions';

    /** Products whose prices the shop keeps for itself (StoqsContent sync modes). */
    private const OWN_PRICES = ['ignore', 'stock_only', 'manual'];

    // ── state ───────────────────────────────────────────────────────────────

    /** The stored row (memoised per request); null before the first pull or the migration. */
    public static function row(): ?object
    {
        $attrs = request()->attributes;
        if ($attrs->has(self::ATTR)) {
            return $attrs->get(self::ATTR);
        }
        $row = null;
        try {
            if (Schema::hasTable('stoqs_promotions')) {
                $row = DB::table('stoqs_promotions')->where('id', 1)->first();
            }
        } catch (\Throwable) {
            $row = null;
        }
        $attrs->set(self::ATTR, $row);

        return $row;
    }

    /** Drop the per-request memo (after a pull, and in tests). */
    public static function forget(): void
    {
        foreach (['', '.payload', '.index', '.windows', '.outlet', '.cats'] as $k) {
            request()->attributes->remove(self::ATTR.$k);
        }
        request()->attributes->remove('outlet.percents');
    }

    /** Whether StoqS runs this site's promotions. False whenever the StoqS link is off. */
    public static function managed(): bool
    {
        return (bool) config('stockkeeping.enabled') && (bool) (self::row()->managed ?? false);
    }

    /** The last /promotions payload (decoded), [] without one. */
    public static function payload(): array
    {
        return self::memo('.payload', function () {
            $raw = self::row()->payload ?? null;
            $j = $raw ? json_decode((string) $raw, true) : null;

            return is_array($j) ? $j : [];
        });
    }

    public static function settings(): array
    {
        return (array) (self::payload()['settings'] ?? []);
    }

    public static function version(): ?int
    {
        $v = self::payload()['version'] ?? null;

        return $v === null ? null : (int) $v;
    }

    /** The URL this shop introduces itself with (StoqsSites::sync), so StoqS prices for this site. */
    public static function siteUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    // ── pull ────────────────────────────────────────────────────────────────

    /**
     * GET /promotions with If-None-Match. A 304 changes nothing; a 200 stores the payload.
     * When `managed` flips either way, every product is priced again from StoqS's catalog
     * (stockkeeping:catalog --full), so list prices and the shop's own outlet come back
     * exactly as the import would set them in that mode.
     *
     * @return array{ok: bool, status: int|string, changed: bool, managed: bool, flipped: bool, message?: string}
     */
    public static function pull(bool $reimportOnFlip = true): array
    {
        if (! config('stockkeeping.enabled')) {
            return ['ok' => false, 'status' => 'off', 'changed' => false, 'managed' => false, 'flipped' => false];
        }
        $row = self::row();
        $was = (bool) ($row->managed ?? false);
        $headers = ['Accept-Encoding' => 'gzip'];
        if ($row && $row->etag && $row->payload !== null) {
            $headers['If-None-Match'] = (string) $row->etag;
        }
        try {
            $resp = StoqsApi::client()->withHeaders($headers)->timeout(20)->get('promotions', ['site_url' => self::siteUrl()]);
        } catch (\Throwable $e) {
            self::store(['error' => mb_substr($e->getMessage(), 0, 500)]);

            return ['ok' => false, 'status' => 'error', 'changed' => false, 'managed' => self::managed(), 'flipped' => false, 'message' => $e->getMessage()];
        }
        if ($resp->status() === 304) {
            self::store(['pulled_at' => now(), 'error' => null]);

            return ['ok' => true, 'status' => 304, 'changed' => false, 'managed' => self::managed(), 'flipped' => false];
        }
        if (! $resp->successful() || ! is_array($resp->json())) {
            $e = StoqsApi::failure($resp, 'discounts:read');
            self::store(['error' => mb_substr('HTTP '.$resp->status().' '.$e->getMessage(), 0, 500)]);

            return ['ok' => false, 'status' => $resp->status(), 'changed' => false, 'managed' => self::managed(), 'flipped' => false, 'message' => $e->getMessage()];
        }
        $j = (array) $resp->json();
        $managed = ! empty($j['managed']);
        self::store([
            'managed' => $managed,
            'promo_source' => mb_substr((string) ($j['promo_source'] ?? ''), 0, 20) ?: null,
            'price_level' => mb_substr((string) ($j['price_level'] ?? ''), 0, 10) ?: null,
            'version' => isset($j['version']) ? (int) $j['version'] : null,
            'etag' => mb_substr((string) $resp->header('ETag'), 0, 100) ?: null,
            'payload' => $resp->body(),
            'pulled_at' => now(),
            'error' => null,
        ]);
        $flipped = $was !== $managed;
        if ($flipped) {
            self::flipped($managed, $reimportOnFlip);
        }

        return ['ok' => true, 'status' => 200, 'changed' => true, 'managed' => $managed, 'flipped' => $flipped];
    }

    /** Write the row (id 1) and drop the memo. */
    private static function store(array $values): void
    {
        try {
            if (! Schema::hasTable('stoqs_promotions')) {
                return;
            }
            $values['updated_at'] = now();
            if (DB::table('stoqs_promotions')->where('id', 1)->exists()) {
                DB::table('stoqs_promotions')->where('id', 1)->update($values);
            } else {
                DB::table('stoqs_promotions')->insert($values + ['id' => 1, 'created_at' => now()]);
            }
        } finally {
            self::forget();
        }
    }

    /**
     * StoqS took the promotions over, or gave them back. promo_price is cleared and the
     * whole catalog imported again in the new mode (list prices only when managed; the
     * StoqS sale price and the shop's own outlet when not), then the windows applied.
     */
    private static function flipped(bool $managed, bool $reimport): void
    {
        ProductVariant::whereNotNull('promo_price')->update(['promo_price' => null]);
        StockkeeepingLog::record(StockkeeepingLog::TYPE_SITES, 'in', 'promotions', null, ['managed' => $managed],
            source: StockkeeepingLog::SOURCE_AUTO,
            summary: $managed ? 'تخفیف‌های این سایت از این پس در StoqS مدیریت می‌شود' : 'تخفیف‌های این سایت دوباره با خود فروشگاه است');
        if ($reimport) {
            try {
                \Illuminate\Support\Facades\Artisan::call('stockkeeping:catalog', ['--full' => true, '--source' => 'auto']);
            } catch (\Throwable $e) {
                Log::warning('[stoqs-promotions] catalog re-import after the switch failed', ['error' => $e->getMessage()]);
            }
        }
    }

    // ── the payload, indexed ───────────────────────────────────────────────

    /** windows[] by id, each with from/to as UTC timestamps (null = open). */
    public static function windows(): array
    {
        return self::memo('.windows', function () {
            $out = [];
            foreach ((array) (self::payload()['windows'] ?? []) as $w) {
                if (! is_array($w) || ! isset($w['id'])) {
                    continue;
                }
                $w['_from'] = self::ts($w['from_utc'] ?? $w['from'] ?? null);
                $w['_to'] = self::ts($w['to_utc'] ?? $w['to'] ?? null);
                $out[(string) $w['id']] = $w;
            }

            return $out;
        });
    }

    /** variants[] rows by StoqS variant id. */
    public static function index(): array
    {
        return self::memo('.index', function () {
            $out = [];
            foreach ((array) (self::payload()['variants'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                foreach ((array) ($row['variant_ids'] ?? []) as $vid) {
                    $out[(int) $vid] = $row;
                }
            }

            return $out;
        });
    }

    /** StoqS product id => its StoqS category ids (the payload's categories map, inverted). */
    public static function categoriesOf(): array
    {
        return self::memo('.cats', function () {
            $out = [];
            foreach ((array) (self::payload()['categories'] ?? []) as $cid => $pids) {
                foreach ((array) $pids as $pid) {
                    $out[(int) $pid][] = (int) $cid;
                }
            }

            return $out;
        });
    }

    private static function ts($iso): ?int
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $iso)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function covers(array $w, int $ts): bool
    {
        return ($w['_from'] === null || $w['_from'] <= $ts) && ($w['_to'] === null || $ts < $w['_to']);
    }

    /**
     * One size's price at $ts: [price, window] with the lowest window price covering $ts, or
     * [null, null] when none does (it sells at its list price). A size the site prices
     * itself and has not reported yet has no price from StoqS ([window id, null, pct]): the
     * published formula prices it from $list (formulaPrice).
     */
    public static function priceAt(array $row, int $list, int $ts): array
    {
        $wins = self::windows();
        $best = null;
        $win = null;
        foreach ((array) ($row['prices'] ?? []) as $e) {
            $w = $wins[(string) ($e[0] ?? '')] ?? null;
            if (! $w || ! self::covers($w, $ts)) {
                continue;
            }
            $p = ($e[1] ?? null) !== null ? (int) $e[1]
                : self::formulaPrice($list, (float) ($e[2] ?? $w['pct'] ?? 0), isset($row['floor_price']) ? (float) $row['floor_price'] : null);
            if ($p === null) {
                continue;
            }
            if ($best === null || $p < $best) {
                [$best, $win] = [$p, $w];
            }
        }

        return [$best, $win];
    }

    /**
     * StoqS's published sale-price formula (cp_sale_at with the site's floor), for a size the
     * site prices itself before StoqS has its price: $pct off $L rounded the brand's way, no
     * lower than the cap (max_discount_percent, rounded up) or the floor (rounded up). Null
     * when nothing comes off.
     */
    public static function formulaPrice(int $L, float $pct, ?float $floor, ?array $s = null): ?int
    {
        $s ??= self::settings();
        if ($L <= 0) {
            return null;
        }
        $dh = max(0, min(10000, (int) self::half($pct * 100)));
        if ($dh <= 0) {
            return null;
        }
        $mh = max(0, min(10000, (int) self::half((float) ($s['max_discount_percent'] ?? 100) * 100)));
        $target = self::roundPrice($L * (10000 - $dh) / 10000, $s);
        $cap = self::roundPrice($L * (10000 - $mh) / 10000, $s, 'up');
        $S = max($target, $cap);
        if ($floor !== null && $floor > 0) {
            $r = self::roundPrice($floor, $s, 'up');
            if ($r > $L) {
                return null;
            }
            $S = max($S, $r);
        }

        return $S >= $L ? null : (int) $S;
    }

    /** cp_round: half away from zero. */
    private static function half(float $x): float
    {
        return $x < 0 ? -floor(-$x + 0.5) : floor($x + 0.5);
    }

    /** cp_round_price: the brand's rounding (step 1/1000/10000/100000, down|up|nearest, offset). */
    public static function roundPrice(float $x, array $s, ?string $mode = null): int
    {
        if ($x <= 0) {
            return 0;
        }
        $step = (int) ($s['round_step'] ?? 1000);
        if (! in_array($step, [1, 1000, 10000, 100000], true)) {
            $step = 1000;
        }
        $off = (int) ($s['round_offset'] ?? 0);
        if ($off < 0 || $off >= $step) {
            $off = 0;
        }
        $mode ??= (string) ($s['round_mode'] ?? 'down');
        $eps = 1e-7;
        if ($mode === 'up') {
            $y = ceil($x / $step - $eps) * $step - $off;
            if ($y < $x - $eps) {
                $y += $step;
            }
        } elseif ($mode === 'nearest') {
            $y = floor(($x + $off) / $step + 0.5) * $step - $off;
        } else {
            $y = ceil($x / $step - $eps) * $step - $off;
            if ($y > $x + $eps) {
                $y -= $step;
            }
        }

        return (int) max(0, $y);
    }

    // ── what a size sells at now ───────────────────────────────────────────

    /**
     * One size, now: [list, price, window|null]. The list is what this site charges before
     * any promotion (the size's own price, else its product's list price); the price is the
     * window price, else the list.
     */
    public static function sizeNow(ProductVariant $v, ?int $productList = null, ?int $ts = null): array
    {
        $ts ??= now()->getTimestamp();
        $productList ??= StoqsContent::basePrice($v->product);
        $list = $v->price !== null ? (int) $v->price : (int) $productList;
        $row = $v->stockkeeping_variant_id ? (self::index()[(int) $v->stockkeeping_variant_id] ?? null) : null;
        if (! $row) {
            return [$list, $list, null];
        }
        if (isset($row['list_price']) && $row['list_price'] !== null && ($row['price_owner'] ?? '') !== 'shop') {
            $list = (int) $row['list_price'];
        }
        [$p, $w] = self::priceAt($row, $list, $ts);

        return [$list, $p !== null && $p < $list ? $p : $list, $p !== null && $p < $list ? $w : null];
    }

    /**
     * Put StoqS's prices on every product it links (managed only). Products the shop keeps
     * its own prices for (sync mode ignore / stock_only / manual, or a price edit not sent
     * yet) and bundles are left alone.
     *
     * @return array{products: int, changed: int}
     */
    public static function apply(?int $ts = null): array
    {
        $out = ['products' => 0, 'changed' => 0];
        if (! self::managed()) {
            return $out;
        }
        $ts ??= now()->getTimestamp();
        Product::whereNotNull('stockkeeping_id')->where('stockkeeping_id', '!=', '')->where('is_bundle', false)
            ->with('variants')->chunkById(200, function ($products) use (&$out, $ts) {
                foreach ($products as $p) {
                    $out['products']++;
                    if (self::applyProduct($p, $ts)) {
                        $out['changed']++;
                    }
                }
            });
        self::store(['applied_at' => now()]);

        return $out;
    }

    /**
     * One product (managed only): the product shows the price of the sizes that follow it
     * (the lowest, when a window prices them apart), its list price struck through when
     * that is lower; a size whose price is not what the product shows carries it in
     * promo_price. Returns whether anything was saved.
     */
    public static function applyProduct(Product $p, ?int $ts = null): bool
    {
        if (! self::managed() || $p->is_bundle) {
            return false;
        }
        $ts ??= now()->getTimestamp();
        $p->loadMissing('variants');
        $variants = $p->variants;
        $changed = false;
        if (in_array(StoqsContent::mode($p), self::OWN_PRICES, true) || in_array('price', StoqsContent::pending($p), true)) {
            foreach ($variants as $v) {
                if ($v->promo_price !== null) {
                    $v->promo_price = null;
                    $v->save();
                    $changed = true;
                }
            }

            return $changed;
        }
        $list = StoqsContent::basePrice($p);
        $now = [];
        foreach ($variants as $v) {
            $v->setRelation('product', $p);
            $now[$v->id] = self::sizeNow($v, $list, $ts);
        }
        $following = $variants->filter(fn ($v) => $v->price === null);
        $shown = $following->isNotEmpty() ? (int) min(array_map(fn ($v) => $now[$v->id][1], $following->all())) : $list;
        $shown = min($shown, $list);
        $p->price = $shown;
        $p->compare_at_price = $shown < $list ? $list : null;
        if (self::hasStoqsDiscount()) {
            $p->stoqs_discount = $shown < $list;
        }
        if ($p->isDirty(['price', 'compare_at_price', 'stoqs_discount'])) {
            $p->save();
            $changed = true;
        }
        foreach ($variants as $v) {
            $expected = $v->price !== null ? (int) $v->price : $shown;
            $promo = $now[$v->id][1] !== $expected ? $now[$v->id][1] : null;
            if ($v->promo_price !== $promo) {
                $v->promo_price = $promo;
                $v->save();
                $changed = true;
            }
        }

        return $changed;
    }

    private static function hasStoqsDiscount(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('products', 'stoqs_discount');
    }

    /**
     * Outlet products now (managed): local product id => percent off its list price, for
     * the products a StoqS outlet window prices at this moment.
     */
    public static function outletPercents(): array
    {
        return self::memo('.outlet', function () {
            $ids = array_map('strval', (array) (self::payload()['outlet'] ?? []));
            if (! $ids) {
                return [];
            }
            $ts = now()->getTimestamp();
            $out = [];
            $vids = [];
            foreach (self::index() as $vid => $row) {
                foreach ((array) ($row['prices'] ?? []) as $e) {
                    $w = self::windows()[(string) ($e[0] ?? '')] ?? null;
                    if ($w && in_array((string) $w['id'], $ids, true) && self::covers($w, $ts)) {
                        $vids[$vid] = true;
                    }
                }
            }
            if (! $vids) {
                return [];
            }
            ProductVariant::with('product')->whereIn('stockkeeping_variant_id', array_keys($vids))->get()
                ->each(function ($v) use (&$out, $ts) {
                    if (! $v->product) {
                        return;
                    }
                    [$list, $price] = self::sizeNow($v, null, $ts);
                    $pct = $list > 0 ? (int) round((1 - $price / $list) * 100) : 0;
                    $out[(int) $v->product_id] = max($out[(int) $v->product_id] ?? 0, $pct);
                });

            return array_filter($out, fn ($p) => $p > 0);
        });
    }

    // ── ack ─────────────────────────────────────────────────────────────────

    /** POST /promotions/ack once per version applied. Returns whether an ack was sent. */
    public static function ack(): bool
    {
        $row = self::row();
        $v = self::version();
        if (! self::managed() || $v === null || (int) ($row->acked_version ?? -1) === $v) {
            return false;
        }
        $plans = array_values(array_unique(array_map(fn ($pl) => (int) ($pl['id'] ?? 0), (array) (self::payload()['plans'] ?? []))));
        try {
            StoqsApi::call('POST', 'promotions/ack', array_filter([
                'version' => $v, 'etag' => $row->etag ?: null, 'site_url' => self::siteUrl(),
                'applied' => array_values(array_filter($plans)), 'failed' => [],
            ], fn ($x) => $x !== null));
        } catch (\Throwable $e) {
            Log::warning('[stoqs-promotions] ack failed', ['version' => $v, 'error' => $e->getMessage()]);

            return false;
        }
        self::store(['acked_version' => $v, 'acked_at' => now()]);

        return true;
    }

    // ── sizes priced in the shop ───────────────────────────────────────────

    /** A size price set in the shop (not by StoqS): it stays the shop's and StoqS is told. */
    public static function shopSet(ProductVariant $v): bool
    {
        return $v->price !== null && ($v->stoqs_size_price === null || (int) $v->stoqs_size_price !== (int) $v->price);
    }

    /**
     * Tell StoqS each shop-set size price once, and again when it changes; a size that is no
     * longer shop-set is handed back ({shop_set: false}). Managed only. At most $limit calls.
     *
     * @return array{sent: int, failed: int}
     */
    public static function reportSitePrices(int $limit = 200): array
    {
        $out = ['sent' => 0, 'failed' => 0];
        if (! self::managed() || ! Schema::hasColumn('product_variants', 'site_price_reported')) {
            return $out;
        }
        $rows = ProductVariant::whereNotNull('stockkeeping_variant_id')
            ->where(fn ($q) => $q->whereNotNull('price')->orWhereNotNull('site_price_reported'))
            ->orderBy('id')->get();
        foreach ($rows as $v) {
            if ($out['sent'] + $out['failed'] >= $limit) {
                break;
            }
            $want = self::shopSet($v) ? (int) $v->price : null;
            $have = $v->site_price_reported === null ? null : (int) $v->site_price_reported;
            if ($want === $have) {
                continue;
            }
            try {
                StoqsApi::call('PUT', 'catalog/variants/'.(int) $v->stockkeeping_variant_id.'/site-price',
                    $want === null ? ['shop_set' => false, 'site_url' => self::siteUrl()] : ['list_price' => $want, 'site_url' => self::siteUrl()]);
                $v->site_price_reported = $want;
                $v->saveQuietly();
                $out['sent']++;
            } catch (\Throwable $e) {
                $out['failed']++;
                Log::warning('[stoqs-promotions] site price not sent', ['variant' => $v->id, 'error' => $e->getMessage()]);
            }
        }

        return $out;
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private static function memo(string $key, callable $make): mixed
    {
        $attrs = request()->attributes;
        if (! $attrs->has(self::ATTR.$key)) {
            $attrs->set(self::ATTR.$key, $make());
        }

        return $attrs->get(self::ATTR.$key);
    }
}

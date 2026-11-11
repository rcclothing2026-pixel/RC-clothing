<?php

namespace App\Support;

use App\Models\OutletItem;
use App\Models\Product;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Support\Facades\Schema;

/**
 * Outlet prices. An active outlet item sets the product's price to its base
 * price less the outlet percentage (rounded down to 10,000 toman), with the
 * base as the struck price, so cart, schema, Instagram API and the StoqS sale
 * report all see the same price. `stoqs_discount` marks it as a synced
 * discount, so the next StoqS import clears it once the item is removed.
 *
 * While StoqS manages this site's promotions the outlet is StoqS's: percents() lists the
 * products a StoqS outlet window prices now (StoqsPromotions::outletPercents), apply()
 * does nothing (the window applier sets the prices) and sync() refuses. The shop's own
 * outlet_items stay as they are, for when the shop runs its promotions again.
 */
class Outlet
{
    public const MANAGED = 'اوتلت این سایت در StoqS مدیریت می‌شود؛ آن را در StoqS («برنامه‌ریزی کمپین» ← اوتلت) تغییر دهید.';

    /** @return array<int, int> product id => percent, for active items */
    public static function percents(): array
    {
        return request()->attributes->get('outlet.percents') ?? tap(
            StoqsPromotions::managed() ? StoqsPromotions::outletPercents()
                : (Schema::hasTable('outlet_items') ? OutletItem::where('is_active', true)->pluck('percent', 'product_id')->all() : []),
            fn ($m) => request()->attributes->set('outlet.percents', $m),
        );
    }

    public static function productIds(): array
    {
        return array_keys(self::percents());
    }

    public static function has(int $productId): bool
    {
        return isset(self::percents()[$productId]);
    }

    /** Products the outlet may not take: winter categories and bundles. */
    public static function allowed(Product $p): bool
    {
        $skip = config('shop.no_discount_categories', []);

        return ! $p->is_bundle && $p->categories->pluck('slug')->push($p->category?->slug)->intersect($skip)->isEmpty();
    }

    /** The outlet price for $base at $percent: rounded down to 10,000 toman. */
    public static function priceFor(int $base, int $percent): int
    {
        return (int) (floor($base * (100 - $percent) / 100 / 10000) * 10000);
    }

    /** The price the outlet percentage is taken from: the struck price when there is one. */
    public static function base(Product $p): int
    {
        return (int) ($p->hasDiscount() ? $p->compare_at_price : $p->price);
    }

    /** Why $percent may not go on $p (in Persian), or null. */
    public static function problem(Product $p, int $percent): ?string
    {
        $max = (int) config('shop.outlet_max_percent', 30);
        if (! self::allowed($p)) {
            return '«'.$p->name.'» زمستانی یا بسته است و به اوتلت نمی‌رود.';
        }
        if ($percent < 1 || $percent > $max) {
            return 'اوتلت «'.$p->name.'»: درصد باید بین ۱ و '.Margin::fa($max).' باشد.';
        }
        $cost = Margin::floorOf((int) $p->id);
        if (! Margin::keeps(self::priceFor(self::base($p), $percent), $cost)) {
            return 'اوتلت «'.$p->name.'»: با '.Margin::fa($percent).'٪ حاشیه از '.Margin::fa(Margin::min()).'٪ کمتر می‌شود؛ حداکثر '
                .Margin::fa(min($max, (int) Margin::maxPercent(self::base($p), $cost))).'٪.';
        }

        return null;
    }

    /**
     * Put products in the outlet, change their percent or take them out
     * (percent 0 or null), by slug. All or nothing.
     *
     * @return string[] problems in Persian
     */
    public static function sync(array $bySlug): array
    {
        if (StoqsPromotions::managed()) {
            return [self::MANAGED];
        }
        $products = Product::with(['category', 'categories'])->whereIn('slug', array_map('strval', array_keys($bySlug)))->get()->keyBy('slug');
        $err = [];
        foreach ($bySlug as $slug => $pct) {
            $p = $products[(string) $slug] ?? null;
            if (! $p) {
                $err[] = "اوتلت: محصول «{$slug}» پیدا نشد.";
            } elseif ((int) $pct > 0 && ($why = self::problem($p, (int) $pct))) {
                $err[] = $why;
            }
        }
        if ($err) {
            return $err;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($bySlug, $products) {
            foreach ($bySlug as $slug => $pct) {
                $p = $products[(string) $slug];
                $pct = (int) $pct;
                $item = OutletItem::where('product_id', $p->id)->first();
                if ($pct <= 0) {
                    if ($item) {
                        $item->delete();
                        self::restore($p);
                        $p->save();
                    }

                    continue;
                }
                if ($item && $item->is_active && (int) $item->percent === $pct) {
                    continue;
                }
                if ($item) {
                    self::restore($p);
                    $item->update(['percent' => $pct, 'is_active' => true]);
                } else {
                    OutletItem::create(['product_id' => $p->id, 'percent' => $pct, 'is_active' => true]);
                }
                self::apply($p);
                $p->save();
            }
        });
        request()->attributes->remove('outlet.percents');

        return [];
    }

    /**
     * Set the outlet price on $p (unsaved). $base and $stoqsSale come from the
     * StoqS import; from the admin they are read off the product.
     */
    public static function apply(Product $p, ?int $base = null, ?int $stoqsSale = null): void
    {
        if (StoqsPromotions::managed()) {
            return;   // StoqS's outlet windows price it (StoqsPromotions::applyProduct)
        }
        $item = OutletItem::where('product_id', $p->id)->where('is_active', true)->first();
        if (! $item) {
            return;
        }
        $base ??= (int) ($p->hasDiscount() ? $p->compare_at_price : $p->price);
        $outlet = self::priceFor($base, (int) $item->percent);
        $p->price = $stoqsSale !== null ? min($outlet, $stoqsSale) : $outlet;
        $p->compare_at_price = $base;
        self::flag($p, true);
    }

    /** Back to the base price; the next StoqS import restores any StoqS sale. */
    public static function restore(Product $p): void
    {
        if ($p->hasDiscount()) {
            $p->price = (int) $p->compare_at_price;
        }
        $p->compare_at_price = null;
        self::flag($p, false);
    }

    /** `stoqs_discount` exists only where the StoqS sale-price sync is installed. */
    private static function flag(Product $p, bool $on): void
    {
        static $has = null;
        if ($has ??= Schema::hasColumn('products', 'stoqs_discount')) {
            $p->stoqs_discount = $on;
        }
    }
}

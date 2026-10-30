<?php

namespace App\Support;

use App\Models\OutletItem;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;

/**
 * Outlet prices. An active outlet item sets the product's price to its base
 * price less the outlet percentage (rounded down to 10,000 toman), with the
 * base as the struck price, so cart, schema, Instagram API and the StoqS sale
 * report all see the same price. `stoqs_discount` marks it as a synced
 * discount, so the next StoqS import clears it once the item is removed.
 */
class Outlet
{
    /** @return array<int, int> product id => percent, for active items */
    public static function percents(): array
    {
        return request()->attributes->get('outlet.percents') ?? tap(
            Schema::hasTable('outlet_items') ? OutletItem::where('is_active', true)->pluck('percent', 'product_id')->all() : [],
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

    /**
     * Set the outlet price on $p (unsaved). $base and $stoqsSale come from the
     * StoqS import; from the admin they are read off the product.
     */
    public static function apply(Product $p, ?int $base = null, ?int $stoqsSale = null): void
    {
        $item = OutletItem::where('product_id', $p->id)->where('is_active', true)->first();
        if (! $item) {
            return;
        }
        $base ??= (int) ($p->hasDiscount() ? $p->compare_at_price : $p->price);
        $outlet = (int) (floor($base * (100 - $item->percent) / 100 / 10000) * 10000);
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

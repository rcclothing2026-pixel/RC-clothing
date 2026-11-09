<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductCost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The minimum-margin floor (shop.min_margin_percent, profit ÷ sale price).
 * A product's floor price is the lowest price that still keeps that margin
 * over its finished cost; products without a cost have no floor.
 */
class Margin
{
    public static function min(): int
    {
        return max(0, min(90, (int) config('shop.min_margin_percent', 10)));
    }

    /** @return array<int, int> product id => cost */
    public static function costs(): array
    {
        return request()->attributes->get('margin.costs') ?? tap(
            Schema::hasTable('product_costs') ? ProductCost::pluck('cost', 'product_id')->map(fn ($c) => (int) $c)->all() : [],
            fn ($m) => request()->attributes->set('margin.costs', $m),
        );
    }

    public static function costOf(int $productId): ?int
    {
        return self::costs()[$productId] ?? null;
    }

    /** Lowest unit price that keeps the minimum margin over $cost. */
    public static function floor(int $cost): int
    {
        return (int) ceil($cost * 100 / (100 - self::min()));
    }

    /** Largest whole percent off $price that keeps the margin; null without a cost. */
    public static function maxPercent(int $price, ?int $cost): ?int
    {
        if ($cost === null || $price <= 0) {
            return null;
        }

        return max(0, (int) floor((1 - self::floor($cost) / $price) * 100 + 1e-9));
    }

    /** True when $price keeps the margin (always true without a cost). */
    public static function keeps(int $price, ?int $cost): bool
    {
        return $cost === null || $price >= self::floor($cost);
    }

    /**
     * Write costs by product slug: a number sets it, null or '' clears it.
     *
     * @return string[] problems in Persian; nothing is written when there are any
     */
    public static function saveCosts(array $bySlug, string $source = 'nj'): array
    {
        $err = [];
        $products = Product::whereIn('slug', array_map('strval', array_keys($bySlug)))->pluck('id', 'slug');
        $rows = [];
        foreach ($bySlug as $slug => $cost) {
            $slug = (string) $slug;
            if (! isset($products[$slug])) {
                $err[] = "قیمت تمام‌شده: محصول «{$slug}» پیدا نشد.";

                continue;
            }
            if ($cost === null || $cost === '') {
                $rows[$products[$slug]] = null;

                continue;
            }
            if (! is_numeric($cost) || (int) $cost < 0 || (int) $cost > 100_000_000_000) {
                $err[] = "قیمت تمام‌شدهٔ «{$slug}» عدد درستی نیست.";

                continue;
            }
            $rows[$products[$slug]] = (int) $cost;
        }
        if ($err) {
            return $err;
        }
        DB::transaction(function () use ($rows, $source) {
            foreach ($rows as $id => $cost) {
                if ($cost === null) {
                    ProductCost::where('product_id', $id)->delete();
                } else {
                    ProductCost::updateOrCreate(['product_id' => $id], ['cost' => $cost, 'source' => $source]);
                }
            }
        });
        request()->attributes->remove('margin.costs');

        return [];
    }

    /** Persian digits for messages. */
    public static function fa(int|float|string $n): string
    {
        return strtr((string) $n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}

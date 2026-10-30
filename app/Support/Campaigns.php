<?php

namespace App\Support;

use App\Models\Collection as ShopCollection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Date-windowed campaign content read from config/campaigns.php: shop shelves,
 * promo-bar slides, pop-ups, product-page offer badges and «ست کنید با» pairs.
 * Discount rules, coupons and bundles are DB rows written by `campaigns:sync`.
 * Dates are Tehran calendar days (Y-m-d); a missing from/to is open-ended.
 */
class Campaigns
{
    private static array $shelfMemo = [];

    public static function today(): string
    {
        return now('Asia/Tehran')->toDateString();
    }

    public static function live(array $items): array
    {
        $t = self::today();

        return array_filter($items, fn ($i) => (empty($i['from']) || $i['from'] <= $t) && (empty($i['to']) || $i['to'] >= $t));
    }

    /** A live shelf by slug (with its slug added), or null. */
    public static function shelf(string $slug): ?array
    {
        $s = config("campaigns.shelves.$slug");

        return is_array($s) && self::live([$s]) ? $s + ['slug' => $slug] : null;
    }

    /** @return array<string, array> live shelves keyed by slug */
    public static function shelves(): array
    {
        $out = [];
        foreach (self::live(config('campaigns.shelves', [])) as $slug => $s) {
            $out[$slug] = $s + ['slug' => $slug];
        }

        return $out;
    }

    /** The shelf as an unsaved Collection, so the shop views title it like one. */
    public static function shelfCollection(array $s): ShopCollection
    {
        return new ShopCollection(['name' => $s['name'], 'slug' => $s['slug'], 'description' => $s['description'] ?? '', 'is_active' => true]);
    }

    public static function applyShelf(Builder $q, array $s): Builder
    {
        $stock = '(select coalesce(sum(stock_qty), 0) from product_variants where product_variants.product_id = products.id and product_variants.is_active = 1)';

        if (! empty($s['categories'])) {
            $q->whereHas('categories', fn ($c) => $c->whereIn('slug', $s['categories']));
        }
        if (! empty($s['exclude_categories'])) {
            $q->whereDoesntHave('categories', fn ($c) => $c->whereIn('slug', $s['exclude_categories']));
        }
        if (! empty($s['products'])) {
            $q->whereIn('slug', $s['products']);
        }
        if (! empty($s['bundles'])) {
            $q->where('is_bundle', true);
        }
        if (isset($s['max_price'])) {
            $q->where('price', '<=', (int) $s['max_price']);
        }
        if (isset($s['max_stock'])) {
            $q->where('is_bundle', false)->whereRaw("$stock between 1 and ?", [(int) $s['max_stock']]);
        } elseif (! empty($s['in_stock'])) {
            $q->where(fn ($w) => $w->where('is_bundle', true)->orWhereRaw("$stock > 0"));
        }

        return $q;
    }

    public static function inShelf(?Product $p, string $slug): bool
    {
        if (! $p || ! ($s = self::shelf($slug))) {
            return false;
        }

        return self::$shelfMemo[$slug][$p->id] ??= self::applyShelf(Product::whereKey($p->id), $s)->exists();
    }

    /** Category slugs of this product (primary + secondary). */
    private static function categorySlugs(Product $p): array
    {
        return $p->categories->pluck('slug')->push($p->category?->slug)->filter()->unique()->values()->all();
    }

    /** In-stock products from the categories that complete this product's look. */
    public static function pairsFor(Product $p, int $take = 4)
    {
        $pairs = collect(self::categorySlugs($p))
            ->flatMap(fn ($slug) => config("campaigns.pairs.$slug", []))
            ->unique()->values()->all();
        if ($pairs === []) {
            return collect();
        }

        return self::applyShelf(Product::active()->whereKeyNot($p->id), ['categories' => $pairs, 'in_stock' => true])
            ->with(['images', 'variants'])->inRandomOrder()->take($take)->get();
    }

    /** Live offer lines for the product page (config «badges»). */
    public static function badgesFor(Product $p): array
    {
        $cats = self::categorySlugs($p);

        return array_values(array_map(fn ($b) => $b['text'], array_filter(self::live(config('campaigns.badges', [])), function ($b) use ($p, $cats) {
            if (! empty($b['exclude_categories']) && array_intersect($b['exclude_categories'], $cats)) {
                return false;
            }
            if (! empty($b['categories'])) {
                return (bool) array_intersect($b['categories'], $cats);
            }
            if (! empty($b['products'])) {
                return in_array($p->slug, $b['products'], true);
            }
            if (! empty($b['shelf'])) {
                return self::inShelf($p, $b['shelf']);
            }

            return true;
        })));
    }

    public static function promoSlides(): array
    {
        return array_values(array_map(fn ($s) => ['text' => $s['text'], 'url' => $s['url'] ?? ''], self::live(config('campaigns.slides', []))));
    }

    /** Live campaign pop-ups in Popup::activePayload() shape. */
    public static function popups(): array
    {
        $out = [];
        foreach (self::live(config('campaigns.popups', [])) as $key => $p) {
            $out[] = [
                'id' => 'c-'.$key,
                'html' => $p['html'],
                'trigger' => $p['trigger'] ?? 'delay',
                'delay' => $p['delay'] ?? 6,
                'frequency' => $p['frequency'] ?? 'daily',
                'pages' => $p['pages'] ?? 'all',
            ];
        }

        return $out;
    }

    /**
     * Sets from config «sets» (mix-and-match offers that need sizes, so they
     * are discount rules, not bundle products). Live ones, plus upcoming ones
     * flagged with their Jalali start date when $upcoming is true.
     */
    public static function sets(bool $upcoming = false): array
    {
        $t = self::today();
        $out = [];
        foreach (config('campaigns.sets', []) as $key => $s) {
            if (! empty($s['to']) && $s['to'] < $t) {
                continue;
            }
            $soon = ! empty($s['from']) && $s['from'] > $t;
            if ($soon && ! $upcoming) {
                continue;
            }
            $out[$key] = $s + ['key' => $key, 'starts' => $soon ? 'از '.Jalali::format(\Illuminate\Support\Carbon::parse($s['from'])) : null];
        }

        return $out;
    }

    /** Bundle products that can be assembled now. */
    public static function bundles()
    {
        return Product::active()->where('is_bundle', true)->with(['images', 'variants', 'bundleItems.variant.product'])
            ->orderBy('price')->get()->filter(fn ($b) => $b->bundleAvailableQty() > 0)->values();
    }

    /**
     * Bundles and live sets that include any of these products: shown in the
     * mini-cart after an add and under the cart lines.
     *
     * @return array<int, array{name: string, url: string, image: ?string, note: string, price: ?string, was: ?string}>
     */
    public static function offersFor(array $productIds, int $take = 3): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }
        $products = Product::whereIn('id', $productIds)->with(['categories', 'category'])->get();
        $out = [];

        foreach (self::bundles() as $b) {
            if (in_array((int) $b->id, $productIds, true)
                || ! $b->bundleItems->contains(fn ($bi) => in_array((int) $bi->variant?->product_id, $productIds, true))) {
                continue;
            }
            $out[] = [
                'name' => $b->name,
                'url' => route('product.show', $b->slug),
                'image' => $b->primary_image_url,
                'note' => 'در این بسته '.Money::toman((int) $b->compare_at_price - (int) $b->price).' ارزان‌تر',
                'price' => Money::toman((int) $b->price),
                'was' => $b->compare_at_price ? Money::toman((int) $b->compare_at_price) : null,
            ];
        }

        foreach (self::sets() as $s) {
            $hit = $products->contains(fn ($p) => in_array($p->slug, $s['products'] ?? [], true)
                || array_intersect($s['categories'] ?? [], self::categorySlugs($p)));
            if ($hit) {
                $out[] = ['name' => $s['name'], 'url' => $s['url'], 'image' => self::setImage($s), 'note' => $s['text'], 'price' => null, 'was' => null];
            }
        }

        return array_slice($out, 0, $take);
    }

    /** First in-stock product photo of a set, for its card. */
    public static function setImage(array $s): ?string
    {
        $q = Product::active()->where('is_bundle', false);
        $q = ! empty($s['products'])
            ? $q->whereIn('slug', $s['products'])
            : self::applyShelf($q, ['categories' => $s['categories'] ?? []]);

        return self::applyShelf($q, ['in_stock' => true])->with('images')->first()?->primary_image_url;
    }
}

<?php

namespace App\Support;

use App\Models\Collection as ShopCollection;
use App\Models\Product;
use App\Models\CampaignApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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

        return array_filter($items, function ($i) use ($t) {
            $d = self::resolve($i);

            return $d && (empty($d['from']) || $d['from'] <= $t) && (empty($d['to']) || $d['to'] >= $t);
        });
    }

    /** Approved campaign windows pushed from NJ Focus: key => [from, to] (Y-m-d). */
    public static function approvals(): array
    {
        return request()->attributes->get('campaigns.approvals') ?? tap(
            Schema::hasTable('campaign_approvals')
                ? CampaignApproval::where('approved', true)->get()
                    ->mapWithKeys(fn ($a) => [$a->key => [$a->starts_on->toDateString(), $a->ends_on->toDateString()]])->all()
                : [],
            fn ($m) => request()->attributes->set('campaigns.approvals', $m),
        );
    }

    /** Days the approved campaign moved from its planned start; null while unapproved. */
    public static function shiftDays(string $campaign): ?int
    {
        $w = self::approvals()[$campaign] ?? null;
        $plan = config("campaigns.campaigns.$campaign");

        return $w && $plan ? (int) Carbon::parse($plan['from'])->diffInDays(Carbon::parse($w[0]), false) : null;
    }

    /**
     * An item's effective from/to. Untagged items keep their own dates. Tagged
     * items: null until their campaign is approved; then their dates move with
     * the approved window (a date equal to the planned start/end becomes the
     * approved start/end, others shift by the same days) and stay inside it.
     */
    public static function resolve(array $i): ?array
    {
        $from = $i['from'] ?? null;
        $to = $i['to'] ?? null;
        if (empty($i['campaign'])) {
            return ['from' => $from, 'to' => $to];
        }
        $shift = self::shiftDays($i['campaign']);
        if ($shift === null) {
            return null;
        }
        [$wf, $wt] = self::approvals()[$i['campaign']];
        $plan = config("campaigns.campaigns.{$i['campaign']}");
        $move = fn (?string $d, string $planned, string $approved) => $d === null ? $approved
            : ($d === $planned ? $approved : Carbon::parse($d)->addDays($shift)->toDateString());
        $from = max($move($from, $plan['from'], $wf), $wf);
        $to = min($move($to, $plan['to'], $wt), $wt);

        return $from <= $to ? ['from' => $from, 'to' => $to] : null;
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
        if (! empty($s['outlet'])) {
            $q->whereIn('id', Outlet::productIds() ?: [0]);
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
            if (! ($d = self::resolve($s)) || (! empty($d['to']) && $d['to'] < $t)) {
                continue;
            }
            $soon = ! empty($d['from']) && $d['from'] > $t;
            if ($soon && ! $upcoming) {
                continue;
            }
            $out[$key] = $s + ['key' => $key, 'starts' => $soon ? 'از '.Jalali::format(Carbon::parse($d['from'])) : null];
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

    /** True when the product belongs to the set (by slug or category). */
    private static function inSet(Product $p, array $s): bool
    {
        return in_array($p->slug, $s['products'] ?? [], true) || (bool) array_intersect($s['categories'] ?? [], self::categorySlugs($p));
    }

    /**
     * Short label for a product card when the product forms a bundle or a live
     * set with something already in the cart; null otherwise.
     */
    public static function cartHint(Product $p): ?string
    {
        $ctx = request()->attributes->get('campaigns.cart') ?? tap(self::cartContext(), fn ($c) => request()->attributes->set('campaigns.cart', $c));
        if (! $ctx['ids'] || in_array((int) $p->id, $ctx['ids'], true)) {
            return null;
        }
        foreach ($ctx['bundles'] as $b) {
            if (! array_intersect($b['parts'], $ctx['ids'])) {
                continue;
            }
            if ((int) $p->id === $b['id']) {
                return 'شامل کالای سبد شما، ارزان‌تر';
            }
            if (in_array((int) $p->id, $b['parts'], true)) {
                return 'با سبد شما در بسته ارزان‌تر';
            }
        }
        foreach ($ctx['sets'] as $s) {
            if (($s['hint'] ?? true) && self::inSet($p, $s) && $ctx['products']->contains(fn ($c) => self::inSet($c, $s))) {
                return 'ست با سبد شما: '.$s['name'];
            }
        }

        return null;
    }

    private static function cartContext(): array
    {
        $products = app(\App\Services\Cart\Cart::class)->lines()->map(fn ($l) => $l['variant']->product)->filter()->unique('id')->values();
        if ($products->isEmpty()) {
            return ['ids' => [], 'products' => $products, 'bundles' => [], 'sets' => []];
        }

        return [
            'ids' => $products->pluck('id')->map(fn ($i) => (int) $i)->all(),
            'products' => $products,
            'bundles' => self::bundles()->map(fn ($b) => ['id' => (int) $b->id,
                'parts' => $b->bundleItems->map(fn ($bi) => (int) $bi->variant?->product_id)->filter()->values()->all()])->all(),
            'sets' => self::sets(),
        ];
    }
}

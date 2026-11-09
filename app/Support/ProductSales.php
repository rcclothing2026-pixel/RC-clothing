<?php

namespace App\Support;

use App\Models\Product;

/**
 * «Discount on products»: a campaign's item-only sale. The owner picks the
 * products (each with its own percent, or the sale's default) and optional
 * piece tiers; campaigns:sync turns one sale into ordinary discount rules:
 *
 *   <key>--p<percent>  item_discount_percent on the products at that percent
 *   <key>--n<count>    a tier: from <count> pieces, an extra percent on the same products
 *
 * Every rule is scoped to the chosen products, so nothing else in the cart is
 * discounted. Pieces are counted over the whole cart (count = all) or only
 * over the chosen products (count = selected). A «use alone» sale keeps its
 * rules together: the cart compares the whole sale against other discounts.
 *
 * Config shape:  key => [campaign, label, pct, count, exclusive, from?, to?,
 *                        products => [slug => ?percent], ladder => [[count, percent], …]]
 * Editor shape:  the same with products => [{slug, pct}] and ladder => [{n, pct}].
 */
class ProductSales
{
    public static function toEditor(array $s): array
    {
        $s['products'] = array_map(fn ($slug, $pct) => ['slug' => (string) $slug, 'pct' => $pct === null ? null : (int) $pct], array_keys($s['products'] ?? []), $s['products'] ?? []);
        $s['ladder'] = array_map(fn ($l) => ['n' => (int) $l[0], 'pct' => (int) $l[1]], self::ladder($s));
        $s['pct'] = (int) ($s['pct'] ?? 0);
        $s['count'] = ($s['count'] ?? 'all') === 'selected' ? 'selected' : 'all';
        $s['exclusive'] = (bool) ($s['exclusive'] ?? false);

        return $s;
    }

    public static function fromEditor(array $e): array
    {
        $products = [];
        foreach ((array) ($e['products'] ?? []) as $p) {
            $p = (array) $p;
            $slug = trim((string) ($p['slug'] ?? ''));
            if ($slug !== '') {
                $products[$slug] = isset($p['pct']) && $p['pct'] !== '' && $p['pct'] !== null ? (int) $p['pct'] : null;
            }
        }
        $ladder = [];
        foreach ((array) ($e['ladder'] ?? []) as $l) {
            $l = (array) $l;
            if (($l['n'] ?? '') !== '' && ($l['pct'] ?? '') !== '') {
                $ladder[] = [(int) $l['n'], (int) $l['pct']];
            }
        }
        usort($ladder, fn ($a, $b) => $a[0] <=> $b[0]);

        return array_filter([
            'campaign' => $e['campaign'] ?? null,
            'label' => trim((string) ($e['label'] ?? '')),
            'pct' => (int) ($e['pct'] ?? 0),
            'count' => ($e['count'] ?? 'all') === 'selected' ? 'selected' : 'all',
            'exclusive' => (bool) ($e['exclusive'] ?? false),
            'from' => $e['from'] ?? null,
            'to' => $e['to'] ?? null,
            'products' => $products,
            'ladder' => $ladder,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** @return array<int, array{0: int, 1: int}> tiers sorted by piece count */
    public static function ladder(array $s): array
    {
        $l = array_values(array_map(fn ($x) => [(int) ($x[0] ?? $x['n'] ?? 0), (int) ($x[1] ?? $x['pct'] ?? 0)], $s['ladder'] ?? []));
        usort($l, fn ($a, $b) => $a[0] <=> $b[0]);

        return $l;
    }

    /** The percent each product gets from the item part of the sale. */
    public static function percentOf(array $s, ?int $own): int
    {
        return $own ?? (int) ($s['pct'] ?? 0);
    }

    /** The most a product can get from this sale: its percent plus the top tier, capped. */
    public static function worst(array $s, ?int $own): int
    {
        $top = max([0, ...array_map(fn ($l) => $l[1], self::ladder($s))]);

        return min((int) config('shop.max_discount_percent', 25), self::percentOf($s, $own) + $top);
    }

    /** Problems with one sale (config shape), in Persian. */
    public static function check(array $s, string $key): array
    {
        $max = (int) config('shop.max_discount_percent', 25);
        $name = 'تخفیف محصولی «'.($s['label'] ?? $key).'»';
        $err = [];
        if (empty($s['label'])) {
            $err[] = "تخفیف محصولی «{$key}»: متنی که مشتری می‌بیند خالی است.";
        }
        $products = $s['products'] ?? [];
        if ($products === []) {
            $err[] = "$name: هیچ محصولی انتخاب نشده.";
        }
        if (($s['pct'] ?? 0) < 0 || ($s['pct'] ?? 0) > $max) {
            $err[] = "$name: درصد پیش‌فرض باید بین ۰ و ".Margin::fa($max).' باشد.';
        }
        $prev = 1;
        foreach (self::ladder($s) as [$n, $pct]) {
            if ($n <= $prev) {
                $err[] = "$name: پله‌ها باید از ۲ قطعه شروع شوند و تکراری نباشند.";
            }
            if ($pct < 1 || $pct > $max) {
                $err[] = "$name: درصد پلهٔ ".Margin::fa($n).' قطعه باید بین ۱ و '.Margin::fa($max).' باشد.';
            }
            $prev = $n;
        }
        if ($products === []) {
            return $err;
        }

        $skip = (array) config('shop.no_discount_categories', []);
        $found = Product::with(['category', 'categories'])->whereIn('slug', array_map('strval', array_keys($products)))->get()->keyBy('slug');
        $outlet = Outlet::percents();
        $hasTiers = self::ladder($s) !== [];
        foreach ($products as $slug => $own) {
            $p = $found[(string) $slug] ?? null;
            if (! $p) {
                $err[] = "$name: محصول «{$slug}» در سایت نیست.";

                continue;
            }
            if ($skip && $p->categories->pluck('slug')->push($p->category?->slug)->intersect($skip)->isNotEmpty()) {
                $err[] = "$name: «{$p->name}» زمستانی است و تخفیف نمی‌گیرد.";

                continue;
            }
            $pct = self::percentOf($s, $own);
            if ($pct < 0 || $pct > $max || ($pct === 0 && ! $hasTiers)) {
                $err[] = "$name: درصد «{$p->name}» باید بین ۱ و ".Margin::fa($max).' باشد.';

                continue;
            }
            if (isset($outlet[$p->id])) {
                continue;   // outlet items take no further discount
            }
            $cost = Margin::costOf($p->id);
            $worst = self::worst($s, $own);
            $price = (int) $p->price;
            if (! Margin::keeps((int) floor($price * (100 - $worst) / 100), $cost)) {
                $err[] = "$name: «{$p->name}» با ".Margin::fa($worst).'٪ حاشیه‌اش از '.Margin::fa(Margin::min()).'٪ کمتر می‌شود؛ حداکثر '.Margin::fa((int) Margin::maxPercent($price, $cost)).'٪.';
            }
        }

        return $err;
    }

    /**
     * The discount rules (config shape, like plan['rules']) every sale expands to.
     *
     * @return array<string, array>
     */
    public static function rules(array $plan): array
    {
        $out = [];
        foreach ($plan['sales'] ?? [] as $key => $s) {
            $products = $s['products'] ?? [];
            if ($products === []) {
                continue;
            }
            $label = trim((string) ($s['label'] ?? '')) ?: 'تخفیف';
            $common = array_filter([
                'campaign' => $s['campaign'] ?? null, 'from' => $s['from'] ?? null, 'to' => $s['to'] ?? null,
                'exclusive' => (bool) ($s['exclusive'] ?? false), 'priority' => 20,
            ], fn ($v) => $v !== null);
            $slugs = array_map('strval', array_keys($products));

            $groups = [];
            foreach ($products as $slug => $own) {
                $pct = self::percentOf($s, $own);
                if ($pct > 0) {
                    $groups[$pct][] = (string) $slug;
                }
            }
            krsort($groups);
            foreach ($groups as $pct => $list) {
                $out["$key--p$pct"] = $common + [
                    'label' => $label.' · '.Margin::fa($pct).'٪',
                    'conditions' => [['item_products', 'products' => $list]],
                    'actions' => [['item_discount_percent', $pct]],
                ];
            }

            $ladder = self::ladder($s);
            foreach ($ladder as $i => [$n, $pct]) {
                $count = ($s['count'] ?? 'all') === 'selected'
                    ? ['item_count_from_products', 'products' => $slugs, 'min' => $n]
                    : ['cart_item_count', 'min' => $n];
                if (isset($ladder[$i + 1])) {
                    $count['max'] = $ladder[$i + 1][0] - 1;
                }
                $out["$key--n$n"] = $common + [
                    'label' => $label.' · '.Margin::fa($n).' قطعه '.Margin::fa($pct).'٪ بیشتر',
                    'conditions' => [$count, ['item_products', 'products' => $slugs]],
                    'actions' => [['item_discount_percent', $pct]],
                ];
            }
        }

        return $out;
    }

    /** The sale a generated rule belongs to (its «use alone» group), from the rule's description. */
    public static function groupOf(?string $description): ?string
    {
        return $description && preg_match('/^campaign:(.+)--[pn]\d+$/', $description, $m) ? $m[1] : null;
    }
}

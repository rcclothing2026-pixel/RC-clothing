<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live catalogue for the Instagram DM responder: it answers "how much is it /
 * do you have it" in a chat, so every number must be the one the shop shows
 * right now. Read-only; guarded by VerifyIgCatalogKey (routes/api.php).
 *
 * The rules are the storefront's own, read through the same model methods:
 *   - visible = Product::active() (is_active), as the shop list and the
 *     product page (404 otherwise);
 *   - price_toman = products.price, the number the product page prints; with
 *     hasDiscount() the page strikes compare_at_price → regular_price_toman;
 *   - a variant's price = effectivePrice() (its own override or the product
 *     price) — what the cart charges for that size;
 *   - stock = ProductVariant::inStock()/availableQty() (active variant, cached
 *     StoqS stock_qty; a bundle's derived availability). Inactive variants are
 *     left out: the cart refuses them.
 *   - external_url is set only for a «Buy from Seller» product (isExternal()):
 *     the page links out instead of selling here, so the responder should too.
 *
 * Output is a field whitelist, never toArray(), so StoqS ids, SKUs and any
 * column added later cannot leak. No caching: the catalogue is small and the
 * caller wants live numbers.
 */
class IgCatalogController extends Controller
{
    /** Alternatives must sit within ±this share of the product's price. */
    private const PRICE_SPREAD = 0.4;

    public function index(): JsonResponse
    {
        $rows = $this->query()->orderBy('id')->get()->map(fn (Product $p) => $this->row($p))->values();

        return response()->json([
            'data' => $rows,
            'count' => $rows->count(),
            'currency' => 'toman',
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function show(string $product): JsonResponse
    {
        $p = $this->find($product);
        abort_unless($p !== null, 404);

        return response()->json(['data' => $this->row($p)]);
    }

    /** Up to 10 products by name/slug (Persian or English), best match first. */
    public function search(Request $request): JsonResponse
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $tokens = $this->tokens($q);
        if ($tokens === []) {
            return response()->json(['data' => [], 'count' => 0]);
        }
        $whole = str_replace(' ', '', $this->norm($q));

        $scored = $this->query()->get()->map(function (Product $p) use ($tokens, $whole) {
            $a = $p->getAttributes();
            $name = $this->norm(($a['name'] ?? '').' '.($a['name_en'] ?? ''));
            $hay = $this->norm(implode(' ', [
                $name, str_replace('-', ' ', (string) $p->slug),
                $p->category?->getAttributes()['name'] ?? '',
                $p->variants->where('is_active', true)->pluck('color')->filter()->unique()->implode(' '),
            ]));
            $tight = str_replace(' ', '', $hay);
            // ZWNJ is stripped and spaces are ignored on the tight side, so
            // «تی‌شرت», «تی شرت» and «تیشرت» all meet.
            $score = count(array_filter($tokens, fn ($t) => str_contains($hay, $t) || str_contains($tight, $t)));

            return ['p' => $p, 'score' => $score, 'name' => str_contains(str_replace(' ', '', $name), $whole)];
        })->where('score', '>', 0);

        // Rows matching the most words win, so filler a customer types around
        // the product name («قیمت», «دارید») can't empty the result.
        $best = (int) $scored->max('score');
        $rows = $scored->where('score', $best)
            ->map(fn ($s) => $s + ['row' => $this->row($s['p'])])
            ->sort(fn ($x, $y) => [$y['name'], $y['row']['in_stock'], $y['p']->id] <=> [$x['name'], $x['row']['in_stock'], $x['p']->id])
            ->take(10)->pluck('row')->values();

        return response()->json(['data' => $rows, 'count' => $rows->count()]);
    }

    /** In-stock products of the same (primary) category, closest in price first. */
    public function alternatives(Request $request, string $product): JsonResponse
    {
        $src = $this->find($product);
        abort_unless($src !== null, 404);
        $limit = max(1, min(10, (int) $request->query('limit', 2)));
        $ref = (int) $src->price;

        $rows = collect();
        if ($src->category_id) {
            $rows = $this->query()
                ->where('category_id', $src->category_id)
                ->whereKeyNot($src->id)
                ->get()
                ->map(fn (Product $p) => $this->row($p))
                ->filter(fn ($r) => $r['in_stock'] && abs($r['price_toman'] - $ref) <= $ref * self::PRICE_SPREAD)
                ->sortBy(fn ($r) => [abs($r['price_toman'] - $ref), $r['id']])
                ->take($limit)->values();
        }

        return response()->json([
            'product' => $this->row($src),
            'data' => $rows,
            'count' => $rows->count(),
            'criteria' => [
                'category_id' => $src->category_id,
                'in_stock_only' => true,
                'min_price_toman' => (int) floor($ref * (1 - self::PRICE_SPREAD)),
                'max_price_toman' => (int) ceil($ref * (1 + self::PRICE_SPREAD)),
            ],
        ]);
    }

    // ─── helpers ────────────────────────────────────────────────

    private function query(): Builder
    {
        return Product::active()->with(['category', 'images', 'variants', 'bundleItems.variant']);
    }

    /** An active product by numeric id, else by slug. */
    private function find(string $key): ?Product
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }
        if (ctype_digit($key) && ($p = $this->query()->find((int) $key))) {
            return $p;
        }

        return $this->query()->where('slug', $key)->first();
    }

    private function row(Product $p): array
    {
        $a = $p->getAttributes();
        $variants = $p->variants->where('is_active', true)->sortBy('id')
            ->map(function (ProductVariant $v) use ($p) {
                $v->setRelation('product', $p); // effectivePrice/availableQty read the parent

                return [
                    'id' => $v->id,
                    'label' => implode(' / ', array_filter([$v->color, $v->size])) ?: null,
                    'size' => $v->size,
                    'color' => $v->color,
                    'stock_qty' => max(0, $v->availableQty()),
                    'in_stock' => $v->inStock(),
                    'price_toman' => $v->effectivePrice(),
                ];
            })->values();

        $updated = collect([$p->updated_at])->merge($p->variants->pluck('updated_at'))->filter()->max();
        $c = $p->category;

        return [
            'id' => $p->id,
            'slug' => $p->slug,
            // Raw columns: no locale accessor can swap in another language here.
            'name' => $a['name'] ?? null,
            'name_en' => filled($a['name_en'] ?? null) ? $a['name_en'] : null,
            'category' => $c ? ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->getAttributes()['name'] ?? null] : null,
            'url' => $this->absolute(route('product.show', $p, false)),
            'external_url' => $p->isExternal() ? $p->external_url : null,
            'image' => $this->absolute($p->primary_image_url),
            'price_toman' => (int) $p->price,
            'regular_price_toman' => $p->hasDiscount() ? (int) $p->compare_at_price : null,
            'on_sale' => $p->hasDiscount(),
            'discount_percent' => $p->discountPercent(),
            'in_stock' => $variants->contains('in_stock', true),
            'variants' => $variants,
            'updated_at' => $updated?->toIso8601String(),
            'stock_synced_at' => $p->stock_synced_at?->toIso8601String(),
        ];
    }

    /** Storefront URL from APP_URL, not the caller's Host header. */
    private function absolute(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /** @return list<string> */
    private function tokens(string $q): array
    {
        $all = preg_split('/\s+/u', $this->norm($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $long = array_values(array_filter($all, fn ($t) => mb_strlen($t) >= 2));

        return array_values(array_unique($long ?: $all));
    }

    /** Arabic ي/ك → Persian ی/ک, ZWNJ/tatweel/diacritics out, digits to Latin, lower-case. */
    private function norm(?string $s): string
    {
        $s = str_replace(
            ['ي', 'ى', 'ك', "\u{200C}", "\u{200D}", "\u{0640}"],
            ['ی', 'ی', 'ک', '', '', ''],
            (string) $s,
        );
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $s) ?? $s;
        $s = strtr($s, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $s = mb_strtolower($s);

        return trim(preg_replace('/[\s\-_\/،,.]+/u', ' ', $s) ?? $s);
    }
}

<?php

namespace App\Services\StockKeeping;

use App\Models\BundleItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Kits (bundles) and StoqS. A kit is a shop product with is_bundle and one placeholder variant;
 * its components are exact variants (bundle_items). A kit is «linked» when StoqS knows it too
 * (stockkeeping_id): StoqS then prices it, sells it as ONE line and puts a return back through
 * the placeholder variant. An unlinked kit stays the shop's own: its sale is reported as its
 * components and a return or cancel puts the components back.
 */
class StoqsKits
{
    public static function linked(?Product $p): bool
    {
        return $p !== null && (bool) $p->is_bundle && (string) $p->stockkeeping_id !== '';
    }

    /** A local product a StoqS product may be adopted onto (site_product_id): same kind, not linked yet. */
    public static function adoptable(array $data): ?Product
    {
        $spid = $data['site_product_id'] ?? null;
        if ($spid === null || $spid === '' || ! ctype_digit((string) $spid)) {
            return null;
        }
        $p = Product::whereKey((int) $spid)->first();
        if (! $p || (string) $p->stockkeeping_id !== '') {
            return null;   // never re-point an existing link
        }
        $isKit = is_array($data['bundle'] ?? null) || ! empty($data['is_bundle']);

        return (bool) $p->is_bundle === $isKit ? $p : null;
    }

    /**
     * The StoqS components of a kit payload on local variants: [rows, missing]. A component is
     * found by its StoqS variant id, else by its barcode (sku). rows = [local variant id, qty].
     *
     * @return array{0: array<int, array{0: int, 1: int}>, 1: array<int, string>}
     */
    public static function resolve(array $bundle): array
    {
        $rows = [];
        $missing = [];
        foreach ((array) ($bundle['items'] ?? []) as $it) {
            $vid = (int) ($it['variant_id'] ?? 0);
            $barcode = (string) ($it['barcode'] ?? '');
            $v = $vid ? ProductVariant::where('stockkeeping_variant_id', $vid)->first() : null;
            $v ??= $barcode !== '' ? ProductVariant::where('sku', $barcode)->first() : null;
            if (! $v) {
                $missing[] = $barcode !== '' ? $barcode : (string) $vid;

                continue;
            }
            $rows[] = [(int) $v->id, max(1, (int) ($it['qty'] ?? 1))];
        }

        return [$rows, $missing];
    }

    /** Replace a kit's components. */
    public static function setComponents(Product $kit, array $rows): void
    {
        $kit->bundleItems()->delete();
        foreach ($rows as $i => [$variantId, $qty]) {
            BundleItem::create(['bundle_product_id' => $kit->id, 'product_variant_id' => $variantId, 'quantity' => $qty, 'position' => $i]);
        }
        $kit->unsetRelation('bundleItems');
    }

    /** The kit's one placeholder variant takes StoqS's variant id and barcode (never stock). */
    public static function placeholder(Product $kit, array $payload): void
    {
        $v = (array) (($payload['variants'] ?? [])[0] ?? []);
        $barcode = (string) ($v['barcode'] ?? $v['sku'] ?? '');
        $variant = $kit->variants()->orderBy('id')->first()
            ?? ($barcode !== '' ? ProductVariant::where('sku', $barcode)->first() : null)
            ?? new ProductVariant(['size' => 'کیت', 'price' => null, 'stock_qty' => 0]);
        $variant->product_id = $kit->id;
        if ($barcode !== '' && ! ProductVariant::where('sku', $barcode)->where('id', '!=', (int) $variant->id)->exists()) {
            $variant->sku = $barcode;
        }
        if (! empty($v['variant_id'])) {
            $variant->stockkeeping_variant_id = (int) $v['variant_id'];
        }
        $variant->is_active = true;
        $variant->save();
    }

    /**
     * What a return or a cancel puts back for one order line: the shop's own stock (variant id
     * => pieces) and the StoqS stock-adjust lines. A linked kit goes to StoqS as its placeholder
     * variant (StoqS puts every component back); an unlinked kit, and a linked kit whose
     * placeholder StoqS has no id for, as its components. Plain lines as before.
     *
     * @return array{local: array<int, int>, stoqs: array<int, array>}
     */
    public static function restock(?ProductVariant $variant, int $qty, ?int $skVariantId, ?string $sku): array
    {
        $product = $variant?->product;
        if (! $product?->is_bundle) {
            $line = $skVariantId ? ['variant_id' => $skVariantId, 'delta' => $qty] : ($sku ? ['barcode' => $sku, 'delta' => $qty] : null);

            return ['local' => $variant ? [(int) $variant->id => $qty] : [], 'stoqs' => $line ? [$line] : []];
        }
        $product->loadMissing('bundleItems.variant');
        $local = [];
        $children = [];
        foreach ($product->bundleItems as $bi) {
            $c = $bi->variant;
            if (! $c) {
                continue;
            }
            $n = $qty * (int) $bi->quantity;
            $local[(int) $c->id] = ($local[(int) $c->id] ?? 0) + $n;
            $children[] = $c->stockkeeping_variant_id ? ['variant_id' => (int) $c->stockkeeping_variant_id, 'delta' => $n]
                : ($c->sku ? ['barcode' => (string) $c->sku, 'delta' => $n] : null);
        }
        $kitId = $skVariantId ?: ($variant->stockkeeping_variant_id ? (int) $variant->stockkeeping_variant_id : null);
        if (self::linked($product) && $kitId) {
            return ['local' => $local, 'stoqs' => [['variant_id' => $kitId, 'delta' => $qty]]];
        }

        return ['local' => $local, 'stoqs' => array_values(array_filter($children))];
    }
}

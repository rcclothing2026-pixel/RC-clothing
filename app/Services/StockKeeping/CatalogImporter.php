<?php

namespace App\Services\StockKeeping;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Creates/updates local products from a StoqS "published" product payload
 * (the /api/v1/catalog shape, also delivered by the product.published webhook).
 *
 * Correspondence is by BARCODE at the variant level: each website variant
 * (a colour+size cell) maps to one StoqS variant by barcode. StoqS models each
 * colour as a separate product, so products that share a name are merged into a
 * single website product carrying all colour+size variants — e.g. 2 colours × 3
 * sizes from two StoqS products become one website product with 6 variants.
 *
 * Categories and collections are created/assigned by name. The website keeps its
 * own presentation fields (summary, images, is_featured, web price) on update.
 */
class CatalogImporter
{
    /**
     * Per-product sync mode (products.stoqs_sync_mode, see StoqsContent): «ignore»
     * leaves the product untouched; «stock_only» and «manual» keep their name, text,
     * prices and photos (manual takes them only when $force — «دریافت از StoqS»).
     * A field the shop edited and has not sent yet is never overwritten.
     *
     * @param  array<string, mixed>  $data  one product payload
     */
    public function importProduct(array $data, bool $force = false): Product
    {
        $skId = (string) ($data['product_id'] ?? '');
        $name = trim((string) ($data['name'] ?? '')) ?: 'محصول';
        $price = (int) round((float) ($data['base_price'] ?? $data['price'] ?? 0));

        // Find the target product: the same StoqS product (re-import), else an
        // existing imported product with the same name (merge colours), else new.
        $product = Product::where('stockkeeping_id', $skId)->first()
            ?? Product::whereNotNull('stockkeeping_id')->where('name', $name)->first()
            ?? new Product;
        $isNew = ! $product->exists;
        $mode = StoqsContent::mode($product);
        if (! $isNew && $mode === 'ignore' && ! $force) {
            return $product;
        }
        $content = $isNew || $force || $mode === 'auto';
        $pending = $isNew || $force ? [] : StoqsContent::pending($product);
        $primary = $isNew || empty($product->stockkeeping_id) || (string) $product->stockkeeping_id === $skId;

        if ($content && ! in_array('name', $pending, true)) {
            $product->name = $name;
        }
        if (empty($product->stockkeeping_id)) {
            $product->stockkeeping_id = $skId;
        }
        $prices = $content && ! in_array('price', $pending, true);
        if ($prices) {
            $product->price = $price;
            // Outlet markdowns (admin → اوتلت) sit on top of the StoqS price.
            \App\Support\Outlet::apply($product, $price);
        }
        if ($content) {
            StoqsContent::pullText($product, $data, $primary, $pending);
        }
        $product->is_active = (bool) ($data['active'] ?? true);
        $product->stock_synced_at = now();

        // Multiple categories: prefer the `categories` list; fall back to the
        // single `category` string (older StoqS). The first is the PRIMARY,
        // kept in category_id; the full set is synced into the pivot after save.
        $cats = collect((array) ($data['categories'] ?? array_filter([$data['category'] ?? null])))
            ->map(fn ($n) => $this->resolveCategory($n))
            ->filter()->unique('id')->values();
        $product->category_id = $cats->first()?->id;

        if ($collection = $this->resolveCollection($data['collection'] ?? null)) {
            $product->collection_id = $collection->id;
        }

        if ($isNew || empty($product->slug)) {
            $product->slug = $this->uniqueSlug($name, $skId);
        }

        $product->save();
        $product->categories()->sync($cats->pluck('id')->all());

        $color = $data['color'] ?? null;
        foreach ($data['variants'] ?? [] as $v) {
            $this->importVariant($product, $v, $color);
        }
        if ($prices) {
            $this->applyVariantPrices($product, $data);   // sets StoqS size prices, never clears one
        }
        StoqsContent::remember($product, $data);
        if ($content && ! in_array('images', $pending, true)) {
            StoqsContent::pullImages($product, $data, $primary);
        }

        return $product;
    }

    /**
     * Per-size prices: a size whose StoqS price equals the product price (or, for an
     * outlet product, its base price) follows the product (null); others carry it.
     */
    private function applyVariantPrices(Product $product, array $data): void
    {
        $ref = \App\Support\Outlet::has((int) $product->id) && $product->compare_at_price ? (int) $product->compare_at_price : (int) $product->price;
        foreach ($data['variants'] ?? [] as $v) {
            $barcode = (string) ($v['barcode'] ?? $v['sku'] ?? '');
            if ($barcode === '' || ! isset($v['price'])) {
                continue;
            }
            $variant = ProductVariant::where('sku', $barcode)->where('product_id', $product->id)->first();
            $own = (int) round((float) $v['price']);
            $override = ($own === (int) $product->price || $own === $ref) ? null : $own;
            // RC has no price.changed handler, so nothing here may clear a shop size price.
            if ($variant && $override !== null && $variant->price !== $override) {
                $variant->price = $override;
                $variant->save();
            }
        }
    }

    private function importVariant(Product $product, array $v, ?string $color): void
    {
        $barcode = (string) ($v['barcode'] ?? $v['sku'] ?? '');
        if ($barcode === '') {
            return;
        }

        // Match by barcode globally so a variant never duplicates across products.
        $variant = ProductVariant::where('sku', $barcode)->first() ?? new ProductVariant;

        $variant->product_id = $product->id;
        $variant->sku = $barcode;
        $variant->size = $v['size'] ?? $variant->size;
        // v4.2: StoqS now sends colour per-variant. Prefer it; fall back to the
        // product-level colour for legacy rows that don't carry one yet.
        $vColor = ($v['color'] ?? '') !== '' ? $v['color'] : $color;
        if ($vColor) {
            $variant->color = $vColor;
        }
        if (($v['color_hex'] ?? '') !== '') {
            $variant->color_hex = $v['color_hex'];
        }
        if (! empty($v['variant_id'])) {
            $variant->stockkeeping_variant_id = (int) $v['variant_id'];
        }
        $variant->is_active = (bool) ($v['active'] ?? true);
        // stock_qty is owned by the stock sync (pull/webhook) — never set here.
        $variant->save();
    }

    private function resolveCategory(?string $name): ?Category
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Category::firstOrCreate(
            ['slug' => $this->uniqueTaxonomySlug(Category::class, $name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function resolveCollection(?string $name): ?Collection
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Collection::firstOrCreate(
            ['slug' => $this->uniqueTaxonomySlug(Collection::class, $name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    /** Deterministic slug from a name so the same StoqS name maps to one row. */
    private function uniqueTaxonomySlug(string $model, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            // Persian names slugify to empty — derive a stable token from the name.
            $base = 'tax-'.substr(md5($name), 0, 8);
        }

        return $base;
    }

    private function uniqueSlug(string $name, string $skId): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'mahsool';
        }

        return $base.'-'.$skId;
    }
}

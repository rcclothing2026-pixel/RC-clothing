<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\SizeGuide;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $products = Product::with(['category', 'categories', 'collection', 'variants', 'images'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->integer('category'), fn ($q, $id) => $q->whereHas('categories', fn ($qq) => $qq->where('categories.id', $id)))
            ->when($request->integer('collection'), fn ($q, $id) => $q->where('collection_id', $id))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->orderByDesc('id') // deterministic tiebreaker — imports share created_at, else pages duplicate/skip
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'collections' => Collection::orderBy('name')->get(),
            'sizeGuides' => SizeGuide::orderBy('name')->get(),
        ]);
    }

    /** Bulk operations on selected products. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulk_action' => ['required', 'in:activate,deactivate,delete,category,collection'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'collection_id' => ['nullable', 'exists:collections,id'],
        ]);

        $query = Product::whereIn('id', $data['ids']);
        $count = (clone $query)->count();

        match ($data['bulk_action']) {
            'activate' => $query->update(['is_active' => true]),
            'deactivate' => $query->update(['is_active' => false]),
            'delete' => $query->delete(),
            'category' => $this->bulkSetCategory($data['ids'], $data['category_id'] ?? null),
            'collection' => $query->update(['collection_id' => $data['collection_id'] ?? null]),
        };

        return back()->with('success', \App\Support\Money::toPersianDigits((string) $count).' محصول به‌روزرسانی شد.');
    }

    /** Bulk-set the primary category on the given products and keep the pivot in sync. */
    private function bulkSetCategory(array $ids, ?int $categoryId): void
    {
        Product::whereIn('id', $ids)->update(['category_id' => $categoryId]);
        foreach (Product::whereIn('id', $ids)->get() as $product) {
            $product->categories()->sync($categoryId ? [$categoryId] : []);
        }
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product(['is_active' => true]),
            'categories' => Category::orderBy('name')->get(),
            'collections' => Collection::orderBy('name')->get(),
            'sizeGuides' => SizeGuide::orderBy('name')->get(),
            'bundleCandidates' => $this->bundleCandidates(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);

        $product = Product::create([
            ...$data['core'],
            'slug' => Str::slug(Str::ascii($data['core']['name'])).'-'.Str::lower(Str::random(5)),
        ]);

        $product->categories()->sync($data['category_ids']);
        if ($product->is_bundle) {
            $this->syncBundle($product, (array) $request->input('bundle_items', []));
        } else {
            $this->syncVariants($product, $request);
        }
        $this->handleImages($product, $request);

        return redirect()->route('admin.products.edit', $product)->with('success', 'محصول ایجاد شد.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load(['variants', 'images', 'bundleItems.variant.product']),
            'categories' => Category::orderBy('name')->get(),
            'collections' => Collection::orderBy('name')->get(),
            'sizeGuides' => SizeGuide::orderBy('name')->get(),
            'bundleCandidates' => $this->bundleCandidates($product->id),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validateProduct($request);

        $product->update($data['core']);
        $product->categories()->sync($data['category_ids']);
        if ($product->is_bundle) {
            $this->syncBundle($product, (array) $request->input('bundle_items', []));
        } else {
            // If a product was previously a bundle, clear its bundle items.
            if ($product->bundleItems()->exists()) {
                $product->bundleItems()->delete();
            }
            $this->syncVariants($product, $request);
        }
        $this->handleImages($product, $request);

        return redirect()->route('admin.products.edit', $product)->with('success', 'تغییرات ذخیره شد.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'محصول حذف شد.');
    }

    private function validateProduct(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'collection_id' => ['nullable', 'exists:collections,id'],
            'size_guide_id' => ['nullable', 'exists:size_guides,id'],
            'summary' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_bundle' => ['nullable', 'boolean'],
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.variant_id' => ['required_with:bundle_items', 'integer', 'exists:product_variants,id'],
            'bundle_items.*.quantity' => ['required_with:bundle_items', 'integer', 'min:1', 'max:99'],
        ]);

        // Multiple categories (category_ids[]); fall back to the legacy single.
        // First = primary, kept in products.category_id.
        $catIds = array_values(array_unique(array_map('intval', $validated['category_ids'] ?? [])));
        if (! $catIds && ! empty($validated['category_id'])) {
            $catIds = [(int) $validated['category_id']];
        }

        return [
            'category_ids' => $catIds,
            'core' => [
                'name' => $validated['name'],
                'category_id' => $catIds[0] ?? null,
                'collection_id' => $validated['collection_id'] ?? null,
                'size_guide_id' => $validated['size_guide_id'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'description' => $this->purify($validated['description'] ?? ''),
                'price' => $validated['price'],
                'compare_at_price' => $validated['compare_at_price'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'is_featured' => $request->boolean('is_featured'),
                'is_bundle' => $request->boolean('is_bundle'),
            ],
        ];
    }

    /**
     * Variants that can be picked as items inside a gift bundle: any variant of
     * any non-bundle product. Excluding bundles prevents nested bundles (which
     * would complicate stock derivation). Excluding the current product avoids a
     * bundle that contains itself.
     */
    private function bundleCandidates(?int $excludeProductId = null): \Illuminate\Support\Collection
    {
        return ProductVariant::query()
            ->whereHas('product', fn ($q) => $q->where('is_bundle', false)
                ->when($excludeProductId, fn ($q) => $q->whereKeyNot($excludeProductId)))
            ->with('product:id,name')
            ->orderBy('product_id')
            ->get(['id', 'product_id', 'size', 'color', 'sku'])
            ->map(fn (ProductVariant $v) => [
                'id' => (int) $v->id,
                'label' => trim($v->product?->name.' · '.($v->size ?: '—').($v->color ? ' · '.$v->color : '').($v->sku ? ' · '.$v->sku : '')),
            ]);
    }

    /**
     * Persist the (variant_id, quantity) rows for a bundle and ensure the bundle
     * has exactly ONE placeholder variant — the one cart/order/payment use. The
     * placeholder carries no SKU and no StoqS variant id, so stock pulls and
     * inventory sync silently ignore it; real stock is derived from children.
     */
    private function syncBundle(Product $product, array $rows): void
    {
        $clean = collect($rows)
            ->filter(fn ($r) => ! empty($r['variant_id']) && (int) ($r['quantity'] ?? 0) >= 1)
            ->groupBy(fn ($r) => (int) $r['variant_id']) // merge duplicates
            ->map(fn ($g) => ['variant_id' => (int) $g->first()['variant_id'], 'quantity' => (int) $g->sum('quantity')])
            ->values();

        $product->bundleItems()->delete();
        foreach ($clean as $i => $row) {
            \App\Models\BundleItem::create([
                'bundle_product_id' => $product->id,
                'product_variant_id' => $row['variant_id'],
                'quantity' => $row['quantity'],
                'position' => $i,
            ]);
        }

        // Drop any historical own-variants the product may have had before being
        // converted, then ensure exactly one placeholder. Keep its id stable across
        // re-saves so existing carts/orders still resolve.
        $placeholder = $product->variants()->first();
        if (! $placeholder) {
            $product->variants()->create([
                'size' => '—', 'color' => null, 'color_hex' => null, 'sku' => null,
                'price' => null, 'stock_qty' => 0, 'is_active' => true,
                'stockkeeping_variant_id' => null,
            ]);
        } else {
            $product->variants()->whereKeyNot($placeholder->id)->delete();
            $placeholder->update([
                'size' => '—', 'color' => null, 'color_hex' => null, 'sku' => null,
                'is_active' => true, 'stockkeeping_variant_id' => null,
            ]);
        }
    }

    /**
     * Sanitize the rich-text description (rendered raw on the storefront). Allows
     * common formatting + links/images/tables and inline align/color styles;
     * strips scripts, event handlers, and anything else.
     */
    private function purify(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p[style|dir],br,b,strong,i,em,u,s,span[style],div[style|dir],h1[style|dir],h2[style|dir],h3[style|dir],h4,ul,ol,li,a[href|title|target|rel],img[src|alt|width|height|style],blockquote[dir],pre,code,small,sub,sup,hr,table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan]');
        $config->set('CSS.AllowedProperties', 'text-align,color,background-color,font-weight,font-style,text-decoration,direction,width,height');
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier'));
        if (! is_dir(storage_path('app/htmlpurifier'))) {
            @mkdir(storage_path('app/htmlpurifier'), 0775, true);
        }

        return (new HTMLPurifier($config))->purify($html);
    }

    /** Create/update/delete variants (size, color, stock, optional price). */
    private function syncVariants(Product $product, Request $request): void
    {
        $variants = $request->input('variants', []);

        foreach ($variants as $row) {
            $delete = ! empty($row['_delete']);
            $id = $row['id'] ?? null;

            if ($id) {
                $variant = $product->variants()->whereKey($id)->first();
                if (! $variant) {
                    continue;
                }
                if ($delete) {
                    $variant->delete();

                    continue;
                }
                $variant->update($this->variantAttributes($row));
            } elseif (! $delete && (! empty($row['size']) || ! empty($row['color']))) {
                $product->variants()->create($this->variantAttributes($row));
            }
        }
    }

    private function variantAttributes(array $row): array
    {
        return [
            'size' => $row['size'] ?? null,
            'color' => $row['color'] ?? null,
            'color_hex' => $row['color_hex'] ?? null,
            'sku' => $row['sku'] ?? null,
            'price' => ($row['price'] ?? '') !== '' ? (int) $row['price'] : null,
            'stock_qty' => (int) ($row['stock_qty'] ?? 0),
            'is_active' => ! empty($row['is_active']),
            'stockkeeping_variant_id' => $row['stockkeeping_variant_id'] ?? null,
        ];
    }

    /** Handle deletes, primary selection, and uploaded image files. */
    private function handleImages(Product $product, Request $request): void
    {
        foreach ((array) $request->input('delete_images', []) as $imageId) {
            $product->images()->whereKey($imageId)->delete();
        }

        // Apply the admin's drag order (comma-separated existing image ids) so the
        // storefront gallery shows them in that order. New uploads append after.
        if ($order = $request->input('image_order')) {
            $pos = 0;
            foreach (array_filter(array_map('intval', explode(',', $order))) as $id) {
                $product->images()->whereKey($id)->update(['position' => ++$pos]);
            }
        }

        if ($request->hasFile('images')) {
            $request->validate(['images.*' => ['image', 'max:4096']]);
            $position = (int) $product->images()->max('position');
            foreach ($request->file('images') as $file) {
                $media = Media::createFromUploadedFile(
                    $file, 'products', $product->name, (int) $request->user()?->id
                );
                $product->images()->create([
                    'path' => $media->url,
                    'alt' => $product->name,
                    'position' => ++$position,
                ]);
            }
        }

        // The first image in the gallery order is the featured (primary) one.
        $first = $product->images()->orderBy('position')->orderBy('id')->first();
        if ($first) {
            $product->images()->where('id', '!=', $first->id)->update(['is_primary' => false]);
            $first->update(['is_primary' => true]);
        }
    }
}

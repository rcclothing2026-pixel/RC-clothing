<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::where('is_active', true)->orderBy('position')->get();

        $activeCategory   = null;
        $activeCollection = null;

        $query = Product::active()->with(['images', 'variants', 'category']);

        // Filter: category — match ANY of the product's categories (multi-category).
        if ($slug = $request->string('category')->toString()) {
            $activeCategory = Category::where('slug', $slug)->where('is_active', true)->first();
            $query->whereHas('categories', fn ($q) => $q->where('slug', $slug));
        }

        // Filter: collection
        if ($cslug = $request->string('collection')->toString()) {
            $activeCollection = Collection::where('slug', $cslug)->where('is_active', true)->first();
            $query->whereHas('collection', fn ($q) => $q->where('slug', $cslug));
        }

        // Search via Scout (Meilisearch) when reachable; falls back to a plain DB
        // search so the shop never 500s if the search engine is down/not installed.
        $term = $request->string('q')->toString();
        if ($term) {
            $query->whereIn('id', $this->searchProductIds($term));
        }

        // Filter: price range (Toman)
        if ($min = $request->integer('min')) {
            $query->where('price', '>=', $min);
        }
        if ($max = $request->integer('max')) {
            $query->where('price', '<=', $max);
        }

        // Filter: size (matches any active variant of that size)
        if ($size = $request->string('size')->toString()) {
            $query->whereHas('variants', fn ($q) => $q->where('size', $size)->where('is_active', true));
        }

        // Filter: in-stock only — at least one active variant with stock > 0
        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', fn ($q) => $q->where('is_active', true)->where('stock', '>', 0));
        }

        // Sort. «پرفروش» (bestseller) orders by total quantity sold across all
        // order items; «پربازدید» (popular) by the product's view counter.
        // Previously both fell through to `latest()`, so the three options
        // rendered identically — that's the bug being fixed here.
        match ($request->string('sort')->toString()) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'cheapest'   => $query->orderBy('price'),
            'popular'    => $query->orderByDesc('views'),
            'bestseller' => $query->withSum('orderItems as sold_qty', 'quantity')->orderByDesc('sold_qty'),
            default      => $query->latest(),
        };
        // Unique tiebreaker so paginated pages never duplicate/skip rows when the
        // primary sort (price / created_at / views / sold) ties across products.
        $query->orderByDesc('id');

        $products = $query->paginate(12)->withQueryString();

        $sizes = ['S', 'M', 'L', 'XL'];

        // Real catalogue ceiling for the price slider — pulled from the
        // active products so the slider's right-hand stop always sits a step
        // above the most expensive item (no more «slider capped at 5M while
        // there's a 12M item in the catalogue» bug). Rounded up to the next
        // 100k. Falls back to 5M if the catalogue is empty.
        $catalogueMax = (int) (Product::active()->max('price') ?: 5_000_000);
        $maxPrice = (int) ceil($catalogueMax / 100_000) * 100_000;

        return view('shop.index', compact('products', 'categories', 'sizes', 'activeCategory', 'activeCollection', 'maxPrice'));
    }

    public function suggest(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        // Only suggest products that are actually in the shop (active) — otherwise
        // inactive products appear in search but not the list and 404 when opened.
        $ids = $this->searchProductIds($q, 20);
        $results = Product::active()->with('images')->whereIn('id', $ids)->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids->all())) // keep search relevance order
            ->take(6)
            ->map(fn ($p) => [
                'name'  => $p->name,
                'url'   => route('product.show', $p),
                'price' => $p->formattedPrice(),
                'image' => $p->primary_image_url ? \App\Support\ImageOptimizer::thumbUrl($p->primary_image_url) : null,
            ])->values();

        return response()->json($results);
    }

    /**
     * Product IDs matching a search term. Uses Scout (Meilisearch) when the engine
     * is reachable; on any failure (engine down / not installed on the host) it
     * falls back to a plain DB search so search never throws a 500.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function searchProductIds(string $term, int $limit = 60): \Illuminate\Support\Collection
    {
        try {
            return Product::search($term)->take($limit)->keys();
        } catch (\Throwable $e) {
            report($e);

            return Product::query()
                ->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('summary', 'like', "%{$term}%")
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%")))
                ->limit($limit)
                ->pluck('id');
        }
    }
}

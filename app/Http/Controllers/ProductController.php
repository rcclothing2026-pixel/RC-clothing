<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        // Bump the view counter (powers the shop's «پربازدید» sort). Atomic
        // increment — no model event, no timestamp touch, no race.
        $product->newQuery()->whereKey($product->getKey())->increment('views');

        $product->load(['images', 'variants', 'category', 'categories', 'sizeGuide']);
        if ($product->is_bundle) {
            $product->load(['bundleItems.variant.product.images']);
        }

        // Group variants for the size/color pickers. Bundles use a single
        // placeholder variant — show no pickers; the «شامل» list below stands in.
        $colors = $product->is_bundle ? collect() : $product->variants
            ->whereNotNull('color')
            ->unique('color')
            ->map(fn ($v) => ['name' => $v->color, 'hex' => $v->color_hex])
            ->values();

        $sizes = $product->is_bundle ? collect() : $product->variants
            ->whereNotNull('size')
            ->pluck('size')
            ->unique()
            ->values();

        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with(['images', 'variants'])
            ->take(4)
            ->get();

        $inWishlist = auth()->check()
            && auth()->user()->wishlist()->where('product_id', $product->id)->exists();

        return view('product.show', compact('product', 'colors', 'sizes', 'related', 'inWishlist'));
    }
}

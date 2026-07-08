<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /gift — interactive gift-discovery page. The editable hero/intro is the
 * page-builder page at slug `gift` (so admin can keep using the page-builder
 * for the top of the page), and the interactive filter-grid below uses
 * Collections whose slug starts with `gift-` as facets — admin curates which
 * collections appear by simply prefixing their slug.
 *
 * Filters (all optional, all GET): collection, min, max, sort. The page is
 * AJAX-swappable via the same `#shop-frame` contract as /shop, so dropping
 * any filter is a no-reload swap.
 *
 * /gift/surprise — a fun «one random in-budget gift» pick (?max=500000),
 * redirects straight to the product page.
 */
class GiftController extends Controller
{
    public function index(Request $request): View
    {
        $page = Page::query()->where('slug', 'gift')->first();
        $facets = Collection::query()
            ->where('is_active', true)
            ->where('slug', 'like', 'gift-%')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        // Products: limit to those in any gift- collection. Admin curates
        // gift-eligible products by tagging them to a gift- collection.
        $query = Product::active()->with(['images', 'variants', 'collection']);
        $facetIds = $facets->pluck('id');
        if ($facetIds->isNotEmpty()) {
            $query->whereIn('collection_id', $facetIds);
        }

        $activeFacet = null;
        if ($slug = $request->string('collection')->toString()) {
            $activeFacet = $facets->firstWhere('slug', $slug);
            if ($activeFacet) {
                $query->where('collection_id', $activeFacet->id);
            }
        }

        if ($min = $request->integer('min')) {
            $query->where('price', '>=', $min);
        }
        if ($max = $request->integer('max')) {
            $query->where('price', '<=', $max);
        }

        match ($request->string('sort')->toString()) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default      => $query->latest(),
        };
        $query->orderByDesc('id');

        $products = $query->paginate(12)->withQueryString();

        return view('gift.index', compact('page', 'facets', 'activeFacet', 'products'));
    }

    /**
     * "Surprise me" — pick one random in-budget gift and redirect to it.
     * Defaults to under 500k Toman; admin can change via ?max=NNN.
     */
    public function surprise(Request $request): RedirectResponse
    {
        $max = (int) ($request->integer('max') ?: 500_000);

        $facetIds = Collection::query()
            ->where('is_active', true)
            ->where('slug', 'like', 'gift-%')
            ->pluck('id');

        $product = Product::active()
            ->when($facetIds->isNotEmpty(), fn ($q) => $q->whereIn('collection_id', $facetIds))
            ->where('price', '<=', $max)
            ->inRandomOrder()
            ->first();

        if (! $product) {
            return redirect()->route('gift.index')->with('error', 'متأسفانه پیشنهادی در این محدوده پیدا نشد.');
        }

        return redirect()->route('product.show', $product);
    }
}

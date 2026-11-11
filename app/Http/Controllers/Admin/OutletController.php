<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutletItem;
use App\Models\Product;
use App\Services\StockKeeping\StoqsPromotions;
use App\Support\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «اوتلت»: choose products for the outlet and set each one's discount. While StoqS
 * manages this site's promotions the page shows StoqS's outlet, read-only, and every
 * change is refused (the shop's own items are kept for when it runs them again).
 */
class OutletController extends Controller
{
    public function index(): View
    {
        if (StoqsPromotions::managed()) {
            $pct = Outlet::percents();
            $rows = Product::whereIn('id', array_keys($pct) ?: [0])->orderBy('name')->get()
                ->map(fn ($p) => ['product' => $p, 'percent' => (int) $pct[$p->id]])->all();

            return view('admin.outlet.index', ['managed' => true, 'stoqsOutlet' => $rows, 'items' => collect(), 'candidates' => collect(),
                'max' => (int) config('shop.outlet_max_percent', 30)]);
        }
        $items = OutletItem::with('product')->latest()->get();
        $candidates = Product::active()->with(['category', 'categories'])->whereNotIn('id', $items->pluck('product_id'))
            ->orderBy('name')->get()->filter(fn ($p) => Outlet::allowed($p))->values();

        return view('admin.outlet.index', ['items' => $items, 'candidates' => $candidates, 'max' => (int) config('shop.outlet_max_percent', 30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (StoqsPromotions::managed()) {
            return back()->withErrors(['percent' => Outlet::MANAGED]);
        }
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id', Rule::unique('outlet_items', 'product_id')],
            'percent' => ['required', 'integer', 'min:1', 'max:'.(int) config('shop.outlet_max_percent', 30)],
        ]);
        $product = Product::with(['category', 'categories'])->findOrFail($data['product_id']);
        if (! Outlet::allowed($product)) {
            return back()->withErrors(['product_id' => 'اقلام زمستانه و بسته‌ها به اوتلت نمی‌روند.']);
        }
        if ($why = Outlet::problem($product, (int) $data['percent'])) {
            return back()->withErrors(['percent' => $why]);
        }

        OutletItem::create($data + ['is_active' => true]);
        Outlet::apply($product);
        $product->save();

        return back()->with('success', '«'.$product->name.'» به اوتلت رفت.');
    }

    public function update(Request $request, OutletItem $outlet): RedirectResponse
    {
        if (StoqsPromotions::managed()) {
            return back()->withErrors(['percent' => Outlet::MANAGED]);
        }
        $data = $request->validate([
            'percent' => ['required', 'integer', 'min:1', 'max:'.(int) config('shop.outlet_max_percent', 30)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $product = $outlet->product;
        if ($why = Outlet::problem($product->loadMissing(['category', 'categories']), (int) $data['percent'])) {
            return back()->withErrors(['percent' => $why]);
        }
        Outlet::restore($product);
        $outlet->update(['percent' => $data['percent'], 'is_active' => $request->boolean('is_active')]);
        Outlet::apply($product);
        $product->save();

        return back()->with('success', 'اوتلت «'.$product->name.'» به‌روز شد.');
    }

    public function destroy(OutletItem $outlet): RedirectResponse
    {
        if (StoqsPromotions::managed()) {
            return back()->withErrors(['percent' => Outlet::MANAGED]);
        }
        $product = $outlet->product;
        $outlet->delete();
        if ($product) {
            Outlet::restore($product);
            $product->save();
        }

        return back()->with('success', 'از اوتلت برداشته شد.');
    }
}

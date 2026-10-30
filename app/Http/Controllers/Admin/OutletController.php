<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutletItem;
use App\Models\Product;
use App\Support\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** «اوتلت»: choose products for the outlet and set each one's discount. */
class OutletController extends Controller
{
    public function index(): View
    {
        $items = OutletItem::with('product')->latest()->get();
        $candidates = Product::active()->with(['category', 'categories'])->whereNotIn('id', $items->pluck('product_id'))
            ->orderBy('name')->get()->filter(fn ($p) => Outlet::allowed($p))->values();

        return view('admin.outlet.index', ['items' => $items, 'candidates' => $candidates, 'max' => (int) config('shop.outlet_max_percent', 30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id', Rule::unique('outlet_items', 'product_id')],
            'percent' => ['required', 'integer', 'min:1', 'max:'.(int) config('shop.outlet_max_percent', 30)],
        ]);
        $product = Product::with(['category', 'categories'])->findOrFail($data['product_id']);
        if (! Outlet::allowed($product)) {
            return back()->withErrors(['product_id' => 'اقلام زمستانه و بسته‌ها به اوتلت نمی‌روند.']);
        }

        OutletItem::create($data + ['is_active' => true]);
        Outlet::apply($product);
        $product->save();

        return back()->with('success', '«'.$product->name.'» به اوتلت رفت.');
    }

    public function update(Request $request, OutletItem $outlet): RedirectResponse
    {
        $data = $request->validate([
            'percent' => ['required', 'integer', 'min:1', 'max:'.(int) config('shop.outlet_max_percent', 30)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $product = $outlet->product;
        Outlet::restore($product);
        $outlet->update(['percent' => $data['percent'], 'is_active' => $request->boolean('is_active')]);
        Outlet::apply($product);
        $product->save();

        return back()->with('success', 'اوتلت «'.$product->name.'» به‌روز شد.');
    }

    public function destroy(OutletItem $outlet): RedirectResponse
    {
        $product = $outlet->product;
        $outlet->delete();
        if ($product) {
            Outlet::restore($product);
            $product->save();
        }

        return back()->with('success', 'از اوتلت برداشته شد.');
    }
}

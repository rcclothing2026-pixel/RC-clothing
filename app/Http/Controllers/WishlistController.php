<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.wishlist', [
            'products' => $request->user()->wishlist()->with(['images', 'variants'])->get(),
        ]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $result = $request->user()->wishlist()->toggle($product->id);
        $added = ! empty($result['attached']);

        return back()->with('success', $added ? 'به علاقه‌مندی‌ها افزوده شد.' : 'از علاقه‌مندی‌ها حذف شد.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->user()->wishlist()->detach();

        return back()->with('success', 'همه علاقه‌مندی‌ها حذف شد.');
    }
}

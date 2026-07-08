<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // The homepage is always rendered through the page builder and is fully
        // editable in admin; it is provisioned from the default layout when
        // none exists. Falls back to the legacy view only when migrations
        // haven't been run yet (so a fresh install doesn't 500).
        if ($home = Page::provisionHome()) {
            return view('page', ['page' => $home]);
        }

        $featured = Product::active()->featured()->with(['images', 'variants'])->latest()->take(8)->get();
        $newArrivals = Product::active()->with(['images', 'variants'])->latest()->take(8)->get();
        $categories = Category::where('is_active', true)->orderBy('position')->get();

        return view('home', compact('featured', 'newArrivals', 'categories'));
    }
}

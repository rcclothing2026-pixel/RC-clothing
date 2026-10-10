<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\StockNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'phone'      => ['required', 'regex:/^09[0-9]{9}$/'],
        ]);

        $variant = ProductVariant::findOrFail($data['variant_id']);

        if ($variant->inStock()) {
            return back()->with('status', 'این محصول موجود است.');
        }

        // firstOrCreate would re-use an already-notified row; reset the flag
        // so the next re-stock fires for this shopper again. The unique
        // index keeps it one-row-per-(variant, phone).
        $alert = StockNotification::firstOrNew([
            'product_variant_id' => $data['variant_id'],
            'phone' => $data['phone'],
        ]);
        $alert->notified = false;
        $alert->notified_at = null;
        $alert->save();

        return back()->with('status', 'به محض موجود شدن از طریق پیامک اطلاع‌رسانی می‌شود.');
    }
}

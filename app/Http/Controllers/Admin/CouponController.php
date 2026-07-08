<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.coupons.index', ['coupons' => Coupon::latest()->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['type' => 'percent', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('success', 'کد تخفیف ساخته شد.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', ['coupon' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon));

        return redirect()->route('admin.coupons.index')->with('success', 'کد تخفیف به‌روزرسانی شد.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return back()->with('success', 'کد تخفیف حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $v = $request->validate([
            'code' => ['required', 'string', 'max:60', 'unique:coupons,code'.($coupon ? ','.$coupon->id : '')],
            'description' => ['nullable', 'string', 'max:200'],
            'type' => ['required', 'in:percent,fixed,free_shipping'],
            'value' => ['nullable', 'integer', 'min:0'],
            'min_subtotal' => ['nullable', 'integer', 'min:0'],
            'max_subtotal' => ['nullable', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'first_order_only' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'code' => mb_strtoupper(trim($v['code'])),
            'description' => $v['description'] ?? null,
            'type' => $v['type'],
            'value' => (int) ($v['value'] ?? 0),
            'min_subtotal' => (int) ($v['min_subtotal'] ?? 0),
            'max_subtotal' => ($v['max_subtotal'] ?? null) !== null ? (int) $v['max_subtotal'] : null,
            'max_discount' => ($v['max_discount'] ?? null) !== null ? (int) $v['max_discount'] : null,
            'usage_limit' => $v['usage_limit'] ?? null,
            'per_user_limit' => $v['per_user_limit'] ?? null,
            'first_order_only' => $request->boolean('first_order_only'),
            // Jalali strings from the picker → Gregorian for storage.
            'starts_at' => Jalali::parse($v['starts_at'] ?? null),
            'expires_at' => Jalali::parse($v['expires_at'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}

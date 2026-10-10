<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    public function index(): View
    {
        return view('admin.shipping.index', [
            'methods' => ShippingMethod::orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.shipping.form', [
            'method' => new ShippingMethod(['is_active' => true, 'position' => 0, 'price' => 0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ShippingMethod::create($this->validated($request));

        return redirect()->route('admin.shipping.index')->with('success', 'روش ارسال اضافه شد.');
    }

    public function edit(ShippingMethod $shipping): View
    {
        return view('admin.shipping.form', ['method' => $shipping]);
    }

    public function update(Request $request, ShippingMethod $shipping): RedirectResponse
    {
        $shipping->update($this->validated($request));

        return redirect()->route('admin.shipping.index')->with('success', 'روش ارسال «'.$shipping->name.'» به‌روزرسانی شد.');
    }

    public function destroy(ShippingMethod $shipping): RedirectResponse
    {
        $shipping->delete();

        return back()->with('success', 'روش ارسال حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'free_over' => ['nullable', 'integer', 'min:0'],
            'cost_on_delivery' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'zone_province' => ['nullable', 'array'],
            'zone_province.*' => ['nullable', 'string', 'max:60'],
            'zone_price' => ['nullable', 'array'],
            'zone_price.*' => ['nullable', 'integer', 'min:0'],
        ]);

        // Per-province cost overrides: { province => price }.
        $zones = [];
        foreach ((array) $request->input('zone_province', []) as $i => $prov) {
            $prov = trim((string) $prov);
            if ($prov !== '') {
                $zones[$prov] = (int) (($request->input('zone_price', [])[$i]) ?? 0);
            }
        }

        return [
            'name' => $v['name'],
            'description' => $v['description'] ?? null,
            'price' => (int) $v['price'],
            'free_over' => ($v['free_over'] ?? null) !== null ? (int) $v['free_over'] : null,
            'cost_on_delivery' => $request->boolean('cost_on_delivery'),
            'zones' => $zones ?: null,
            'position' => (int) ($v['position'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}

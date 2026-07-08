<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.payments', [
            'methods' => PaymentMethod::orderBy('position')->get(),
        ]);
    }

    public function update(Request $request, PaymentMethod $method): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'sandbox' => ['nullable', 'boolean'],
            'config' => ['nullable', 'array'],
        ]);

        // Only persist the known config fields for this gateway.
        $config = $method->config ?? [];
        foreach (array_keys(PaymentMethod::fieldsFor($method->key)) as $field) {
            $config[$field] = $request->input("config.$field");
        }

        $method->update([
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sandbox' => $request->boolean('sandbox'),
            'config' => $config,
        ]);

        // Exactly one default among active methods.
        if ($request->boolean('is_default')) {
            PaymentMethod::where('id', '!=', $method->id)->update(['is_default' => false]);
            $method->update(['is_default' => true, 'is_active' => true]);
        }

        return back()->with('success', 'تنظیمات درگاه «'.$method->label.'» ذخیره شد.');
    }
}

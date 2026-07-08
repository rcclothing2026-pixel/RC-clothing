<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\DiscountRule;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountRuleController extends Controller
{
    public function index(): View
    {
        return view('admin.discount-rules.index', [
            'rules' => DiscountRule::latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.discount-rules.form', [
            'rule' => new DiscountRule,
            'conditionOptions' => DiscountRule::conditionOptions(),
            'actionOptions' => DiscountRule::actionOptions(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'collections' => Collection::orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::active()->orderBy('position')->get(['key', 'label']),
            'shippingMethods' => ShippingMethod::where('is_active', true)->orderBy('position')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['conditions'] = $this->parseConditions($request);
        $data['actions'] = $this->parseActions($request);
        $data['apply_mode'] = $request->input('apply_mode', 'all');
        $data['stack_mode'] = $request->input('stack_mode', 'best');
        $data['priority'] = (int) ($data['priority'] ?? 0); // column is NOT NULL; blank → 0
        $data['is_active'] = $request->boolean('is_active');
        $data['starts_at'] = Jalali::parse($data['starts_at'] ?? null);
        $data['expires_at'] = Jalali::parse($data['expires_at'] ?? null);

        DiscountRule::create($data);

        return redirect()->route('admin.discount-rules.index')->with('success', 'قانون تخفیف ایجاد شد.');
    }

    public function edit(DiscountRule $discountRule): View
    {
        return view('admin.discount-rules.form', [
            'rule' => $discountRule,
            'conditionOptions' => DiscountRule::conditionOptions(),
            'actionOptions' => DiscountRule::actionOptions(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'collections' => Collection::orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::active()->orderBy('position')->get(['key', 'label']),
            'shippingMethods' => ShippingMethod::where('is_active', true)->orderBy('position')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, DiscountRule $discountRule): RedirectResponse
    {
        $data = $this->validated($request);
        $data['conditions'] = $this->parseConditions($request);
        $data['actions'] = $this->parseActions($request);
        $data['apply_mode'] = $request->input('apply_mode', 'all');
        $data['stack_mode'] = $request->input('stack_mode', 'best');
        $data['priority'] = (int) ($data['priority'] ?? 0); // column is NOT NULL; blank → 0
        $data['is_active'] = $request->boolean('is_active');
        $data['starts_at'] = Jalali::parse($data['starts_at'] ?? null);
        $data['expires_at'] = Jalali::parse($data['expires_at'] ?? null);

        $discountRule->update($data);

        return redirect()->route('admin.discount-rules.index')->with('success', 'قانون تخفیف به‌روزرسانی شد.');
    }

    public function destroy(DiscountRule $discountRule): RedirectResponse
    {
        $discountRule->delete();

        return back()->with('success', 'قانون تخفیف حذف شد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'priority' => ['nullable', 'integer', 'min:-999', 'max:999'],
            'starts_at' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'string'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function parseConditions(Request $request): array
    {
        $types = (array) $request->input('cond_type', []);
        $result = [];

        foreach ($types as $i => $type) {
            if (!isset(DiscountRule::conditionOptions()[$type])) continue;
            $params = (array) ($request->input('cond_params', [])[$i] ?? []);
            $entry = ['type' => $type];

            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $value = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
                }
                if ($value !== '' && $value !== null && $value !== []) {
                    $entry[$key] = $value;
                }
            }

            $result[] = $entry;
        }

        return $result;
    }

    private function parseActions(Request $request): array
    {
        $types = (array) $request->input('act_type', []);
        $result = [];

        foreach ($types as $i => $type) {
            if (!isset(DiscountRule::actionOptions()[$type])) continue;
            $params = (array) ($request->input('act_params', [])[$i] ?? []);
            $entry = ['type' => $type];

            foreach ($params as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $entry[$key] = is_numeric($value) ? (int) $value : $value;
                }
            }

            $result[] = $entry;
        }

        return $result;
    }
}

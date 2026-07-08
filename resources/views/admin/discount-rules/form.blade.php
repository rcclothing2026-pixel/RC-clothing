@inject('condOpts', 'App\Models\DiscountRule')
@php
    $condOptions = $condOpts::conditionOptions();
    $actOptions = $condOpts::actionOptions();
@endphp
@extends('admin.layout')

@section('title', $rule->exists ? 'ویرایش قانون تخفیف' : 'قانون تخفیف جدید')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-brand-900">{{ $rule->exists ? 'ویرایش قانون تخفیف' : 'قانون تخفیف جدید' }}</h1>
    <a href="{{ route('admin.discount-rules.index') }}" class="text-sm text-brand-500 hover:underline">→ بازگشت</a>
</div>

<form method="POST" action="{{ $rule->exists ? route('admin.discount-rules.update', $rule) : route('admin.discount-rules.store') }}"
      x-data="ruleBuilder(@js($rule->conditions), @js($rule->actions))"
      class="space-y-6">
    @csrf @if($rule->exists) @method('PATCH') @endif

    <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">نام قانون</label>
                <input name="name" value="{{ old('name', $rule->name) }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" required maxlength="120">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">اولویت</label>
                <input type="number" name="priority" value="{{ old('priority', $rule->priority) }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" min="-999" max="999">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">وضعیت</label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked($rule->exists ? $rule->is_active : true) class="rounded">
                    فعال
                </label>
            </div>
        </div>
        <div class="mt-3">
            <label class="mb-1 block text-xs font-medium text-brand-500">توضیحات (داخلی)</label>
            <input name="description" value="{{ old('description', $rule->description) }}"
                   class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" maxlength="500">
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Conditions --}}
        <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-brand-900">شرایط <span class="text-xs text-brand-400 font-normal">(همه باید درست باشند)</span></h2>
                <button type="button" @click="addCondition"
                        class="rounded-lg bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-200">+ شرط</button>
            </div>

            <div class="space-y-3">
                <template x-for="(cond, i) in conditions" :key="i">
                    <div class="rounded-lg border border-brand-200 p-3 relative">
                        <button type="button" @click="conditions.splice(i, 1)"
                                class="absolute top-2 left-2 text-brand-300 hover:text-red-500 text-xs">✕</button>

                        <select :name="`cond_type[${i}]`" x-model="cond.type"
                                class="w-full rounded-lg border border-brand-100 px-3 py-1.5 text-sm mb-2" required>
                            <option value="">انتخاب شرط...</option>
                            @foreach($condOptions as $key => $opt)
                            <option value="{{ $key }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>

                        <template x-if="cond.type && !noParamsCond(cond.type)">
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <template x-for="(def, pkey) in getParams(cond.type)" :key="pkey">
                                    <div>
                                        <label class="block text-brand-400 mb-0.5" x-text="def.label"></label>

                                        <template x-if="def.type === 'select'">
                                            <select :name="`cond_params[${i}][${pkey}]`" x-model="cond.params[pkey]"
                                                    class="w-full rounded-lg border border-brand-100 px-2 py-1.5">
                                                <option value="">...</option>
                                                <template x-for="(optLabel, optVal) in (def.options || {})" :key="optVal">
                                                    <option :value="optVal" x-text="optLabel"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <template x-if="def.type === 'array'">
                                            <div>
                                                <div class="flex gap-1 mb-1">
                                                    <input type="text" x-model="def._input"
                                                           placeholder="افزودن..."
                                                           class="flex-1 rounded-lg border border-brand-100 px-2 py-1.5 text-xs"
                                                           @keydown.enter.prevent="addToArray(cond, pkey, def._input)">
                                                    <button type="button" @click="addToArray(cond, pkey, def._input)"
                                                            class="rounded bg-brand-100 px-2 text-brand-600 text-xs">+</button>
                                                </div>
                                                <div class="flex flex-wrap gap-1">
                                                    <template x-for="(val, vi) in (cond.params[pkey] || [])" :key="vi">
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] text-brand-600">
                                                            <span x-text="val"></span>
                                                            <button type="button" @click="removeFromArray(cond, pkey, vi)" class="text-brand-400 hover:text-red-500">✕</button>
                                                            <input type="hidden" :name="`cond_params[${i}][${pkey}][]`" :value="val">
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>

                                        <template x-if="def.type !== 'select' && def.type !== 'array'">
                                            <input :type="def.type === 'integer' ? 'number' : 'text'"
                                                   :name="`cond_params[${i}][${pkey}]`"
                                                   x-model="cond.params[pkey]"
                                                   class="w-full rounded-lg border border-brand-100 px-2 py-1.5"
                                                   :min="def.type === 'integer' ? 0 : undefined">
                                        </template>
                                    </div>
                                </template>

                                {{-- product multi-select from db --}}
                                <template x-if="['item_products', 'item_count_from_products', 'item_total_from_products'].includes(cond.type)">
                                    <div class="col-span-2">
                                        <label class="block text-brand-400 mb-0.5">محصولات</label>
                                        <select :name="`cond_params[${i}][product_ids][]`" x-model="cond.params.product_ids" multiple
                                                class="w-full rounded-lg border border-brand-100 px-2 py-1.5 text-xs h-24">
                                            @foreach($products as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                {{-- category multi-select from db --}}
                                <template x-if="['item_categories', 'item_count_from_categories', 'item_total_from_categories'].includes(cond.type)">
                                    <div class="col-span-2">
                                        <label class="block text-brand-400 mb-0.5">دسته‌بندی</label>
                                        <select :name="`cond_params[${i}][category_ids][]`" x-model="cond.params.category_ids" multiple
                                                class="w-full rounded-lg border border-brand-100 px-2 py-1.5 text-xs h-20">
                                            @foreach($categories as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                {{-- collection multi-select from db --}}
                                <template x-if="['item_collections', 'item_count_from_collections', 'item_total_from_collections'].includes(cond.type)">
                                    <div class="col-span-2">
                                        <label class="block text-brand-400 mb-0.5">مجموعه‌ها</label>
                                        <select :name="`cond_params[${i}][collection_ids][]`" x-model="cond.params.collection_ids" multiple
                                                class="w-full rounded-lg border border-brand-100 px-2 py-1.5 text-xs h-20">
                                            @foreach($collections as $col)
                                            <option value="{{ $col->id }}">{{ $col->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                {{-- payment method multi-select --}}
                                <template x-if="cond.type === 'order_payment_method'">
                                    <div class="col-span-2">
                                        <label class="block text-brand-400 mb-0.5">روش‌های پرداخت</label>
                                        <select :name="`cond_params[${i}][method_keys][]`" x-model="cond.params.method_keys" multiple
                                                class="w-full rounded-lg border border-brand-100 px-2 py-1.5 text-xs h-20">
                                            @foreach($paymentMethods as $pm)
                                            <option value="{{ $pm->key }}">{{ $pm->label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>

                                {{-- shipping method multi-select --}}
                                <template x-if="cond.type === 'order_shipping_method'">
                                    <div class="col-span-2">
                                        <label class="block text-brand-400 mb-0.5">روش‌های ارسال</label>
                                        <select :name="`cond_params[${i}][method_ids][]`" x-model="cond.params.method_ids" multiple
                                                class="w-full rounded-lg border border-brand-100 px-2 py-1.5 text-xs h-20">
                                            @foreach($shippingMethods as $sm)
                                            <option value="{{ $sm->id }}">{{ $sm->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <template x-if="!conditions.length">
                    <p class="text-center text-sm text-brand-400 py-4">شرطی اضافه نشده است. بدون شرط، قانون همیشه اعمال می‌شود.</p>
                </template>
            </div>
        </section>

        {{-- Actions --}}
        <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-brand-900">عملیات</h2>
                <button type="button" @click="addAction"
                        class="rounded-lg bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-200">+ عملیات</button>
            </div>

            <div class="space-y-3">
                <template x-for="(act, i) in actions" :key="i">
                    <div class="rounded-lg border border-brand-200 p-3 relative">
                        <button type="button" @click="actions.splice(i, 1)"
                                class="absolute top-2 left-2 text-brand-300 hover:text-red-500 text-xs">✕</button>

                        <select :name="`act_type[${i}]`" x-model="act.type"
                                class="w-full rounded-lg border border-brand-100 px-3 py-1.5 text-sm mb-2" required>
                            <option value="">انتخاب عملیات...</option>
                            @foreach($actOptions as $key => $opt)
                            <option value="{{ $key }}">{{ $opt['label'] }}</option>
                            @endforeach
                        </select>

                        <template x-if="act.type && act.type !== 'free_shipping'">
                            <div class="space-y-2 text-xs">
                                <template x-for="(def, pkey) in getActParams(act.type)" :key="pkey">
                                    <div>
                                        <label class="block text-brand-400 mb-0.5" x-text="def.label"></label>

                                        <template x-if="def.type === 'select'">
                                            <select :name="`act_params[${i}][${pkey}]`" x-model="act.params[pkey]"
                                                    class="w-full rounded-lg border border-brand-100 px-2 py-1.5">
                                                <option value="">...</option>
                                                <template x-for="(optLabel, optVal) in (def.options || {})" :key="optVal">
                                                    <option :value="optVal" x-text="optLabel"></option>
                                                </template>
                                            </select>
                                        </template>

                                        <template x-if="def.type !== 'select'">
                                            <input :type="def.type === 'integer' ? 'number' : 'text'"
                                                   :name="`act_params[${i}][${pkey}]`"
                                                   x-model="act.params[pkey]"
                                                   class="w-full rounded-lg border border-brand-100 px-2 py-1.5"
                                                   :min="def.type === 'integer' ? 0 : undefined"
                                                   :max="def.type === 'integer' && pkey === 'value' && ['cart_discount_percent','item_discount_percent','item_surcharge_percent','cart_surcharge_percent'].includes(act.type) ? 100 : undefined">
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="act.type === 'free_shipping'">
                            <p class="text-xs text-green-600">ارسال سفارش رایگان خواهد شد.</p>
                        </template>
                    </div>
                </template>

                <template x-if="!actions.length">
                    <p class="text-center text-sm text-brand-400 py-4">عملیاتی اضافه نشده است.</p>
                </template>
            </div>
        </section>
    </div>

    <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">حالت اعمال شرایط</label>
                <select name="apply_mode" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="all" @selected(old('apply_mode', $rule->apply_mode) === 'all')>همه شرایط (AND)</option>
                    <option value="any" @selected(old('apply_mode', $rule->apply_mode) === 'any')>حداقل یکی (OR)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">حالت انباشت عملیات</label>
                <select name="stack_mode" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <option value="best" @selected(old('stack_mode', $rule->stack_mode) === 'best')>فقط بهترین</option>
                    <option value="all" @selected(old('stack_mode', $rule->stack_mode) === 'all')>همه عملیات</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">حداکثر استفاده (کل)</label>
                <input type="number" name="max_uses" value="{{ old('max_uses', $rule->max_uses) }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" min="1" placeholder="بدون محدودیت">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">مصرف هر کاربر</label>
                <input type="number" name="max_uses_per_user" value="{{ old('max_uses_per_user', $rule->max_uses_per_user) }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" min="1" placeholder="بدون محدودیت">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">شروع اعتبار</label>
                <input name="starts_at" data-jdp value="{{ old('starts_at', $rule->starts_at ? \App\Support\Jalali::format($rule->starts_at) : '') }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm text-center fa-num" autocomplete="off">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-brand-500">پایان اعتبار</label>
                <input name="expires_at" data-jdp value="{{ old('expires_at', $rule->expires_at ? \App\Support\Jalali::format($rule->expires_at) : '') }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm text-center fa-num" autocomplete="off">
            </div>
        </div>
    </section>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.discount-rules.index') }}" class="rounded-lg px-5 py-2 text-sm text-brand-500 hover:bg-brand-50">انصراف</a>
        <button class="rounded-lg bg-brand-900 px-6 py-2 text-sm font-semibold text-white">
            {{ $rule->exists ? 'به‌روزرسانی' : 'ذخیره قانون' }}
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
const noParamConditions = ['coupon_single_use','coupon_single_use_per_user','has_featured','has_sale_item','has_discounted_item','has_full_price_item'];

function ruleBuilder(existingConditions, existingActions) {
    const paramDefs = @json($condOptions);
    const actParamDefs = @json($actOptions);

    function toList(raw) {
        // Tolerate every shape a rule may have been stored in:
        //  - flat  {type, min, max, value, ...}  ← what the engine + this form write
        //  - nested {type, params:{...}}          ← what the promotion-merge migration wrote
        //  - a JSON string (double-encoded column) ← parse it once
        let arr = raw;
        if (typeof arr === 'string') {
            try { arr = JSON.parse(arr); } catch (e) { arr = []; }
        }
        return (Array.isArray(arr) ? arr : []).map(c => {
            const { type, params, ...rest } = c || {};
            const flat = (params && typeof params === 'object' && !Array.isArray(params))
                ? { ...rest, ...params }
                : rest;
            return { type: type || '', params: flat };
        });
    }

    function normalizeType(typeDef) {
        if (typeof typeDef === 'string') return { type: typeDef, options: null };
        if (typeDef && typeof typeDef === 'object') return { type: typeDef.type || 'string', options: typeDef.options || null };
        return { type: 'string', options: null };
    }

    return {
        conditions: toList(existingConditions),
        actions: toList(existingActions),

        noParamsCond(type) {
            return noParamConditions.includes(type);
        },

        addCondition() {
            this.conditions.push({ type: '', params: {} });
        },

        addAction() {
            this.actions.push({ type: '', params: {} });
        },

        getParams(type) {
            const def = paramDefs[type];
            if (!def || !def.params) return {};
            const skip = {
                order_payment_method: ['method_keys'],
                order_shipping_method: ['method_ids'],
                item_products: ['product_ids'],
                item_count_from_products: ['product_ids'],
                item_total_from_products: ['product_ids'],
                item_categories: ['category_ids'],
                item_count_from_categories: ['category_ids'],
                item_total_from_categories: ['category_ids'],
                item_collections: ['collection_ids'],
                item_count_from_collections: ['collection_ids'],
                item_total_from_collections: ['collection_ids'],
            };
            const skipKeys = skip[type] || [];
            const result = {};
            Object.entries(def.params).forEach(([key, typeDef]) => {
                if (skipKeys.includes(key)) return;
                const { type: t, options } = normalizeType(typeDef);
                result[key] = { type: t, options, label: this.paramLabel(type, key), _input: '' };
            });
            return result;
        },

        getActParams(type) {
            const def = actParamDefs[type];
            if (!def || !def.params) return {};
            const result = {};
            Object.entries(def.params).forEach(([key, typeDef]) => {
                const { type: t, options } = normalizeType(typeDef);
                result[key] = { type: t, options, label: this.paramLabel(type, key), _input: '' };
            });
            return result;
        },

        paramLabel(type, key) {
            const labels = {
                min: 'حداقل', max: 'حداکثر', value: 'مقدار', code: 'کد کوپن',
                product_ids: 'محصولات', brands: 'برندها', category_ids: 'دسته‌بندی‌ها', collection_ids: 'مجموعه‌ها',
                phones: 'شماره موبایل‌ها', cities: 'شهرها', method_keys: 'روش پرداخت',
                method_ids: 'روش ارسال', group_ids: 'گروه کاربری', message: 'متن پیام',
            };
            return labels[key] || key;
        },

        addToArray(cond, key, val) {
            if (!val || !val.trim()) return;
            if (!cond.params[key]) cond.params[key] = [];
            cond.params[key].push(val.trim());
            const def = paramDefs[cond.type];
            if (def && def.params && def.params[key]) def.params[key]._input = '';
        },

        removeFromArray(cond, key, idx) {
            if (cond.params[key]) cond.params[key].splice(idx, 1);
        },
    };
}
</script>
@endpush

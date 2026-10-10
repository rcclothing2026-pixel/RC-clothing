@extends('admin.layout')

@section('title', $coupon->exists ? 'ویرایش کد تخفیف' : 'کد تخفیف جدید')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $coupon->exists ? 'ویرایش کد تخفیف' : 'کد تخفیف جدید' }}</h1>
        <a href="{{ route('admin.coupons.index') }}" class="text-sm text-brand-500 hover:underline">بازگشت</a>
    </div>

    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}"
          class="max-w-3xl space-y-6 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm text-brand-600">کد *</label>
                <input name="code" value="{{ old('code', $coupon->code) }}" required dir="ltr" placeholder="WELCOME10" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm uppercase">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">توضیح (اختیاری)</label>
                <input name="description" value="{{ old('description', $coupon->description) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">نوع *</label>
                <select name="type" id="cp-type" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    @foreach (['percent' => 'درصدی', 'fixed' => 'مبلغ ثابت (تومان)', 'free_shipping' => 'ارسال رایگان'] as $k => $label)
                        <option value="{{ $k }}" @selected(old('type', $coupon->type) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div data-cp-value>
                <label class="mb-1 block text-sm text-brand-600">مقدار (درصد یا تومان)</label>
                <input name="value" type="number" min="0" value="{{ old('value', $coupon->value) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">شرایط استفاده</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm text-brand-600">حداقل مبلغ سفارش (تومان)</label>
                <input name="min_subtotal" type="number" min="0" value="{{ old('min_subtotal', $coupon->min_subtotal) }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">حداکثر مبلغ سفارش (تومان)</label>
                <input name="max_subtotal" type="number" min="0" value="{{ old('max_subtotal', $coupon->max_subtotal) }}" placeholder="بدون محدودیت" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">سقف تخفیف (برای درصدی)</label>
                <input name="max_discount" type="number" min="0" value="{{ old('max_discount', $coupon->max_discount) }}" placeholder="بدون سقف" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">سقف کل استفاده</label>
                <input name="usage_limit" type="number" min="1" value="{{ old('usage_limit', $coupon->usage_limit) }}" placeholder="نامحدود" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">سقف استفاده هر کاربر</label>
                <input name="per_user_limit" type="number" min="1" value="{{ old('per_user_limit', $coupon->per_user_limit) }}" placeholder="نامحدود" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
            </div>
            <label class="mt-7 inline-flex items-center gap-2 text-sm text-brand-700">
                <input type="checkbox" name="first_order_only" value="1" @checked(old('first_order_only', $coupon->first_order_only)) class="rounded"> فقط برای اولین خرید
            </label>
        </div>

        <h2 class="border-t border-brand-100 pt-4 text-sm font-bold text-brand-800">بازه زمانی (تقویم شمسی)</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm text-brand-600">تاریخ شروع</label>
                <input name="starts_at" data-jdp value="{{ old('starts_at', $coupon->starts_at ? \App\Support\Jalali::format($coupon->starts_at) : '') }}" dir="ltr" placeholder="۱۴۰۳/۰۱/۰۱" autocomplete="off" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm text-center fa-num">
            </div>
            <div>
                <label class="mb-1 block text-sm text-brand-600">تاریخ پایان</label>
                <input name="expires_at" data-jdp value="{{ old('expires_at', $coupon->expires_at ? \App\Support\Jalali::format($coupon->expires_at) : '') }}" dir="ltr" placeholder="۱۴۰۳/۱۲/۲۹" autocomplete="off" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm text-center fa-num">
            </div>
        </div>

        <label class="flex items-center gap-2 border-t border-brand-100 pt-4 text-sm text-brand-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active)) class="rounded"> فعال
        </label>

        <div class="flex gap-3 pt-2">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره</button>
            <a href="{{ route('admin.coupons.index') }}" class="rounded-lg px-5 py-2 text-sm text-brand-500 hover:bg-brand-50">انصراف</a>
        </div>
    </form>

    <script>
        // Hide the value field for free-shipping coupons.
        (function () {
            const type = document.getElementById('cp-type');
            const valueWrap = document.querySelector('[data-cp-value]');
            function sync() { if (valueWrap) valueWrap.style.display = type.value === 'free_shipping' ? 'none' : ''; }
            type?.addEventListener('change', sync); sync();
        })();
    </script>
@endsection

@extends('admin.layout')

@section('title', 'پنل پیامک')

@php($val = fn ($k, $d = '') => old(str_replace('.', '_', $k), $s[$k] ?? $d))

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">پنل پیامک</h1>
        @include('admin.sms._tabs', ['active' => 'settings'])
    </div>

    <form method="POST" action="{{ route('admin.sms.settings.update') }}" class="max-w-3xl space-y-6 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf @method('PATCH')

        {{-- Melipayamak (the only SMS system) --}}
        <div class="rounded-lg bg-accent-50 p-5 ring-1 ring-accent-100">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-accent-800">ملی‌پیامک</h2>
                    <p class="mt-1 text-xs leading-6 text-accent-700">کلید API کنسول ملی‌پیامک و bodyId هر پیامک را اینجا وارد کنید. کد ورود (OTP) توسط سایت ساخته و از طریق پترن (bodyId) ارسال می‌شود.</p>
                </div>
                <label class="flex shrink-0 items-center gap-2 text-sm font-semibold text-accent-800">
                    <input type="checkbox" name="sms_enabled" value="1" @checked($val('sms.enabled', true)) class="rounded">
                    فعال
                </label>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm text-accent-800">کلید API @if($s['sms.api_key'] ?? null)<span class="text-green-600">(ذخیره شده — برای تغییر مقدار جدید وارد کنید)</span>@endif</label>
                    <input name="sms_api_key" value="" type="password" dir="ltr" placeholder="مثلاً e070f58892e346a19254f48f58ddc17b" class="w-full rounded-lg border border-accent-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm text-accent-800">bodyId کد ورود (OTP)</label>
                    <input name="sms_otp_body_id" value="{{ $val('sms.otp_body_id') }}" dir="ltr" placeholder="مثلاً 524" class="w-full rounded-lg border border-accent-200 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-accent-600">پترن تأییدشده با متغیرها به ترتیب: «نام؛ کد». اگر نام کاربر ثبت نشده باشد «کاربر» فرستاده می‌شود و کد به‌جای متغیر دوم قرار می‌گیرد.</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm text-accent-800">شماره خط / فرستنده (اختیاری)</label>
                    <input name="sms_sender" value="{{ $val('sms.sender') }}" dir="ltr" class="w-full rounded-lg border border-accent-200 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-accent-600">فقط برای پیامک متنی آزاد. برای پترن لازم نیست.</p>
                </div>
            </div>
        </div>

        {{-- Order notification --}}
        <div class="border-t border-brand-100 pt-5">
            <h2 class="mb-3 text-sm font-bold text-brand-800">اعلان سفارش (پترن / bodyId)</h2>
            <p class="mb-3 text-xs text-brand-400">هنگام پرداخت موفق، یک پیامک برای مشتری ارسال می‌شود. متغیرهای پترن به ترتیب: «نام؛ نام فروشگاه؛ شماره سفارش».</p>
            <label class="flex items-center gap-2 text-sm text-brand-700">
                <input type="checkbox" name="sms_notify_paid" value="1" @checked($val('sms.notify_paid')) class="rounded">
                ارسال پیامک هنگام پرداخت سفارش
            </label>
            <div class="mt-3 max-w-sm">
                <label class="mb-1 block text-sm text-brand-600">bodyId پیامک «پرداخت شد» (مشتری)</label>
                <input name="sms_order_body_id" value="{{ $val('sms.order_body_id') }}" dir="ltr" placeholder="مثلاً 530" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div class="mt-3 max-w-sm">
                <label class="mb-1 block text-sm text-brand-600">شماره(های) مدیر (با ویرگول) — اختیاری</label>
                <input name="sms_admin_phone" value="{{ $val('sms.admin_phone') }}" dir="ltr" placeholder="0912...,0935..." class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-brand-400">اعلان مدیر سفارش‌ها از طریق ربات تلگرام ارسال می‌شود.</p>
            </div>
        </div>

        <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره تنظیمات</button>
    </form>

    {{-- Send-test card (separate form) — verify the SMS setup actually works --}}
    <div class="mt-6 max-w-3xl rounded-card bg-white p-6 ring-1 ring-brand-100">
        <h2 class="mb-1 text-sm font-bold text-brand-800">ارسال آزمایشی (تست)</h2>
        <p class="mb-4 text-xs leading-6 text-brand-400">یک پیامک آزمایشی به شمارهٔ خودتان بفرستید تا مطمئن شوید تنظیمات کار می‌کند. نتیجه (موفق یا خطای ملی‌پیامک) بالای همین صفحه نمایش داده می‌شود. ابتدا تنظیمات بالا را «ذخیره» کنید.</p>
        <form method="POST" action="{{ route('admin.sms.test') }}" class="space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm text-brand-600">شماره موبایل</label>
                    <input name="test_phone" dir="ltr" placeholder="09120000000" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm text-brand-600">نوع تست</label>
                    <select name="test_type" id="test-type" class="w-full rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
                        <option value="otp">کد ورود (OTP)</option>
                        <option value="pattern">پترن سفارش (bodyId)</option>
                    </select>
                </div>
            </div>
            <div id="test-pattern-fields" style="display:none" class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm text-brand-600">bodyId پترن</label>
                    <input name="test_body_id" dir="ltr" placeholder="مثلاً 530" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm text-brand-600">آرگومان‌ها (با ، جدا کنید)</label>
                    <input name="test_args" placeholder="مثلاً علی، CH-1، ۲۵۰٬۰۰۰" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                </div>
            </div>
            <button class="rounded-lg bg-accent-600 px-5 py-2 text-sm font-semibold text-white hover:bg-accent-700">📤 ارسال تست</button>
        </form>
        <script>
            document.getElementById('test-type').addEventListener('change', function () {
                document.getElementById('test-pattern-fields').style.display = this.value === 'pattern' ? 'grid' : 'none';
            });
        </script>
    </div>
@endsection

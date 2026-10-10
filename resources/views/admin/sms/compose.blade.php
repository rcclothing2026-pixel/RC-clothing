@extends('admin.layout')

@section('title', 'ارسال پیامک')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">پنل پیامک</h1>
        @include('admin.sms._tabs', ['active' => 'compose'])
    </div>

    <form method="POST" action="{{ route('admin.sms.send') }}" class="max-w-2xl space-y-5 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">گیرندگان</label>
            <select name="audience" id="sms-audience" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <option value="buyers">خریداران (کاربران دارای سفارش پرداخت‌شده)</option>
                <option value="all">همه کاربران</option>
                <option value="single">یک شماره مشخص</option>
            </select>
        </div>
        <div id="sms-phone-wrap" style="display:none">
            <label class="mb-1 block text-sm font-medium text-brand-700">شماره موبایل</label>
            <input name="phone" value="{{ old('phone') }}" dir="ltr" placeholder="09120000000" class="w-full max-w-xs rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-brand-700">متن پیام</label>
            <textarea name="message" rows="4" maxlength="480" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ old('message') }}</textarea>
            <p class="mt-1 text-xs text-brand-400">ارسال متن ساده نیازمند خط فعال نزد سرویس‌دهنده است. برای پیام‌های تراکنشی از پترن استفاده کنید.</p>
        </div>
        <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white" onclick="return confirm('ارسال پیامک به گروه انتخاب‌شده؟')">ارسال</button>
    </form>

    <script>
        document.getElementById('sms-audience').addEventListener('change', function () {
            document.getElementById('sms-phone-wrap').style.display = this.value === 'single' ? '' : 'none';
        });
    </script>
@endsection

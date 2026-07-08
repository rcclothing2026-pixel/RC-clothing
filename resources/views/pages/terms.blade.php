@extends('layouts.app')

@section('title', 'قوانین و مقررات | چیاکو')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold text-brand-900">قوانین و مقررات</h1>
        <div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
            <p>با ثبت سفارش در چیاکو، شما قوانین زیر را می‌پذیرید. این قوانین برای شفافیت و حفظ حقوق طرفین تنظیم شده است.</p>
            <h2 class="pt-2 font-bold text-brand-900">۱. ثبت سفارش</h2>
            <p>قیمت‌ها به تومان و شامل ارزش افزوده هستند. پس از پرداخت موفق، سفارش ثبت و برای پردازش ارسال می‌شود.</p>
            <h2 class="pt-2 font-bold text-brand-900">۲. قیمت و موجودی</h2>
            <p>قیمت‌ها و موجودی ممکن است تغییر کنند؛ ملاک، اطلاعات لحظه‌ی ثبت سفارش است. در صورت اتمام موجودی پس از پرداخت، مبلغ بازگردانده می‌شود.</p>
            <h2 class="pt-2 font-bold text-brand-900">۳. ارسال</h2>
            <p>زمان و هزینه‌ی ارسال بر اساس روش انتخابی شما محاسبه می‌شود. جزئیات در صفحه‌ی شرایط ارسال و بازگشت آمده است.</p>
            <h2 class="pt-2 font-bold text-brand-900">۴. حریم خصوصی</h2>
            <p>اطلاعات شما طبق سیاست <a href="{{ route('page', 'privacy') }}" class="text-accent-600 hover:underline">حریم خصوصی</a> محافظت می‌شود.</p>
        </div>
    </div>
@endsection

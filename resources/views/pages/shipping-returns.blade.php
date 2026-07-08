@extends('layouts.app')

@section('title', 'شرایط ارسال و بازگشت | چیاکو')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold text-brand-900">شرایط ارسال و بازگشت کالا</h1>
        <div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
            <h2 class="pt-2 font-bold text-brand-900">ارسال</h2>
            <p>سفارش‌ها پس از تأیید پرداخت آماده و ارسال می‌شوند. روش و هزینه‌ی ارسال در مرحله‌ی پرداخت قابل انتخاب است و برای سفارش‌های بالای سقف مشخص، ارسال رایگان خواهد بود.</p>
            <h2 class="pt-2 font-bold text-brand-900">بازگشت و تعویض</h2>
            <p>تا ۷ روز پس از دریافت، در صورت استفاده‌نشدن و سالم بودن کالا و برچسب‌ها، امکان بازگشت یا تعویض وجود دارد. برای شروع فرایند، از صفحه‌ی <a href="{{ route('contact') }}" class="text-accent-600 hover:underline">تماس با ما</a> اطلاع دهید.</p>
            <h2 class="pt-2 font-bold text-brand-900">شرایط بازگشت</h2>
            <ul class="list-inside list-disc space-y-1">
                <li>کالا باید استفاده‌نشده و با بسته‌بندی و برچسب اصلی باشد.</li>
                <li>هزینه‌ی بازگشت در صورت ایراد از سمت ما به‌عهده‌ی فروشگاه است.</li>
                <li>مبلغ پس از بررسی کالا حداکثر ظرف ۷۲ ساعت کاری بازگردانده می‌شود.</li>
            </ul>
        </div>
    </div>
@endsection

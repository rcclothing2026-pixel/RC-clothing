@extends('layouts.app')

@section('title', 'سوالات متداول | چیاکو')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold text-brand-900">سوالات متداول</h1>
        <div class="mt-8 space-y-4">
            @foreach ([
                ['چطور سفارش ثبت کنم؟', 'محصول مورد نظر را به سبد خرید اضافه کنید، سایز و رنگ را انتخاب کنید و در مرحله‌ی پرداخت آدرس و روش ارسال را مشخص کنید.'],
                ['پرداخت چگونه انجام می‌شود؟', 'پرداخت به‌صورت آنلاین و امن از طریق درگاه‌های معتبر بانکی انجام می‌شود.'],
                ['سفارش چند روزه به دستم می‌رسد؟', 'بسته به روش ارسال انتخابی، معمولاً بین ۱ تا ۴ روز کاری.'],
                ['اگر سایز مناسب نبود چه کنم؟', 'تا ۷ روز امکان بازگشت یا تعویض کالای استفاده‌نشده وجود دارد. جزئیات در صفحه‌ی شرایط ارسال و بازگشت.'],
                ['موجودی سایت دقیق است؟', 'بله، موجودی به‌صورت زنده با انبار هماهنگ می‌شود؛ سایزهای ناموجود غیرفعال نمایش داده می‌شوند.'],
            ] as [$q, $a])
                <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
                    <summary class="cursor-pointer text-sm font-semibold text-brand-800">{{ $q }}</summary>
                    <p class="mt-3 text-sm leading-7 text-brand-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
@endsection

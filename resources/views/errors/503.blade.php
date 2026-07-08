@extends('layouts.app')

@section('title', 'به‌روزرسانی | چیاکو')

@section('content')
    <div class="mx-auto flex max-w-md flex-col items-center px-4 py-24 text-center">
        <x-brand-pattern class="mx-auto mb-4 h-20 w-20 text-brand-200" />
        <h1 class="text-xl font-bold text-brand-900">به‌زودی برمی‌گردیم</h1>
        <p class="mt-2 text-sm text-brand-500">سایت در حال به‌روزرسانی است. لطفاً کمی بعد دوباره سر بزنید.</p>
        <div class="mt-6 flex gap-3">
            <a href="https://instagram.com/chiaco" target="_blank" class="rounded-full border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">اینستاگرام چیاکو</a>
            <a href="https://t.me/chiaco" target="_blank" class="rounded-full border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">کانال تلگرام</a>
        </div>
    </div>
@endsection

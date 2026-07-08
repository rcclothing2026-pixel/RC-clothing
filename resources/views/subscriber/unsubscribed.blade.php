@extends('layouts.app')

@section('title', 'لغو عضویت خبرنامه')

@section('content')
    <div class="mx-auto max-w-lg px-4 py-20 text-center">
        <div class="rounded-card bg-white p-10 ring-1 ring-brand-100">
            <div class="text-5xl mb-4">😢</div>
            <h1 class="text-xl font-bold text-brand-900">لغو عضویت انجام شد</h1>
            <p class="mt-3 text-sm text-brand-500">شما با موفقیت از خبرنامه چیاکو خارج شدید. اگر نظر یا پیشنهادی دارید، خوشحال می‌شویم بشنویم.</p>
            <a href="{{ route('shop.index') }}" class="mt-6 inline-block rounded-lg bg-brand-900 px-6 py-2 text-sm font-semibold text-white">بازگشت به فروشگاه</a>
        </div>
    </div>
@endsection

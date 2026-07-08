@extends('layouts.app')

@section('title', 'صفحه پیدا نشد | چیاکو')

@section('content')
    <div class="mx-auto flex max-w-md flex-col items-center px-4 py-24 text-center">
        <x-brand-pattern class="mx-auto mb-4 h-20 w-20 text-brand-100" />
        <p class="text-6xl font-black text-brand-200 fa-num">۴۰۴</p>
        <h1 class="mt-4 text-xl font-bold text-brand-900">صفحه‌ای که دنبالش بودید پیدا نشد</h1>
        <p class="mt-2 text-sm text-brand-500">ممکن است حذف شده باشد یا آدرس را اشتباه وارد کرده باشید.</p>

        <form action="{{ route('shop.index') }}" method="GET" class="mt-6 w-full">
            <div class="relative">
                <input type="search" name="q" placeholder="جستجوی محصول در فروشگاه..." dir="rtl"
                       class="w-full rounded-xl border border-brand-200 px-4 py-2.5 text-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100" autofocus>
                <button class="absolute left-1 top-1 rounded-lg bg-brand-900 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800">جستجو</button>
            </div>
        </form>

        <div class="mt-6 flex gap-3">
            <a href="{{ route('home') }}" class="rounded-full bg-brand-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">صفحه اصلی</a>
            <a href="{{ route('shop.index') }}" class="rounded-full border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">فروشگاه</a>
        </div>
    </div>
@endsection

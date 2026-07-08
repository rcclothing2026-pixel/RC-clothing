@extends('layouts.app')

@section('title', 'خطای سرور | چیاکو')

@section('content')
    <div class="mx-auto flex max-w-md flex-col items-center px-4 py-24 text-center">
        <x-brand-pattern class="mx-auto mb-4 h-20 w-20 text-brand-100" />
        <p class="text-6xl font-black text-brand-200 fa-num">۵۰۰</p>
        <h1 class="mt-4 text-xl font-bold text-brand-900">مشکلی پیش آمد</h1>
        <p class="mt-2 text-sm text-brand-500">لطفاً چند لحظه دیگر دوباره تلاش کنید. اگر مشکل ادامه داشت با پشتیبانی تماس بگیرید.</p>
        <div class="mt-6 flex gap-3">
            <a href="{{ route('home') }}" class="rounded-full bg-brand-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">بازگشت به خانه</a>
            <a href="{{ route('page', 'contact') }}" class="rounded-full border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">تماس با پشتیبانی</a>
        </div>
    </div>
@endsection

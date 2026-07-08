@extends('layouts.app')

@section('title', __('Page Not Found').' | Racket Club')

@section('content')
    <div class="mx-auto flex max-w-md flex-col items-center px-4 py-24 text-center">
        <x-brand-pattern class="mx-auto mb-4 h-20 w-20 text-brand-100" />
        <p class="text-6xl font-black text-brand-200 fa-num">404</p>
        <h1 class="mt-4 text-xl font-bold uppercase tracking-wide text-brand-900">{{ __("WE COULDN'T FIND THAT PAGE") }}</h1>
        <p class="mt-2 text-sm text-brand-500">{{ __('It may have moved, or the address might be slightly off.') }}</p>

        <form action="{{ route('shop.index') }}" method="GET" class="mt-6 w-full">
            <div class="relative">
                <input type="search" name="q" placeholder="{{ __('Search the shop...') }}" dir="ltr"
                       class="w-full rounded-xl border border-brand-200 px-4 py-2.5 text-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100" autofocus>
                <button class="absolute right-1 top-1 rounded-lg bg-brand-900 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800">{{ __('Search') }}</button>
            </div>
        </form>

        <div class="mt-6 flex gap-3">
            <a href="{{ route('home') }}" class="rounded-full bg-brand-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">{{ __('Home') }}</a>
            <a href="{{ route('shop.index') }}" class="rounded-full border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">{{ __('Shop') }}</a>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'بسته‌ها و ست‌ها | Racket Club')
@section('meta_description', 'بسته‌ها و ست‌های راکت کلاب؛ چند محصول با هم، ارزان‌تر از خرید تکی.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold text-brand-900 sm:text-4xl">بسته‌ها و ست‌ها</h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-brand-500">چند محصول با هم، ارزان‌تر از خرید تکی. تخفیف ست‌ها در سبد خرید خودکار حساب می‌شود.</p>

        @if ($bundles->isNotEmpty())
            <h2 class="mt-10 mb-5 text-xl font-bold text-brand-900">بسته‌ها</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ($bundles as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        @endif

        @if ($sets)
            <h2 class="mt-12 mb-5 text-xl font-bold text-brand-900">ست‌ها</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($sets as $set)
                    <a href="{{ $set['url'] }}" class="group flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-brand-100 transition hover:ring-brand-300">
                        <div class="h-24 w-20 shrink-0 overflow-hidden rounded-xl bg-brand-100">
                            @if ($img = \App\Support\Campaigns::setImage($set))
                                <img src="{{ $img }}" alt="{{ $set['name'] }}" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-brand-900 group-hover:text-accent-600">{{ $set['name'] }}</p>
                            <p class="mt-1 text-sm leading-6 text-brand-600">{{ $set['text'] }}</p>
                            @if ($set['starts'])
                                <span class="mt-2 inline-block rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-600 fa-num">{{ $set['starts'] }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($bundles->isEmpty() && ! $sets)
            <p class="mt-10 text-brand-500">فعلاً بسته یا ستی فعال نیست.</p>
        @endif
    </div>
@endsection

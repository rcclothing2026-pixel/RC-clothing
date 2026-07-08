@extends('layouts.app')

@section('title', __('Wishlist') . ' | Racket Club')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">{{ __('WISHLIST') }}</h1>
            @if ($products->isNotEmpty())
                <form method="POST" action="{{ route('wishlist.clear') }}" onsubmit="return confirm('{{ __('Remove all items from your wishlist?') }}')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-500 transition hover:bg-red-50">{{ __('Clear all') }}</button>
                </form>
            @endif
        </div>

        @if ($products->isEmpty())
            <x-empty-state
                icon="heart"
                title="{{ __('Your wishlist is empty') }}"
                caption="{{ __('Save the pieces you love and find them here whenever you\'re ready.') }}"
                :cta="['label' => __('Browse the Shop'), 'href' => route('shop.index')]" />
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <div class="relative group">
                        <x-product-card :product="$product" />
                        <div class="absolute left-2 top-2 flex flex-col gap-1.5 opacity-0 transition group-hover:opacity-100">
                            @if ($product->variants->isNotEmpty())
                                <form method="POST" action="{{ route('cart.add') }}">
                                    @csrf
                                    <input type="hidden" name="variant_id" value="{{ $product->variants->first()->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="rounded-full bg-white px-2.5 py-1.5 text-xs font-medium text-brand-700 shadow ring-1 ring-brand-100 transition hover:bg-brand-50" title="{{ __('Add to cart') }}">{{ __('+ Add to cart') }}</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('wishlist.toggle', $product) }}">
                                @csrf
                                <button class="rounded-full bg-white px-2.5 py-1.5 text-xs font-medium text-red-500 shadow ring-1 ring-brand-100 transition hover:bg-red-50 active:scale-95" title="{{ __('Remove from wishlist') }}">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

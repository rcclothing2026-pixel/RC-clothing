@extends('layouts.app')

@section('title', 'علاقه‌مندی‌ها | چیاکو')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-brand-900">علاقه‌مندی‌ها</h1>
            @if ($products->isNotEmpty())
                <form method="POST" action="{{ route('wishlist.clear') }}" onsubmit="return confirm('همه علاقه‌مندی‌ها حذف شود؟')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-500 transition hover:bg-red-50">حذف همه</button>
                </form>
            @endif
        </div>

        @if ($products->isEmpty())
            <x-empty-state
                icon="heart"
                title="فهرست علاقه‌مندی‌ها خالی است"
                caption="محصول‌هایی که می‌پسندید را با ❤ ذخیره کنید تا بعداً به‌راحتی پیداشان کنید."
                :cta="['label' => 'رفتن به فروشگاه', 'href' => route('shop.index')]" />
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
                                    <button class="rounded-full bg-white px-2.5 py-1.5 text-xs font-medium text-brand-700 shadow ring-1 ring-brand-100 transition hover:bg-brand-50" title="افزودن به سبد">+ سبد خرید</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('wishlist.toggle', $product) }}">
                                @csrf
                                <button class="rounded-full bg-white px-2.5 py-1.5 text-xs font-medium text-red-500 shadow ring-1 ring-brand-100 transition hover:bg-red-50 active:scale-95" title="حذف از علاقه‌مندی‌ها">✕ حذف</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

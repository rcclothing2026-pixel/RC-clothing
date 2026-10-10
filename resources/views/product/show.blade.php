@extends('layouts.app')

@section('title', $product->name.' | Racket Club')
@section('meta_description', $product->summary ?: \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 155) ?: $product->name.' — Racket Club')
@section('og_type', 'product')
@if ($product->primary_image_url)
    @section('og_image', url($product->primary_image_url))
@endif

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->name,
    'description' => $product->summary ?: $product->description,
    'image' => $product->primary_image_url ? url($product->primary_image_url) : null,
    'sku' => optional($product->variants->first())->sku,
    'offers' => [
        '@type' => 'Offer',
        'price' => $product->price,
        'priceCurrency' => 'IRR',
        'availability' => $product->variants->where('is_active', true)->where('stock_qty', '>', 0)->isNotEmpty()
            ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => route('product.show', $product),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
{{-- Breadcrumb structured data (mirrors the visible trail): Home › Shop › [Category] › Product --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_values(array_filter([
        ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home'), 'item' => route('home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => __('Shop'), 'item' => route('shop.index')],
        $product->category ? ['@type' => 'ListItem', 'position' => 3, 'name' => $product->category->name, 'item' => route('shop.index', ['category' => $product->category->slug])] : null,
        ['@type' => 'ListItem', 'position' => $product->category ? 4 : 3, 'name' => $product->name, 'item' => route('product.show', $product)],
    ])),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:py-10">
        {{-- Breadcrumb — editorial Jost eyebrow rhythm --}}
        <nav class="mb-8 flex items-center gap-2 font-display text-[11px] uppercase tracking-[0.18em] text-brand-400">
            <a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('Home') }}</a>
            <span>·</span>
            <a href="{{ route('shop.index') }}" class="hover:text-brand-700">{{ __('Shop') }}</a>
            @if ($product->category)
                <span>·</span>
                <a href="{{ route('shop.index', ['category' => $product->category->slug]) }}" class="hover:text-brand-700">{{ $product->category->name }}</a>
            @endif
        </nav>

        @php
            $variantData = $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'color' => $v->color,
                'size' => $v->size,
                // availableQty() == stock_qty for normal variants, derived child
                // stock for a bundle's placeholder, so JS treats both uniformly.
                'stock' => $v->inStock() ? $v->availableQty() : 0,
            ])->values();
        @endphp
        {{-- Editorial split: gallery takes ~58% on desktop so the photo
             carries the page; details column stays compact at ~42%. --}}
        <div class="grid gap-10 lg:grid-cols-[1.4fr_1fr] lg:gap-16" data-product data-variants='@json($variantData)'
             data-product-id="{{ $product->id }}"
             data-product-name="{{ $product->name }}"
             data-product-url="{{ route('product.show', $product) }}"
             data-product-image="{{ $product->primary_image_url ?? '' }}"
             data-product-price="{{ $product->variants->min('price') ?? 0 }}">
            {{-- Gallery --}}
            <div class="flex flex-col-reverse gap-4 sm:flex-row">
                @if ($product->images->count() > 1)
                    <div class="flex gap-3 overflow-x-auto pb-1 sm:flex-col sm:overflow-visible">
                        @foreach ($product->images as $image)
                            <button type="button" data-thumb="{{ $image->path }}" data-thumb-index="{{ $loop->index }}"
                                    class="h-16 w-16 shrink-0 overflow-hidden rounded-xl ring-1 ring-brand-200 transition hover:ring-brand-400 {{ $loop->first ? 'ring-2 ring-brand-800' : '' }}">
                                <img src="{{ $image->path }}" alt="{{ $image->alt }}" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
                <div class="flex-1">
                    {{-- Editorial frame: no ring, no rounded card — photo dominates. --}}
                    <div class="group relative aspect-[4/5] overflow-hidden bg-brand-50 cursor-zoom-in"
                         data-lightbox-trigger>
                        <img data-main-image src="{{ $product->primary_image_url ?? '/placeholder?w=900&h=1100&label='.urlencode($product->name) }}"
                             alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                        {{-- Hover zoom hint --}}
                        <span class="pointer-events-none absolute bottom-3 end-3 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-brand-800 shadow-md opacity-0 transition group-hover:opacity-100" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M8 11h6M11 8v6"/></svg>
                        </span>
                        <x-product-badge-overlay :product="$product" />
                    </div>
                </div>
            </div>

            {{-- Lightbox overlay (Alpine) --}}
            @php($lightboxImages = $product->images->count() > 0
                ? $product->images->map(fn ($i) => ['src' => $i->path, 'alt' => $i->alt ?: $product->name])->values()
                : collect([['src' => $product->primary_image_url ?? '', 'alt' => $product->name]]))
            <div x-data="productLightbox({{ $lightboxImages->toJson() }})"
                 x-on:open-lightbox.window="open($event.detail?.index ?? 0)"
                 x-on:keydown.escape.window="close()"
                 x-on:keydown.arrow-right.window="prev()"
                 x-on:keydown.arrow-left.window="next()"
                 x-cloak>
                <div x-show="visible" x-transition.opacity
                     @click.self="close()"
                     class="fixed inset-0 z-[70] flex items-center justify-center bg-brand-950/85 p-4 sm:p-8"
                     role="dialog" aria-modal="true">

                    {{-- Close --}}
                    <button type="button" @click="close()" aria-label="{{ __('Close') }}"
                            class="absolute top-4 end-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>

                    {{-- Prev / Next (RTL: prev = right arrow visually) --}}
                    <template x-if="images.length > 1">
                        <button type="button" @click.stop="prev()" aria-label="{{ __('Previous') }}"
                                class="absolute end-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:end-8">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </template>
                    <template x-if="images.length > 1">
                        <button type="button" @click.stop="next()" aria-label="{{ __('Next') }}"
                                class="absolute start-4 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:start-8">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                    </template>

                    {{-- Stage --}}
                    <div class="relative flex h-full max-h-[88vh] w-full max-w-5xl items-center justify-center"
                         @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">
                        <img :src="images[index]?.src" :alt="images[index]?.alt"
                             class="max-h-full max-w-full select-none rounded-xl object-contain shadow-2xl">
                    </div>

                    {{-- Index pill --}}
                    <template x-if="images.length > 1">
                        <div class="absolute bottom-5 left-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white fa-num" dir="ltr">
                            <span x-text="(index + 1) + ' / ' + images.length"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Details --}}
            <div>
                @php($pcats = $product->categories->isNotEmpty() ? $product->categories : collect([$product->category])->filter())
                @if ($pcats->isNotEmpty())
                    <p class="font-display text-[11px] uppercase tracking-[0.22em] text-brand-400">
                        @foreach ($pcats as $pc)<a href="{{ route('shop.index', ['category' => $pc->slug]) }}" class="hover:text-accent-600">{{ $pc->name }}</a>@if (! $loop->last)<span class="mx-1">·</span>@endif @endforeach
                    </p>
                @endif
                <h1 class="mt-3 text-3xl font-bold uppercase leading-tight tracking-tight text-brand-900 sm:text-4xl lg:text-5xl">{{ $product->name }}</h1>

                {{-- Share buttons --}}
                <div class="mt-2 flex items-center gap-2">
                    <a href="https://wa.me/?text={{ urlencode($product->name . ' — ' . route('product.show', $product)) }}"
                       target="_blank" rel="noopener"
                       class="flex items-center gap-1.5 rounded-lg bg-[#25D366]/10 px-3 py-1.5 text-xs font-medium text-[#25D366] transition hover:bg-[#25D366]/20"
                       aria-label="{{ __('Share on WhatsApp') }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.117.554 4.1 1.523 5.823L0 24l6.335-1.509A11.945 11.945 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.885 0-3.655-.502-5.193-1.38l-.371-.213-3.762.895.952-3.648-.233-.384A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                        WhatsApp
                    </a>
                    <button type="button" id="copy-link-btn"
                            class="flex items-center gap-1.5 rounded-lg bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-200"
                            x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText(window.location.href).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                        <template x-if="!copied">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2m-6 12h8a2 2 0 0 1 2-2v-8a2 2 0 0 1-2-2h-8a2 2 0 0 1-2 2v8a2 2 0 0 1 2 2z"/></svg>
                        </template>
                        <template x-if="copied">
                            <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
                        </template>
                        <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy Link') }}'"></span>
                    </button>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <span class="text-2xl font-bold text-brand-900">{{ $product->formattedPrice() }}</span>
                    @if ($product->hasDiscount())
                        <span class="text-base text-brand-400 line-through">{{ $product->formattedCompareAtPrice() }}</span>
                        <span class="rounded-full bg-accent-500 px-2 py-0.5 text-xs font-bold text-white fa-num">{{ $product->discountPercent() }}% {{ __('Off') }}</span>
                    @endif
                </div>

                @if ($product->summary)
                    <p class="mt-4 text-sm leading-7 text-brand-600">{{ $product->summary }}</p>
                @endif

                {{-- Gift-bundle contents: auto-rendered «شامل» list. Each row is a
                     child variant (qty, thumbnail, name, size/colour). Replaces the
                     colour/size pickers (which the controller blanks for bundles). --}}
                @if ($product->is_bundle && $product->bundleItems->isNotEmpty())
                    <div class="mt-6 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-100">
                        <h3 class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-800"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />{{ __('In This Pack') }}</h3>
                        <ul class="space-y-2.5">
                            @foreach ($product->bundleItems as $bi)
                                @php($cv = $bi->variant)
                                @php($cp = $cv?->product)
                                @if ($cv && $cp)
                                    <li class="flex items-center gap-3">
                                        <span class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-white ring-1 ring-brand-100">
                                            @if ($img = $cp->images->first()?->path)
                                                <img src="{{ $img }}" alt="{{ $cp->name }}" class="h-full w-full object-cover">
                                            @else
                                                <span class="text-xs text-brand-300">—</span>
                                            @endif
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-brand-800">{{ $cp->name }}</span>
                                            <span class="block text-xs text-brand-500">
                                                @if ($cv->size && $cv->size !== '—') {{ __('Size') }} {{ $cv->size }}@endif
                                                @if ($cv->color) · {{ $cv->color }}@endif
                                            </span>
                                        </span>
                                        <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-brand-700 ring-1 ring-brand-100 fa-num">× {{ $bi->quantity }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Colors --}}
                @if ($colors->isNotEmpty())
                    <div class="mt-6">
                        <span class="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-800"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />{{ __('Colour') }}</span>
                        <div class="flex flex-wrap gap-2" data-colors>
                            @foreach ($colors as $color)
                                <button type="button" data-color="{{ $color['name'] }}"
                                        class="flex items-center gap-2 rounded-full border border-brand-200 px-3 py-1.5 text-sm text-brand-700 transition hover:border-brand-400 data-[active=true]:border-brand-800 data-[active=true]:bg-brand-50">
                                    <span class="h-4 w-4 rounded-full ring-1 ring-black/10" style="background: {{ $color['hex'] ?? '#ddd' }}"></span>
                                    {{ $color['name'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Sizes --}}
                @if ($sizes->isNotEmpty() && ! $product->isExternal())
                    <div class="mt-6">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-800"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />{{ __('Size') }}</span>
                            @if ($product->sizeGuide && $product->sizeGuide->is_active)
                                <button type="button" x-data @click="$dispatch('open-size-guide')" class="text-xs text-accent-600 hover:underline">{{ __('Size Guide') }}</button>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2" data-sizes>
                            @foreach ($sizes as $size)
                                <button type="button" data-size="{{ $size }}"
                                        class="min-w-11 rounded-lg border border-brand-200 px-3 py-2 text-sm text-brand-700 transition hover:border-brand-400 data-[active=true]:border-brand-800 data-[active=true]:bg-brand-900 data-[active=true]:text-white">
                                    {{ $size }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Stock urgency indicator --}}
                <div id="stock-indicator" class="hidden mt-4">
                    <p id="stock-msg" class="flex items-center gap-1.5 text-sm font-medium text-red-600">
                        <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" clip-rule="evenodd"/></svg>
                        <span></span>
                    </p>
                </div>

                {{-- Stock + quantity + add to cart --}}
                <div class="mt-8 flex items-center gap-3">
                    @if ($product->isExternal())
                        <a href="{{ $product->external_url }}" target="_blank" rel="noopener nofollow sponsored"
                           class="flex-1 rounded-full bg-brand-900 py-3.5 text-center text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[0.98]">
                            {{ __('Buy from Seller') }}
                        </a>
                    @else
                    {{-- Quantity stepper (outside form; JS writes to #qty-input) --}}
                    <div class="flex shrink-0 items-center rounded-xl border border-brand-200 bg-white">
                        <button type="button" id="qty-minus"
                                class="flex h-11 w-11 items-center justify-center rounded-r-xl text-brand-500 transition hover:bg-brand-50 hover:text-brand-900 active:scale-95">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14"/></svg>
                        </button>
                        <span id="qty-display" class="w-10 select-none text-center text-sm font-semibold text-brand-900 fa-num">1</span>
                        <button type="button" id="qty-plus"
                                class="flex h-11 w-11 items-center justify-center rounded-l-xl text-brand-500 transition hover:bg-brand-50 hover:text-brand-900 active:scale-95">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                    </div>

                    {{-- Cart form --}}
                    <form action="{{ route('cart.add') }}" method="POST" class="flex-1" data-cart-form>
                        @csrf
                        <input type="hidden" name="variant_id" data-variant-id value="">
                        <input type="hidden" name="quantity" id="qty-input" value="1">
                        <button type="submit" data-add-to-cart
                                class="w-full rounded-full bg-brand-900 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                                @disabled(! $product->inStock())>
                            {{ $product->inStock() ? __('Add to Bag') : __('Sold Out') }}
                        </button>
                    </form>
                    @endif

                    {{-- Wishlist form (sibling, not nested) --}}
                    @auth
                        <form action="{{ route('wishlist.toggle', $product) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="grid h-12 w-12 shrink-0 place-items-center rounded-full ring-1 ring-brand-200 transition hover:bg-brand-50 {{ ($inWishlist ?? false) ? 'bg-red-50 text-red-500 ring-red-200' : 'text-brand-500' }}"
                                    aria-label="{{ ($inWishlist ?? false) ? __('Remove from wishlist') : __('Add to wishlist') }}">
                                <svg class="h-5 w-5" fill="{{ ($inWishlist ?? false) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-9.5-8.5C.5 9 2 5.5 5.5 5.5 7.5 5.5 9 7 12 9c3-2 4.5-3.5 6.5-3.5 3.5 0 5 3.5 3 7C19 16.5 12 21 12 21z"/></svg>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"
                           class="grid h-12 w-12 shrink-0 place-items-center rounded-full text-brand-500 ring-1 ring-brand-200 transition hover:bg-brand-50"
                           aria-label="{{ __('Wishlist') }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-9.5-8.5C.5 9 2 5.5 5.5 5.5 7.5 5.5 9 7 12 9c3-2 4.5-3.5 6.5-3.5 3.5 0 5 3.5 3 7C19 16.5 12 21 12 21z"/></svg>
                        </a>
                    @endauth
                </div>
                <p data-cart-note class="mt-3 hidden text-sm text-accent-600"></p>

                {{-- Back in stock notification --}}
                <div id="back-in-stock-form" class="hidden mt-3">
                    @if (session('status'))
                        <p class="rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200">{{ session('status') }}</p>
                    @else
                        <div class="rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-100">
                            <p class="mb-3 text-sm font-medium text-brand-800">{{ __("This size/colour is sold out — we'll let you know when it's back.") }}</p>
                            <form action="{{ route('stock.notify') }}" method="POST" class="flex gap-2">
                                @csrf
                                <input type="hidden" name="variant_id" id="notify-variant-id" value="">
                                <input type="tel" name="phone" placeholder="0912 *** ****" dir="ltr" inputmode="numeric"
                                       class="w-full rounded-xl border border-brand-200 px-3 py-2.5 text-center text-sm outline-none transition focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
                                       required>
                                <button type="submit"
                                        class="shrink-0 rounded-xl bg-brand-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">
                                    {{ __('Notify Me') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- Trust badges with SVG icons --}}
                <div class="mt-8 grid grid-cols-3 gap-3 border-t border-brand-100 pt-6">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-brand-50 text-brand-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 1-.987-1.106v4.964m11 8.7H8.25"/></svg>
                        </div>
                        <span class="text-xs font-medium text-brand-600">{{ __('Fast Delivery') }}</span>
                    </div>
                    <div class="flex flex-col items-center gap-2 text-center">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-brand-50 text-brand-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        </div>
                        <span class="text-xs font-medium text-brand-600">{{ __('Secure Payment') }}</span>
                    </div>
                    <div class="flex flex-col items-center gap-2 text-center">
                        <div class="grid h-10 w-10 place-items-center rounded-full bg-brand-50 text-brand-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0"/></svg>
                        </div>
                        <span class="text-xs font-medium text-brand-600">{{ __('Authenticity Guaranteed') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Description --}}
        <div class="mt-14 max-w-3xl">
            <h2 class="mb-3 text-lg font-bold text-brand-900">{{ __('Product Details') }}</h2>
            <div class="prose-sm leading-8 text-brand-700">{!! $product->description !!}</div>
        </div>

        {{-- Related --}}
        @if ($related->isNotEmpty())
            <div class="mt-16">
                <h2 class="mb-6 text-xl font-bold text-brand-900">{{ __('You May Also Like') }}</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach ($related as $item)
                        <x-product-card :product="$item" />
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recently viewed --}}
        <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6" id="recently-viewed" style="display:none">
            <h2 class="mb-5 text-lg font-bold text-brand-900">{{ __('Recently Viewed') }}</h2>
            <div id="recently-viewed-grid" class="grid grid-cols-2 gap-3 sm:grid-cols-4"></div>
        </section>
    </div>

    {{-- Sticky mobile add-to-cart bar — only on small screens, slides up when main form scrolls out --}}
    @unless ($product->isExternal())
    <div id="sticky-atc"
         class="lg:hidden fixed inset-x-0 bottom-0 z-40 translate-y-full bg-white border-t border-brand-100 shadow-[0_-4px_24px_rgba(0,0,0,0.10)] transition-transform duration-300 ease-out"
         aria-hidden="true">
        <div class="flex items-center gap-3 px-4 py-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px))">
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-brand-900">{{ $product->name }}</p>
                <p id="sticky-price" class="text-xs font-bold text-accent-600 fa-num">{{ $product->formattedPrice() }}</p>
            </div>
            <form action="{{ route('cart.add') }}" method="POST" id="sticky-cart-form" class="shrink-0">
                @csrf
                <input type="hidden" name="variant_id" id="sticky-variant-id" value="">
                <input type="hidden" name="quantity" id="sticky-qty" value="1">
                <button type="submit" id="sticky-atc-btn"
                        class="rounded-full bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white transition active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                        @disabled(! $product->inStock())>
                    {{ $product->inStock() ? __('Add to Bag') : __('Sold Out') }}
                </button>
            </form>
        </div>
    </div>
    @endunless

    {{-- Size guide modal — opens over the product page, no navigation away --}}
    @if ($product->sizeGuide && $product->sizeGuide->is_active)
        <div x-data="{ open: false }" @open-size-guide.window="open = true" x-cloak>
            <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
                <div @click="open = false" class="absolute inset-0 bg-brand-950/50"></div>
                <div class="relative z-10 max-h-[85vh] w-full max-w-2xl overflow-auto rounded-card bg-white p-6 shadow-xl ring-1 ring-brand-100">
                    <div class="mb-4 flex items-center justify-between border-b border-brand-100 pb-3">
                        <h3 class="text-base font-bold text-brand-900">{{ $product->sizeGuide->name }}</h3>
                        <button type="button" @click="open = false" class="text-xl leading-none text-brand-400 hover:text-brand-700" aria-label="{{ __('Close') }}">&times;</button>
                    </div>
                    @if ($product->sizeGuide->image_path)
                        <img src="{{ $product->sizeGuide->image_path }}" alt="{{ $product->sizeGuide->name }}" class="mb-3 w-full rounded-lg">
                    @endif
                    @if (trim((string) $product->sizeGuide->content) !== '')
                        <div class="size-guide-content -mx-1 overflow-x-auto px-1 text-sm leading-7 text-brand-700">
                            {!! $product->sizeGuide->content !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <style>
            .size-guide-content table{width:100%;border-collapse:collapse;margin:8px 0;}
            .size-guide-content th,.size-guide-content td{border:1px solid #e7dfe2;padding:8px 10px;text-align:center;}
            .size-guide-content th{background:#faf6f7;font-weight:600;color:#282828;}
            .size-guide-content h3,.size-guide-content h4{font-weight:700;margin:10px 0 6px;color:#282828;}
            .size-guide-content ul,.size-guide-content ol{padding-inline-start:20px;margin:6px 0;}
        </style>
    @endif
@endsection

@push('head')
<script defer>
document.addEventListener('DOMContentLoaded', function () {
    var bar = document.getElementById('sticky-atc');
    if (!bar) return;

    var mainForm    = document.querySelector('[data-cart-form]');
    var mainBtn     = document.querySelector('[data-add-to-cart]');
    var mainVariant = mainForm ? mainForm.querySelector('[name="variant_id"]') : null;
    var mainQty     = document.getElementById('qty-input');
    var stickyVar   = document.getElementById('sticky-variant-id');
    var stickyQty   = document.getElementById('sticky-qty');
    var stickyBtn   = document.getElementById('sticky-atc-btn');

    function syncSticky() {
        if (mainVariant) stickyVar.value = mainVariant.value;
        if (mainQty)     stickyQty.value  = mainQty.value;
        if (mainBtn) {
            stickyBtn.disabled    = mainBtn.disabled;
            stickyBtn.textContent = mainBtn.textContent.trim();
        }
    }

    // Sync when user picks a color or size
    document.querySelectorAll('[data-color], [data-size]').forEach(function (el) {
        el.addEventListener('click', function () { setTimeout(syncSticky, 60); });
    });

    // Sync when qty stepper changes
    document.querySelectorAll('#qty-minus, #qty-plus').forEach(function (el) {
        el.addEventListener('click', function () { setTimeout(syncSticky, 60); });
    });

    // Watch the main button's disabled attribute and text (set by app.js variant logic)
    if (mainBtn) {
        new MutationObserver(syncSticky).observe(mainBtn, {
            attributes: true, attributeFilter: ['disabled'],
            childList: true, subtree: true, characterData: true
        });
    }

    // Show/hide bar as main form enters/leaves viewport
    if (mainForm) {
        var io = new IntersectionObserver(function (entries) {
            var visible = entries[0].isIntersecting;
            bar.classList.toggle('translate-y-full', visible);
            bar.setAttribute('aria-hidden', visible ? 'true' : 'false');
            if (!visible) syncSticky();
        }, { threshold: 0.4 });
        io.observe(mainForm);
    }

    syncSticky();
});
</script>
@endpush

@push('head')
{{-- Recently-viewed strip — stored in localStorage so it survives between
     pages without server-side history. The current product is prepended
     (de-duped, capped at 8); prior items render in the strip. --}}
<script id="chiiaco-product-payload" type="application/json">{!! json_encode([
    'id' => $product->id,
    'slug' => $product->slug,
    'name' => $product->name,
    'image' => $product->primary_image_url,
    'image2' => $product->images->skip(1)->first()?->path,
    'price' => \App\Support\Money::toman((int) $product->price),
    'url' => route('product.show', $product),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<script defer>
@verbatim
(function () {
    var KEY = 'chiiaco_recently_viewed_v1', CAP = 8;
    var payloadEl = document.getElementById('chiiaco-product-payload');
    if (!payloadEl) return;
    var current;
    try { current = JSON.parse(payloadEl.textContent || '{}'); } catch (e) { return; }
    if (!current.id) return;

    var list = [];
    try { list = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { list = []; }
    if (!Array.isArray(list)) list = [];
    list = list.filter(function (p) { return p && p.id && p.id !== current.id; });
    list.unshift(current);
    list = list.slice(0, CAP);
    try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* quota / private mode */ }

    var prior = list.filter(function (p) { return p.id !== current.id; });
    if (!prior.length) return;
    var section = document.getElementById('recently-viewed');
    var grid = document.getElementById('recently-viewed-grid');
    if (!section || !grid) return;

    var escapeHtml = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    };

    grid.innerHTML = prior.slice(0, 4).map(function (p) {
        // Hover image-swap: when a second photo exists, layer it on top and
        // crossfade in on desktop hover (matches the <x-product-card> behaviour).
        // The `@@media` escapes are for Blade — they emit a literal `@media` in
        // the JS string, which is part of Tailwind's arbitrary-variant syntax.
        var hasSwap = p.image && p.image2;
        var primaryCls = 'h-full w-full object-cover transition-opacity duration-500'
            + (hasSwap ? ' [@media(hover:hover)]:group-hover:opacity-0' : ' group-hover:scale-105');
        var img = p.image
            ? ('<img src="' + escapeHtml(p.image) + '" alt="' + escapeHtml(p.name) + '" loading="lazy" class="' + primaryCls + '">'
               + (hasSwap
                   ? '<img src="' + escapeHtml(p.image2) + '" alt="" aria-hidden="true" loading="lazy" class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500 pointer-events-none [@media(hover:hover)]:group-hover:opacity-100">'
                   : ''))
            : '<div class="grid h-full w-full place-items-center text-xs text-brand-300">' + escapeHtml(p.name) + '</div>';
        return '<a href="' + escapeHtml(p.url) + '" class="group block overflow-hidden rounded-2xl bg-brand-50 ring-1 ring-brand-100 transition hover:ring-accent-300">'
             +   '<div class="relative aspect-[3/4] overflow-hidden">' + img + '</div>'
             +   '<div class="p-3"><p class="line-clamp-1 text-sm font-medium text-brand-800">' + escapeHtml(p.name) + '</p>'
             +     '<p class="mt-1 text-xs font-bold text-accent-600 fa-num">' + escapeHtml(p.price) + '</p></div>'
             + '</a>';
    }).join('');
    section.style.display = '';
})();
@endverbatim
</script>
@endpush

@props(['product', 'style' => 'minimal', 'aspect' => 'aspect-[3/4]'])

@php($img = $product->primary_image_url ?? '/placeholder?w=800&h=1000&label='.urlencode($product->name))
@php($webpSrcset = \App\Support\ImageOptimizer::srcsetFor($img))
@php($totalStock = $product->variants->sum('stock'))
@php($firstVariant = $product->variants->firstWhere(fn ($v) => $v->inStock()))
@php($singleVariant = $product->variants->count() === 1 && $firstVariant)

@if ($style === 'minimal')
    {{-- Minimal editorial card (rastah-style): image · «+» quick-add circle ·
         name + price. Strips hover overlays, gradients, sticky CTA, ring,
         and shadow lift so the photo carries the card.
         If the product has a second image, hover (desktop only) cross-fades
         to it — the standard fashion-card move. Mobile shows the first image
         only since there's no hover state. --}}
    @php($secondaryImg = $product->images->skip(1)->first()?->path)
    <div class="group relative">
        <a href="{{ route('product.show', $product) }}" class="block">
            <div class="relative {{ $aspect }} overflow-hidden bg-brand-50">
                <picture>
                    @if ($webpSrcset)
                        <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw">
                    @endif
                    <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy"
                         class="h-full w-full object-cover transition-opacity duration-500 {{ $secondaryImg ? '[@media(hover:hover)]:group-hover:opacity-0' : '' }}">
                </picture>
                @if ($secondaryImg)
                    {{-- Hover-only secondary image; absolutely positioned on top,
                         crossfades in. Hidden on touch devices (no hover). --}}
                    <img src="{{ $secondaryImg }}" alt="{{ $product->name }}" loading="lazy" aria-hidden="true"
                         class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500 pointer-events-none [@media(hover:hover)]:group-hover:opacity-100">
                @endif
                @if ($product->hasDiscount())
                    <span class="absolute start-2 top-2 rounded-sm bg-white px-1.5 py-0.5 text-[10px] font-medium text-brand-900 ring-1 ring-brand-100 fa-num">
                        {{ $product->discountPercent() }}%
                    </span>
                @endif
                @unless ($product->inStock())
                    <span class="absolute start-2 top-2 rounded-sm bg-white px-1.5 py-0.5 text-[10px] font-medium text-brand-900 ring-1 ring-brand-100">Sold Out</span>
                @endunless
            </div>
            <div class="mt-3 px-1">
                <p class="line-clamp-1 text-xs font-medium uppercase tracking-wide text-brand-900 sm:text-sm">{{ $product->name }}</p>
                <p class="mt-1 text-xs text-brand-700 fa-num sm:text-sm">{{ $product->formattedPrice() }}</p>
            </div>
        </a>

        {{-- Quick-add circle on the image's bottom-end corner. One in-stock size
             → one-tap add. Multiple sizes → «+» opens a size picker so the
             shopper adds the RIGHT size straight from the grid (fewer wrong-size
             adds / returns). --}}
        @php($inStockVariants = $product->variants->filter(fn ($v) => $v->inStock())->values())
        @if ($product->inStock() && $inStockVariants->isNotEmpty())
            @if ($inStockVariants->count() === 1)
                <form action="{{ route('cart.add') }}" method="POST"
                      class="absolute end-2 top-[calc(75%-2.25rem)] sm:top-auto sm:bottom-[3.25rem]"
                      onclick="event.stopPropagation()">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $inStockVariants->first()->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" aria-label="Add to bag"
                            class="grid h-8 w-8 place-items-center rounded-full bg-white text-brand-900 ring-1 ring-brand-200 transition hover:bg-brand-900 hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                </form>
            @else
                <div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false"
                     class="absolute end-2 top-[calc(75%-2.25rem)] sm:top-auto sm:bottom-[3.25rem]"
                     onclick="event.stopPropagation()">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Select size and add to bag"
                            class="grid h-8 w-8 place-items-center rounded-full bg-white text-brand-900 ring-1 ring-brand-200 transition hover:bg-brand-900 hover:text-white">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                    <div x-show="open" x-cloak x-transition
                         class="absolute end-0 bottom-full mb-2 flex max-w-[11rem] flex-wrap justify-end gap-1 rounded-xl bg-white p-2 shadow-lg ring-1 ring-brand-100">
                        @foreach ($inStockVariants as $v)
                            <form action="{{ route('cart.add') }}" method="POST">
                                @csrf
                                <input type="hidden" name="variant_id" value="{{ $v->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit"
                                        class="min-w-[2rem] rounded-md border border-brand-200 px-2 py-1 text-xs font-medium text-brand-800 transition hover:border-accent-400 hover:bg-brand-50 hover:text-accent-600">{{ $v->size ?: 'Add' }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
@else
<div class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-brand-100 transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-brand-900/10 hover:ring-brand-200 cursor-pointer">

    {{-- Full-cover transparent link (z-1) — makes entire card clickable --}}
    <a href="{{ route('product.show', $product) }}"
       class="absolute inset-0 z-[1] rounded-2xl"
       aria-label="{{ $product->name }}"></a>

    {{-- Image (with hover swap to secondary photo on desktop) --}}
    @php($secondaryImg = $product->images->skip(1)->first()?->path)
    <div class="relative {{ $aspect }} overflow-hidden bg-brand-50">
        <picture>
            @if ($webpSrcset)
                <source type="image/webp" srcset="{{ $webpSrcset }}" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw">
            @endif
            <img src="{{ $img }}" alt="{{ $product->name }}" loading="lazy"
                 class="h-full w-full object-cover transition-all duration-500 ease-out group-hover:scale-110 {{ $secondaryImg ? '[@media(hover:hover)]:group-hover:opacity-0' : '' }}">
        </picture>
        @if ($secondaryImg)
            <img src="{{ $secondaryImg }}" alt="{{ $product->name }}" loading="lazy" aria-hidden="true"
                 class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500 pointer-events-none [@media(hover:hover)]:group-hover:opacity-100">
        @endif

        {{-- Gradient scrim on hover --}}
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-brand-950/60 via-brand-950/10 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>

        {{-- Hover CTAs — slides up from bottom on hover (in-stock only, so it never
             collides with the out-of-stock banner; z-[2] sits above the cover link) --}}
        @if ($product->inStock())
        <div class="pointer-events-none absolute inset-x-3 bottom-3 z-[2] translate-y-3 opacity-0 transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
            <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-center gap-1.5 rounded-full bg-white/95 py-2.5 text-xs font-bold text-brand-900 shadow-lg backdrop-blur-sm">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><circle cx="12" cy="12" r="3"/></svg>
                    View Product
                </div>
                @if ($firstVariant)
                    <form action="{{ route('cart.add') }}" method="POST" class="pointer-events-auto" onclick="event.stopPropagation()">
                        @csrf
                        <input type="hidden" name="variant_id" value="{{ $firstVariant->id }}">
                        <input type="hidden" name="quantity" value="1">
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-1.5 rounded-full bg-brand-900 py-2.5 text-xs font-bold text-white shadow-lg transition hover:bg-brand-800 active:scale-[0.97]">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            Add to Bag
                        </button>
                    </form>
                @endif
            </div>
        </div>
        @endif

        {{-- Badges top-right --}}
        <div class="pointer-events-none absolute start-3 top-3 flex flex-col gap-1.5 z-[2]">
            @if ($product->hasDiscount())
                <span class="rounded-full bg-accent-600 px-2.5 py-1 text-[11px] font-bold text-white fa-num shadow-sm">
                    {{ $product->discountPercent() }}%
                </span>
            @endif
        </div>

        {{-- Low-stock urgency badge --}}
        @if ($totalStock > 0 && $totalStock <= 5)
            <span class="absolute top-2 end-2 z-[3] rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white">
                Only {{ $totalStock }} left
            </span>
        @endif

        {{-- Out of stock overlay --}}
        @unless ($product->inStock())
            <div class="pointer-events-none absolute inset-x-0 bottom-0 z-[2] bg-brand-900/75 py-2 text-center text-xs font-medium text-white backdrop-blur-sm">Sold Out</div>
        @endunless

        {{-- Wishlist button (z-2, above full-cover link) --}}
        @auth
        <form action="{{ route('wishlist.toggle', $product) }}" method="POST"
              class="absolute end-3 top-3 z-[2]">
            @csrf
            <button type="submit"
                    aria-label="Add to wishlist"
                    class="grid h-8 w-8 place-items-center rounded-full bg-white/90 text-brand-400 shadow-sm backdrop-blur-sm ring-1 ring-brand-100 transition-all duration-200 hover:bg-white hover:text-red-500 hover:ring-red-200 sm:translate-x-2 sm:opacity-0 sm:group-hover:translate-x-0 sm:group-hover:opacity-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-9.5-8.5C.5 9 2 5.5 5.5 5.5 7.5 5.5 9 7 12 9c3-2 4.5-3.5 6.5-3.5 3.5 0 5 3.5 3 7C19 16.5 12 21 12 21z"/></svg>
            </button>
        </form>
        @endauth
    </div>

    {{-- Info --}}
    <div class="p-4">
        @if ($product->category?->name)
            <p class="text-[10px] font-semibold uppercase tracking-widest text-brand-400">{{ $product->category->name }}</p>
        @endif
        <h3 class="mt-0.5 line-clamp-2 text-sm font-semibold leading-snug text-brand-900">{{ $product->name }}</h3>

        <div class="mt-3 flex items-end justify-between gap-2">
            <div class="leading-tight">
                <div class="text-[15px] font-bold text-brand-900 fa-num">{{ $product->formattedPrice() }}</div>
                @if ($product->hasDiscount())
                    <div class="text-[11px] text-brand-400 line-through fa-num">{{ $product->formattedCompareAtPrice() }}</div>
                @endif
            </div>

            {{-- Desktop: arrow hint (hidden on touch) --}}
            <div class="hidden shrink-0 text-brand-200 transition-colors duration-200 group-hover:text-brand-500 [@media(hover:hover)]:block">
                <svg class="h-5 w-5 -scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </div>

            {{-- Touch: always-visible quick-add button (hidden on hover devices) --}}
            @if ($firstVariant && $product->inStock())
            <form action="{{ route('cart.add') }}" method="POST" class="[@media(hover:hover)]:hidden z-[2] relative" onclick="event.stopPropagation()">
                @csrf
                <input type="hidden" name="variant_id" value="{{ $firstVariant->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit"
                        class="grid h-8 w-8 place-items-center rounded-full bg-brand-900 text-white shadow-sm transition active:scale-95">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endif

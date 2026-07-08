@php
    $products = \App\Support\Blocks\BlockData::products($data);

    $layout      = ($data['layout'] ?? 'grid') === 'carousel' ? 'carousel' : 'grid';
    $cardStyle   = ($data['card_style'] ?? 'minimal') === 'detailed' ? 'detailed' : 'minimal';
    $cardSize    = $data['card_size'] ?? 'compact';
    $cardAspect  = match ($data['card_aspect'] ?? 'portrait') {
        'tall'   => 'aspect-[2/3]',
        'square' => 'aspect-square',
        'wide'   => 'aspect-[4/3]',
        default  => 'aspect-[3/4]',
    };
    $shopAllLink = trim((string) ($data['shop_all_link'] ?? '')) ?: route('shop.index');
    $heading     = trim((string) ($data['heading'] ?? ''));

    // Per-breakpoint column counts (grid mode) — resolve to a known string
    // even when the admin field is missing so the class-map lookups can't
    // fall through to an undefined key.
    $colsMobile  = (string) ($data['columns_mobile']  ?? '2');
    $colsDesktop = (string) ($data['columns_desktop'] ?? '4');
    if (! in_array($colsMobile,  ['1','2','3'], true))           $colsMobile  = '2';
    if (! in_array($colsDesktop, ['2','3','4','5','6'], true))   $colsDesktop = '4';
    $colsMobileClass = ['1' => 'grid-cols-1', '2' => 'grid-cols-2', '3' => 'grid-cols-3'][$colsMobile];
    $colsDesktopClass = [
        '2' => 'lg:grid-cols-2', '3' => 'lg:grid-cols-3', '4' => 'lg:grid-cols-4',
        '5' => 'lg:grid-cols-5', '6' => 'lg:grid-cols-6',
    ][$colsDesktop];

    // Container width
    $containerClass = match ($data['container'] ?? 'default') {
        'wide' => 'max-w-screen-2xl',
        'full' => 'max-w-none',
        default => 'max-w-7xl',
    };

    // Gap between cards
    $gapClass = match ($data['gap'] ?? 'normal') {
        'tight' => 'gap-2 sm:gap-2.5',
        'wide'  => 'gap-6 sm:gap-8',
        default => 'gap-3 sm:gap-4',
    };

    // Vertical padding on the section
    $padClass = match ($data['padding'] ?? 'md') {
        'none' => 'py-0',
        'sm'   => 'py-6 sm:py-8',
        'lg'   => 'py-16 sm:py-24',
        default => 'py-10 sm:py-14',
    };
@endphp

@if ($products->isNotEmpty())
    @if ($layout === 'carousel')
        @php($carouselId = 'prod-carousel-'.\Illuminate\Support\Str::random(6))

        {{-- Horizontal-scroll carousel (rastah.co style) — ~4.5 cards visible
             on desktop, 2.2 on mobile. card_size knob still exists but the
             three options now hover around the same compact band so the
             layout always reads as «editorial scroll», never «hero cards».
             Any saved value (incl. legacy 'full') resolves into this band. --}}
            @php($cardWidthClass = match ($cardSize) {
                'large' => 'w-[60vw] sm:w-[40vw] md:w-[32vw] lg:w-[26vw] xl:w-[24vw]',
                'med'   => 'w-[50vw] sm:w-[34vw] md:w-[28vw] lg:w-[23vw] xl:w-[20vw]',
                default => 'w-[44vw] sm:w-[30vw] md:w-[24vw] lg:w-[20vw] xl:w-[18vw]', // compact
            })
            <section class="bg-white {{ $padClass }}">
                <div class="mx-auto flex {{ $containerClass }} items-end justify-between gap-4 border-t border-brand-100 px-4 py-6 sm:px-6 sm:py-8">
                    <h2 class="font-display text-lg font-bold uppercase tracking-wide text-brand-900 sm:text-xl">{{ $heading ?: 'Products' }}</h2>
                    <a href="{{ $shopAllLink }}"
                       class="shrink-0 border-b border-brand-900 pb-0.5 text-sm font-medium text-brand-900 underline-offset-4 transition hover:opacity-70">View All</a>
                </div>
                <div class="relative">
                    <div id="{{ $carouselId }}"
                         data-chiiaco-marquee data-autoplay="0" data-speed-px="0"
                         class="overflow-x-auto overscroll-x-contain pb-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden cursor-grab">
                        <div class="flex w-max items-stretch {{ $gapClass }} px-4 sm:px-6">
                            @foreach ($products as $product)
                                <div class="{{ $cardWidthClass }} shrink-0">
                                    <x-product-card :product="$product" :style="$cardStyle" :aspect="$cardAspect" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="button" data-marquee-prev="{{ $carouselId }}" aria-label="Previous"
                            class="absolute end-3 top-[45%] z-10 hidden -translate-y-1/2 place-items-center rounded-full bg-white/95 text-brand-900 shadow-md ring-1 ring-brand-100 transition hover:bg-white sm:grid sm:h-11 sm:w-11">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <button type="button" data-marquee-next="{{ $carouselId }}" aria-label="Next"
                            class="absolute start-3 top-[45%] z-10 hidden -translate-y-1/2 place-items-center rounded-full bg-white/95 text-brand-900 shadow-md ring-1 ring-brand-100 transition hover:bg-white sm:grid sm:h-11 sm:w-11">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </section>
    @else
        {{-- GRID layout — per-breakpoint column counts, aspect, gap, padding,
             container all admin-driven. --}}
        <section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
            @if ($heading !== '')
                <div class="reveal mb-8 flex items-end justify-between">
                    <h2 class="text-2xl font-bold text-brand-900">{{ $heading }}</h2>
                    <a href="{{ $shopAllLink }}" class="text-sm font-medium text-accent-600 hover:underline">View All</a>
                </div>
            @endif
            <div class="grid {{ $colsMobileClass }} {{ $colsDesktopClass }} {{ $gapClass }}">
                @foreach ($products as $product)
                    <div class="reveal"><x-product-card :product="$product" :style="$cardStyle" :aspect="$cardAspect" /></div>
                @endforeach
            </div>
        </section>
    @endif
@endif

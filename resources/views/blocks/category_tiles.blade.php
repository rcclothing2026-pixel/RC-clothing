@php($categories = \App\Support\Blocks\BlockData::categories($data))
@php($contain = ($data['fit'] ?? 'cover') === 'contain')
{{-- contain → transparent PNG shown whole (no circle bg, no ring, no card frame); cover → photo in a framed circle --}}
@php($cardCls = $contain ? 'p-2' : 'p-3')
@php($avatarCls = $contain ? '' : 'overflow-hidden rounded-full bg-brand-50')
@php($imgCls = $contain ? 'object-contain' : 'object-cover transition duration-500 group-hover:scale-110')
@php($layout = in_array($data['layout'] ?? 'wrap', ['marquee', 'cards'], true) ? ($data['layout'] ?? 'wrap') : 'wrap')
@php($colsMobile = (string) ($data['columns_mobile'] ?? '2'))
@php($colsDesktop = (string) ($data['columns_desktop'] ?? '6'))
@php($colsMobileMap  = ['2' => 'basis-[calc(50%-0.5rem)]', '3' => 'basis-[calc(33.333%-0.667rem)]', '4' => 'basis-[calc(25%-0.75rem)]'])
@php($colsDesktopMap = ['4' => 'lg:basis-[calc(25%-0.75rem)]', '5' => 'lg:basis-[calc(20%-0.8rem)]', '6' => 'lg:basis-[calc(16.666%-0.834rem)]', '7' => 'lg:basis-[calc(14.285%-0.858rem)]', '8' => 'lg:basis-[calc(12.5%-0.875rem)]'])
@php($colsMobileClass = $colsMobileMap[$colsMobile] ?? $colsMobileMap['2'])
@php($colsDesktopClass = $colsDesktopMap[$colsDesktop] ?? $colsDesktopMap['6'])
@php($containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-7xl' })
@php($padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-8', 'lg' => 'py-20 sm:py-28', default => 'py-16' })
{{-- Marquee auto-scroll speed in pixels per second. The JS in app.js
     advances scrollLeft by this much per second; the user can still
     drag/swipe/wheel/use buttons to scrub manually. --}}
@php($speedPx = match ($data['speed'] ?? 'med') { 'slow' => 20, 'fast' => 80, default => 40 })
@php($marqueeId = 'cat-marquee-'.\Illuminate\Support\Str::random(6))
@if ($categories->isNotEmpty())
    <section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
        <div class="reveal mb-8 flex items-end justify-between">
            <h2 class="text-2xl font-bold text-brand-900">{{ ($data['heading'] ?? null) ?: 'Categories' }}</h2>
            <a href="{{ route('shop.index') }}" class="text-sm font-medium text-accent-600 hover:underline">{{ __('View All') }}</a>
        </div>

        @if ($layout === 'cards')
            {{-- Editorial cards — large full-bleed category image with a dark
                 gradient and the name overlaid (rastah.co category style).
                 Falls back to a navy card with the name when a category has no
                 image. 2 per row on mobile, columns_desktop on desktop. --}}
            @php($cardRatio = match ($data['card_ratio'] ?? 'portrait') {
                'tall' => 'aspect-[2/3]', 'square' => 'aspect-square', 'wide' => 'aspect-[4/3]', default => 'aspect-[4/5]',
            })
            @php($lgGrid = ['4' => 'lg:grid-cols-4', '5' => 'lg:grid-cols-5', '6' => 'lg:grid-cols-6', '7' => 'lg:grid-cols-7', '8' => 'lg:grid-cols-8'][$colsDesktop] ?? 'lg:grid-cols-5')
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 {{ $lgGrid }}">
                @foreach ($categories as $category)
                    <a href="{{ route('shop.index', ['category' => $category->slug]) }}"
                       class="reveal group relative block overflow-hidden rounded-card bg-brand-900 ring-1 ring-brand-100">
                        <div class="{{ $cardRatio }} overflow-hidden">
                            @if ($category->image_path)
                                <img src="{{ $category->image_path }}" alt="{{ $category->name }}" loading="lazy"
                                     class="h-full w-full object-cover transition duration-700 ease-out group-hover:scale-105">
                            @endif
                        </div>
                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-brand-950/75 via-brand-950/15 to-transparent"></div>
                        <span class="absolute inset-x-0 bottom-0 p-3 text-center font-display text-sm font-semibold uppercase tracking-wide text-white sm:p-4 sm:text-lg">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        @elseif ($layout === 'marquee')
            {{-- Infinite-loop marquee, JS-driven (resources/js/app.js).
                 The track is a native overflow-x scroller so the user can
                 drag, swipe, wheel, or use the arrow buttons; the JS
                 auto-advances scrollLeft and wraps at the halfway point.
                 Items are duplicated 2× for a seamless loop. --}}
            <div class="relative">
                {{-- Edge fade + scrollbar hide --}}
                <div id="{{ $marqueeId }}"
                     data-chiiaco-marquee data-speed-px="{{ $speedPx }}"
                     class="overflow-x-auto overscroll-x-contain [mask-image:linear-gradient(to_right,transparent,#000_6%,#000_94%,transparent)] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden cursor-grab">
                    <div class="flex w-max items-center gap-12 whitespace-nowrap py-2">
                        @for ($pass = 0; $pass < 2; $pass++)
                            @foreach ($categories as $category)
                                <a href="{{ route('shop.index', ['category' => $category->slug]) }}"
                                   class="group flex shrink-0 flex-col items-center gap-3 text-center transition hover:-translate-y-1 {{ $cardCls }}"
                                   aria-hidden="{{ $pass === 1 ? 'true' : 'false' }}"
                                   @if ($pass === 1) tabindex="-1" @endif>
                                    <div class="h-20 w-20 sm:h-24 sm:w-24 {{ $avatarCls }}">
                                        <img src="{{ $category->image_path ?: '/placeholder?w=160&h=160&seed='.urlencode($category->slug) }}" alt="{{ $category->name }}" class="h-full w-full {{ $imgCls }} pointer-events-none" draggable="false">
                                    </div>
                                    <span class="text-sm font-medium text-brand-800">{{ $category->name }}</span>
                                </a>
                            @endforeach
                        @endfor
                    </div>
                </div>

                {{-- Prev / Next arrows. In RTL «next» visually points left
                     (matches the reading flow direction). --}}
                <button type="button" data-marquee-prev="{{ $marqueeId }}"
                        aria-label="{{ __('Previous') }}"
                        class="absolute end-2 top-1/2 z-10 hidden -translate-y-1/2 place-items-center rounded-full bg-white/95 text-brand-900 shadow-md ring-1 ring-brand-100 transition hover:bg-white sm:grid sm:h-10 sm:w-10">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" data-marquee-next="{{ $marqueeId }}"
                        aria-label="{{ __('Next') }}"
                        class="absolute start-2 top-1/2 z-10 hidden -translate-y-1/2 place-items-center rounded-full bg-white/95 text-brand-900 shadow-md ring-1 ring-brand-100 transition hover:bg-white sm:grid sm:h-10 sm:w-10">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
        @else
            {{-- flex-wrap + justify-center so few tiles sit centred; basis matches the
                 old 2/3/6-per-row grid (minus the gap) and stays mobile-friendly. --}}
            <div class="flex flex-wrap justify-center gap-4">
                @foreach ($categories as $category)
                    <a href="{{ route('shop.index', ['category' => $category->slug]) }}"
                       class="reveal group flex {{ $colsMobileClass }} flex-col items-center gap-3 text-center transition hover:-translate-y-1 sm:basis-[calc(33.333%-0.667rem)] {{ $colsDesktopClass }} {{ $cardCls }}">
                        <div class="h-16 w-16 {{ $avatarCls }}">
                            <img src="{{ $category->image_path ?: '/placeholder?w=160&h=160&seed='.urlencode($category->slug) }}" alt="{{ $category->name }}" class="h-full w-full {{ $imgCls }}">
                        </div>
                        <span class="text-sm font-medium text-brand-800">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endif

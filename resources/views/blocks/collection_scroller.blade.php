@php($collections = \App\Support\Blocks\BlockData::collections($data))
@php($size = $data['size'] ?? 'lg')
@php($shape = $data['shape'] ?? 'portrait')
@php($showName = ($data['show_name'] ?? 'no') === 'yes')
@php($w = ['md' => 'w-52 sm:w-60', 'lg' => 'w-64 sm:w-80', 'xl' => 'w-72 sm:w-[24rem]'][$size] ?? 'w-64 sm:w-80')
@php($aspect = ['portrait' => 'aspect-[3/4]', 'square' => 'aspect-square', 'landscape' => 'aspect-[4/3]'][$shape] ?? 'aspect-[3/4]')
@php($contain = ($data['fit'] ?? 'cover') === 'contain')
{{-- contain → transparent PNG shown whole (no gray fill/ring/crop); cover → photo fills the card --}}
@php($cardCls = $contain ? '' : 'rounded-card bg-brand-50 transition')
@php($imgCls = $contain ? 'object-contain p-2' : 'object-cover transition-transform duration-700 ease-out group-hover:scale-105')
@php($layout = ($data['layout'] ?? 'scroll') === 'marquee' ? 'marquee' : 'scroll')
{{-- Marquee auto-scroll speed in pixels per second. The JS in app.js
     advances scrollLeft by this much per second; the user can still
     drag/swipe/wheel/use buttons to scrub manually. --}}
@php($speedPx = match ($data['speed'] ?? 'med') { 'slow' => 30, 'fast' => 110, default => 60 })
@php($marqueeId = 'col-marquee-'.\Illuminate\Support\Str::random(6))
@php($containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-7xl' })
@php($gapClass       = match ($data['gap'] ?? 'normal') { 'tight' => 'gap-2', 'wide' => 'gap-8', default => '{{ $gapClass }}' })
@php($padClass       = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-6', 'lg' => 'py-16 sm:py-24', default => 'py-12' })

@if ($collections->isNotEmpty())
    <section class="{{ $padClass }}">
        @if (! empty($data['heading']))
            <div class="mx-auto mb-6 flex {{ $containerClass }} items-end justify-between px-4 sm:px-6">
                <h2 class="text-2xl font-bold text-brand-900 sm:text-3xl">{{ $data['heading'] }}</h2>
                <a href="{{ route('shop.index') }}" class="text-sm font-medium text-accent-600 hover:underline">View All</a>
            </div>
        @endif

        @if ($layout === 'marquee')
            {{-- Infinite-loop marquee, JS-driven (resources/js/app.js).
                 Native overflow-x scroller so the user can drag, swipe,
                 wheel, or use the arrow buttons; the JS auto-advances
                 scrollLeft and wraps at the halfway point. Items are
                 duplicated 2× for a seamless loop. --}}
            <div class="relative">
                <div id="{{ $marqueeId }}"
                     data-chiiaco-marquee data-speed-px="{{ $speedPx }}"
                     class="overflow-x-auto overscroll-x-contain px-4 pb-4 sm:px-6 [mask-image:linear-gradient(to_right,transparent,#000_4%,#000_96%,transparent)] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden cursor-grab">
                    <div class="flex w-max items-stretch {{ $gapClass }}">
                        @for ($pass = 0; $pass < 2; $pass++)
                            @foreach ($collections as $collection)
                                <a href="{{ route('shop.index', ['collection' => $collection->slug]) }}"
                                   class="group relative {{ $w }} shrink-0 overflow-hidden {{ $cardCls }}"
                                   aria-label="{{ $collection->name }}"
                                   aria-hidden="{{ $pass === 1 ? 'true' : 'false' }}"
                                   @if ($pass === 1) tabindex="-1" @endif>
                                    <div class="{{ $aspect }} w-full overflow-hidden">
                                        <img src="{{ $collection->image_path ?: '/placeholder?w=600&h=800&seed='.urlencode($collection->slug).'&label='.urlencode($collection->name) }}"
                                             alt="{{ $collection->name }}" loading="lazy"
                                             class="h-full w-full {{ $imgCls }} pointer-events-none" draggable="false">
                                    </div>
                                    @if ($showName)
                                        <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-950/70 via-brand-950/10 to-transparent p-4">
                                            <span class="text-base font-bold text-white">{{ $collection->name }}</span>
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        @endfor
                    </div>
                </div>

                {{-- Prev / Next arrows (RTL-aware via app.js direction flag). --}}
                <button type="button" data-marquee-prev="{{ $marqueeId }}"
                        aria-label="Previous"
                        class="absolute end-4 top-1/2 z-10 hidden -translate-y-1/2 place-items-center rounded-full bg-white/95 text-brand-900 shadow-lg ring-1 ring-brand-100 transition hover:bg-white sm:grid sm:h-11 sm:w-11">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <button type="button" data-marquee-next="{{ $marqueeId }}"
                        aria-label="Next"
                        class="absolute start-4 top-1/2 z-10 hidden -translate-y-1/2 place-items-center rounded-full bg-white/95 text-brand-900 shadow-lg ring-1 ring-brand-100 transition hover:bg-white sm:grid sm:h-11 sm:w-11">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </button>
            </div>
        @else
            {{-- Horizontal, snap-scrolling row of large collection images.
                 Inner w-max + mx-auto centres the row when it fits the viewport,
                 and lets it overflow-scroll when there are more than fit. --}}
            <div class="overflow-x-auto overscroll-x-contain px-4 pb-4 sm:px-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <div class="mx-auto flex w-max snap-x snap-mandatory {{ $gapClass }}">
                    @foreach ($collections as $collection)
                        <a href="{{ route('shop.index', ['collection' => $collection->slug]) }}"
                           class="group relative {{ $w }} shrink-0 snap-start overflow-hidden {{ $cardCls }}"
                           aria-label="{{ $collection->name }}">
                            <div class="{{ $aspect }} w-full overflow-hidden">
                                <img src="{{ $collection->image_path ?: '/placeholder?w=600&h=800&seed='.urlencode($collection->slug).'&label='.urlencode($collection->name) }}"
                                     alt="{{ $collection->name }}" loading="lazy"
                                     class="h-full w-full {{ $imgCls }}">
                            </div>
                            @if ($showName)
                                <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-brand-950/70 via-brand-950/10 to-transparent p-4">
                                    <span class="text-base font-bold text-white">{{ $collection->name }}</span>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
@endif

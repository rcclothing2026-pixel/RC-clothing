{{--
    Lookbook grid — Pinterest-style masonry of editorial product photos. Picks
    products via the same multipicker as product_grid (shares BlockData::products).
    Uses CSS columns (browser-native masonry) so no JS required. The admin
    picks mobile + desktop column counts independently; both default to a
    multi-column layout so the grid never collapses to a single tall stack.
--}}
@php($lbLimit = (int) ($data['limit'] ?? 0))
@php($products = \App\Support\Blocks\BlockData::products(array_merge($data, ['limit' => $lbLimit > 0 ? $lbLimit : 12])))
@if ($products->isNotEmpty())
    @php($desktopCols = (int) ($data['columns'] ?? 4))
    @php($desktopCols = in_array($desktopCols, [3,4,5], true) ? $desktopCols : 4)
    @php($mobileCols  = (int) ($data['mobile_columns'] ?? 2))
    @php($mobileCols  = in_array($mobileCols, [2,3], true) ? $mobileCols : 2)
    @php($mobileClass  = $mobileCols === 3 ? 'columns-3' : 'columns-2')
    @php($tabletClass  = 'md:columns-3')
    @php($desktopClass = $desktopCols === 3 ? 'lg:columns-3' : ($desktopCols === 5 ? 'lg:columns-5' : 'lg:columns-4'))
    @php($containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-7xl' })
    @php($padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-6', 'lg' => 'py-20 sm:py-28', default => 'py-10 sm:py-12' })
    @php($lookbookGapClass = match ($data['gap'] ?? 'normal') { 'tight' => 'gap-px sm:gap-1', 'wide' => 'gap-3 sm:gap-6', default => 'gap-0.5 sm:gap-3' })
    <section class="mx-auto {{ $containerClass }} {{ $padClass }} px-1.5 sm:px-6">
        @if ($heading = ($data['heading'] ?? null))
            <div class="mb-6 px-3 text-center sm:mb-8 sm:px-0">
                <h2 class="reveal text-xl font-bold text-brand-900 sm:text-2xl">{{ $heading }}</h2>
                @if ($sub = ($data['subtitle'] ?? null))
                    <p class="reveal mt-2 text-sm text-brand-500">{{ $sub }}</p>
                @endif
            </div>
        @endif
        {{-- Pinterest rhythm: 2 px gap on phones for that edge-to-edge mosaic
             density, opens up to 12 px on tablet+. Browser-native CSS columns
             so heights stagger by the image's natural aspect ratio. --}}
        <div class="{{ $lookbookGapClass }} [column-fill:_balance] {{ $mobileClass }} {{ $tabletClass }} {{ $desktopClass }}">
            {{-- Force a staggered Pinterest rhythm: even if every product
                 photo has the same source aspect ratio, this cycle crops
                 each tile to a different shape so columns actually mismatch
                 in height instead of stacking neatly. --}}
            @php($aspectCycle = ['aspect-[3/4]', 'aspect-[4/5]', 'aspect-[1/1]', 'aspect-[2/3]', 'aspect-[3/4]'])
            @foreach ($products as $i => $product)
                @php($priceLabel = $product->formattedPrice())
                @php($hasPrice = $product->price && (int) $product->price > 0)
                @php($aspect = $aspectCycle[$i % count($aspectCycle)])
                <a href="{{ route('product.show', $product) }}"
                   class="reveal group relative mb-0.5 block break-inside-avoid overflow-hidden rounded-md bg-brand-50 transition active:scale-[0.99] sm:mb-3 sm:rounded-2xl sm:ring-1 sm:ring-brand-100 sm:hover:ring-accent-300">
                    @php($_secondaryImg = $product->images->skip(1)->first()?->path)
                    @php($_lbImg = $product->primary_image_url ?? '/placeholder?w=600&h='.(($i % 3) === 0 ? 800 : (($i % 3) === 1 ? 600 : 700)).'&label='.urlencode($product->name))
                    @php($_lbSrcset = \App\Support\ImageOptimizer::srcsetFor($_lbImg))
                    <div class="relative w-full {{ $aspect }} overflow-hidden">
                        <picture>
                            @if ($_lbSrcset)
                                <source type="image/webp" srcset="{{ $_lbSrcset }}" sizes="(min-width: 1024px) 25vw, 50vw">
                            @endif
                            <img src="{{ $_lbImg }}"
                                 alt="{{ $product->name }}"
                                 loading="lazy" decoding="async"
                                 class="absolute inset-0 h-full w-full object-cover transition duration-500 sm:group-hover:scale-105 {{ $_secondaryImg ? '[@media(hover:hover)]:group-hover:opacity-0' : '' }}">
                        </picture>
                        @if ($_secondaryImg)
                            @php($_lbSrcset2 = \App\Support\ImageOptimizer::srcsetFor($_secondaryImg))
                            <picture>
                                @if ($_lbSrcset2)
                                    <source type="image/webp" srcset="{{ $_lbSrcset2 }}" sizes="(min-width: 1024px) 25vw, 50vw">
                                @endif
                                <img src="{{ $_secondaryImg }}" alt="" aria-hidden="true" loading="lazy" decoding="async"
                                     class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500 pointer-events-none [@media(hover:hover)]:group-hover:opacity-100">
                            </picture>
                        @endif
                    </div>

                    {{-- Mobile: always-visible name pill in the corner (touch has
                         no hover state). Desktop: full gradient + name + price
                         that reveals on hover (kept hidden on phones via sm:). --}}
                    <span class="pointer-events-none absolute end-1.5 bottom-1.5 max-w-[80%] truncate rounded bg-black/55 px-1.5 py-0.5 text-[10px] font-medium text-white backdrop-blur-sm sm:hidden">{{ $product->name }}</span>

                    <div class="pointer-events-none absolute inset-x-0 bottom-0 hidden translate-y-2 bg-gradient-to-t from-black/70 via-black/30 to-transparent p-3 opacity-0 transition sm:block sm:group-hover:translate-y-0 sm:group-hover:opacity-100">
                        <p class="line-clamp-1 text-sm font-semibold text-white">{{ $product->name }}</p>
                        @if ($hasPrice)
                            <p class="mt-0.5 text-xs text-white/80 fa-num">{{ $priceLabel }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif

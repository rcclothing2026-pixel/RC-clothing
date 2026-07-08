{{--
    Collection Feature — editorial spread for ONE collection. Left/right
    split (stacks on mobile): a brand-signature diagonal-cut image on one
    side, an eyebrow + name + description + a row of product cards on the
    other, ending in a "view all" CTA. Renders nothing if the chosen
    collection slug doesn't resolve.
--}}
@php($slug = trim((string) ($data['collection'] ?? '')))
@if ($slug !== '' && ($collection = \App\Support\Blocks\BlockData::collection($slug)))
    @php($limit = max(2, min(4, (int) ($data['limit'] ?? 4))))
    @php($picked = array_values(array_filter(array_map('trim', explode(',', (string) ($data['products'] ?? ''))))))
    @php($items = $picked
        ? \App\Models\Product::active()->with(['images', 'variants'])->whereIn('slug', $picked)->get()
            ->sortBy(fn ($p) => array_search($p->slug, $picked, true))->values()->take($limit)
        : $collection->products()->where('is_active', true)->with(['images', 'variants'])->latest()->take($limit)->get())
    @php($imageSide = ($data['image_side'] ?? 'start') === 'end' ? 'end' : 'start')
    @php($tone = ($data['tone'] ?? 'red') === 'dark' ? 'dark' : 'red')
    @php($corner = ($data['corner'] ?? 'bottom') === 'top' ? 'top' : 'bottom')
    @php($eyebrow = trim((string) ($data['eyebrow'] ?? '')) ?: 'RACKET CLUB COLLECTION')
    @php($ctaText = trim((string) ($data['cta_text'] ?? '')) ?: 'View All')
    @php($showSummary = ($data['show_summary'] ?? 'no') === 'yes')
    @php($ratioMap = ['sm' => 'aspect-[4/5]', 'md' => 'aspect-[3/4]', 'lg' => 'aspect-[2/3]', 'square' => 'aspect-square', 'wide' => 'aspect-[4/3]'])
    @php($desktopRatio = $ratioMap[$data['image_size']        ?? 'md'] ?? 'aspect-[3/4]')
    @php($mobileRatio  = $ratioMap[$data['image_size_mobile'] ?? ($data['image_size'] ?? 'md')] ?? $desktopRatio)
    @php($imageRatio   = $mobileRatio.' lg:'.str_replace('aspect-', 'aspect-', $desktopRatio))
    {{-- Build a "mobile-aspect lg:desktop-aspect" string by prefixing the desktop class --}}
    @php($imageRatio = $mobileRatio.' '.str_replace(['aspect-[4/5]','aspect-[3/4]','aspect-[2/3]','aspect-square','aspect-[4/3]'], ['lg:aspect-[4/5]','lg:aspect-[3/4]','lg:aspect-[2/3]','lg:aspect-square','lg:aspect-[4/3]'], $desktopRatio))
    @php($containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-7xl' })
    @php($padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-6', 'lg' => 'py-20 sm:py-28', default => 'py-14 sm:py-20' })
    @php($collectionUrl = route('shop.index', ['collection' => $collection->slug]))
    @php($imageOverride = trim((string) ($data['image_override'] ?? '')))
    @php($imageSrc = $imageOverride ?: ($collection->image_path ?: ($items->first()?->primary_image_url) ?: '/placeholder?w=900&h=1100&label='.urlencode($collection->name)))

    <section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
        <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12">
            {{-- Image with the brand diagonal-cut treatment --}}
            <div class="reveal {{ $imageSide === 'end' ? 'lg:order-2' : '' }}">
                <x-diagonal-cut :src="$imageSrc" :alt="$collection->name"
                                :ratio="$imageRatio"
                                :tone="$tone" :corner="$corner" label="RACKET CLUB" />
            </div>

            {{-- Editorial copy + product strip --}}
            <div class="reveal {{ $imageSide === 'end' ? 'lg:order-1' : '' }}" style="transition-delay:120ms">
                <x-brand-heading :kicker="$eyebrow">{{ $collection->name }}</x-brand-heading>

                @if (filled($collection->description))
                    <p class="mt-4 max-w-md text-sm leading-8 text-brand-600">
                        {{ \Illuminate\Support\Str::limit(strip_tags($collection->description), 220) }}
                    </p>
                @endif

                @if ($items->isNotEmpty())
                    {{-- Inline product strip — 2 cols on mobile, $limit on desktop --}}
                    <div class="mt-6 grid grid-cols-2 gap-3
                        {{ $limit === 4 ? 'lg:grid-cols-4' : ($limit === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-2') }}">
                        @foreach ($items as $p)
                            @php($_secondaryImg = $p->images->skip(1)->first()?->path)
                            <a href="{{ route('product.show', $p) }}"
                               class="group block overflow-hidden rounded-xl bg-brand-50 ring-1 ring-brand-100 transition hover:ring-accent-300">
                                <div class="relative aspect-[3/4] overflow-hidden">
                                    <img src="{{ $p->primary_image_url ?? '/placeholder?w=300&h=400&label='.urlencode($p->name) }}"
                                         alt="{{ $p->name }}" loading="lazy"
                                         class="h-full w-full object-cover transition duration-500 group-hover:scale-105 {{ $_secondaryImg ? '[@media(hover:hover)]:group-hover:opacity-0' : '' }}">
                                    @if ($_secondaryImg)
                                        <img src="{{ $_secondaryImg }}" alt="" aria-hidden="true" loading="lazy"
                                             class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500 pointer-events-none [@media(hover:hover)]:group-hover:opacity-100">
                                    @endif
                                </div>
                                <div class="p-2.5">
                                    <p class="line-clamp-1 text-xs font-medium text-brand-800">{{ $p->name }}</p>
                                    @if ($p->price && (int) $p->price > 0)
                                        <p class="mt-0.5 text-[11px] font-bold text-accent-600 fa-num">{{ $p->formattedPrice() }}</p>
                                    @endif
                                    @if ($showSummary && filled($p->summary))
                                        <p class="mt-1 line-clamp-2 text-[11px] leading-5 text-brand-500">{{ \Illuminate\Support\Str::limit(strip_tags($p->summary), 60) }}</p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif

                <a href="{{ $collectionUrl }}"
                   class="mt-8 inline-flex items-center gap-1.5 rounded-full bg-brand-900 px-7 py-3 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[0.98]">
                    {{ $ctaText }}
                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            </div>
        </div>
    </section>
@endif

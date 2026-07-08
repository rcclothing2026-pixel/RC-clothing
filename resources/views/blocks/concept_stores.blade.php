{{--
    Concept Stores — editorial spread introducing the partner concept stores
    chiiaco works with. Each store is an alternating image/text section with a
    full write-up, Instagram, and optional website. Admin-managed via the
    `stores` repeater (name, tagline, city, description, image, image2,
    instagram, website).
--}}
@php
    $stores = array_values(array_filter((array) ($data['stores'] ?? []), function ($s) {
        return is_array($s) && (trim((string) ($s['name'] ?? '')) !== '' || trim((string) ($s['image'] ?? '')) !== '');
    }));
@endphp
@if ($stores)
    @php($containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-6xl' })
    @php($padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-8', 'lg' => 'py-24 sm:py-32', default => 'py-16 sm:py-24' })
    <section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
        @if (($h = trim((string) ($data['heading'] ?? ''))) !== '' || ($sub = trim((string) ($data['subtitle'] ?? ''))) !== '')
            <div class="reveal mx-auto mb-14 max-w-2xl text-center sm:mb-20">
                @if ($h !== '')
                    <h2 class="text-3xl font-bold tracking-tight text-brand-900 sm:text-4xl">{{ $h }}</h2>
                @endif
                @if (($sub = trim((string) ($data['subtitle'] ?? ''))) !== '')
                    <p class="mt-4 text-sm leading-8 text-brand-500 sm:text-base">{{ $sub }}</p>
                @endif
            </div>
        @endif

        <div class="space-y-16 sm:space-y-28">
            @foreach ($stores as $i => $s)
                @php($name = trim((string) ($s['name'] ?? '')))
                @php($tagline = trim((string) ($s['tagline'] ?? '')))
                @php($city = trim((string) ($s['city'] ?? '')))
                @php($img = trim((string) ($s['image'] ?? '')))
                @php($img2 = trim((string) ($s['image2'] ?? '')))
                @php($desc = (string) ($s['description'] ?? ''))
                @php($ig = trim((string) ($s['instagram'] ?? '')))
                @php($igUrl = $ig === '' ? '' : (str_starts_with($ig, 'http') ? $ig : 'https://instagram.com/'.ltrim($ig, '@/')))
                @php($igHandle = $ig === '' ? '' : '@'.ltrim(preg_replace('~^https?://(www\.)?instagram\.com/~i', '', $ig), '@/'))
                @php($site = trim((string) ($s['website'] ?? '')))
                @php($srcset = $img !== '' ? \App\Support\ImageOptimizer::srcsetFor($img) : null)

                <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-14">
                    {{-- Image side (alternates) --}}
                    <div class="reveal {{ $i % 2 ? 'lg:order-2' : '' }}">
                        <div class="relative overflow-hidden rounded-3xl bg-brand-50 ring-1 ring-brand-100">
                            @if ($img !== '')
                                <div class="aspect-[4/5] w-full sm:aspect-[3/2] lg:aspect-[4/5]">
                                    <picture>
                                        @if ($srcset)<source type="image/webp" srcset="{{ $srcset }}" sizes="(min-width:1024px) 50vw, 100vw">@endif
                                        <img src="{{ $img }}" alt="{{ $name }}" loading="lazy" decoding="async"
                                             class="h-full w-full object-cover">
                                    </picture>
                                </div>
                            @else
                                <div class="grid aspect-[4/5] w-full place-items-center text-brand-300">{{ $name }}</div>
                            @endif
                            @if ($img2 !== '')
                                <img src="{{ $img2 }}" alt="" aria-hidden="true" loading="lazy"
                                     class="absolute bottom-4 {{ $i % 2 ? 'start-4' : 'end-4' }} hidden h-28 w-24 rounded-xl object-cover shadow-xl ring-2 ring-white sm:block">
                            @endif
                        </div>
                    </div>

                    {{-- Text side --}}
                    <div class="reveal {{ $i % 2 ? 'lg:order-1' : '' }}">
                        @if ($city !== '')
                            <p class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-accent-600">{{ $city }}</p>
                        @endif
                        <h3 class="text-2xl font-bold tracking-tight text-brand-900 sm:text-3xl">{{ $name }}</h3>
                        @if ($tagline !== '')
                            <p class="mt-2 text-base text-brand-500">{{ $tagline }}</p>
                        @endif
                        @if (trim(strip_tags($desc)) !== '')
                            <div class="prose prose-sm mt-5 max-w-none leading-8 text-brand-700 [&_a]:text-accent-600">{!! $desc !!}</div>
                        @endif

                        @if ($igUrl !== '' || $site !== '')
                            <div class="mt-7 flex flex-wrap items-center gap-3">
                                @if ($igUrl !== '')
                                    <a href="{{ $igUrl }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-2 rounded-full bg-brand-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
                                        {{ $igHandle }}
                                    </a>
                                @endif
                                @if ($site !== '')
                                    <a href="{{ str_starts_with($site, 'http') ? $site : 'https://'.$site }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center gap-1.5 rounded-full px-4 py-2.5 text-sm font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50">
                                        وب‌سایت
                                        <svg class="h-3.5 w-3.5 -scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

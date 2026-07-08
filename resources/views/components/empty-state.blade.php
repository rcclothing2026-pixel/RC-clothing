{{--
    Empty state — used when a list, cart, wishlist, orders, or search returns
    nothing. Wraps the page's «no results» message in a brand-styled panel
    with the Gate pattern overlay, a glyph from the icon set, a heading, a
    soft caption, and one CTA.

    Props:
      icon : 'cart' | 'heart' | 'box' | 'search' | 'pattern' (default 'pattern')
      title : main line (Persian)
      caption : softer support copy (optional)
      cta : ['label' => string, 'href' => url]   (optional)
    Slot: anything extra below the CTA (e.g. recommended products grid).
--}}
@props([
    'icon' => 'pattern',
    'title' => '',
    'caption' => null,
    'cta' => null,
])
<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-card bg-white p-10 text-center ring-1 ring-brand-100 sm:p-14']) }}>
    {{-- Faint Gate pattern as the brand-anchored background --}}
    <x-brand-pattern class="pointer-events-none absolute inset-0 h-full w-full text-brand-900" :opacity="'0.05'" />

    <div class="relative">
        {{-- Glyph in a soft Chiaco-light circle --}}
        <div class="mx-auto mb-5 grid h-20 w-20 place-items-center rounded-full bg-brand-200/60 text-brand-900">
            @switch($icon)
                @case('cart')
                    <svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6 5 3H3"/></svg>
                    @break
                @case('heart')
                    <svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-9.5-8.5C.5 9 2 5.5 5.5 5.5 7.5 5.5 9 7 12 9c3-2 4.5-3.5 6.5-3.5 3.5 0 5 3.5 3 7C19 16.5 12 21 12 21z"/></svg>
                    @break
                @case('box')
                    <svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><path d="M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4V11M21 7v10l-9 4"/></svg>
                    @break
                @case('search')
                    <svg class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="1.4" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    @break
                @default
                    <x-brand-sign class="h-10 w-auto text-brand-900" />
            @endswitch
        </div>

        @if ($title)
            <h2 class="text-lg font-bold text-brand-900 sm:text-xl">{{ $title }}</h2>
        @endif
        @if ($caption)
            <p class="mx-auto mt-2 max-w-sm text-sm leading-7 text-brand-500">{{ $caption }}</p>
        @endif
        @if ($cta && is_array($cta) && ! empty($cta['href']))
            <a href="{{ $cta['href'] }}" class="mt-6 inline-block rounded-full bg-brand-900 px-7 py-3 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[0.98]">
                {{ $cta['label'] ?? 'ادامه' }}
            </a>
        @endif

        {{ $slot }}
    </div>
</div>

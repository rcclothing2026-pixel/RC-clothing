{{-- Renders a product's up-to-4 corner badges over its image. Shared by every
     product card style and the product page, so badges appear everywhere. Each
     badge is text (styled: colour, size, weight, radius, rotation) or an image,
     optionally a link. Stacks WITH the automatic discount/out-of-stock badge:
     when that auto badge occupies the top-start corner, a custom top-start
     badge drops below it. Non-link badges are click-through (pointer-events
     none) so they never block the product link. --}}
@props(['product'])
@php($badges = $product->badgeList())
@if ($badges)
    @php($autoTs = $product->hasDiscount() || ! $product->inStock())
    @foreach ($badges as $corner => $b)
        @php($pos = match ($corner) {
            'ts' => ($autoTs ? 'top-9' : 'top-2').' start-2',
            'te' => 'top-2 end-2',
            'bs' => 'bottom-2 start-2',
            'be' => 'bottom-2 end-2',
            default => 'top-2 start-2',
        })
        @php($isImg = ($b['type'] ?? 'text') === 'image')
        @php($sizeCls = $isImg
            ? match ($b['size'] ?? 'md') { 'sm' => 'h-8', 'lg' => 'h-14', default => 'h-11' }
            : match ($b['size'] ?? 'md') { 'sm' => 'px-1.5 py-0.5 text-[9px]', 'lg' => 'px-2.5 py-1.5 text-sm', default => 'px-2 py-1 text-[11px]' })
        @php($radiusCls = match ($b['radius'] ?? 'rounded') { 'pill' => 'rounded-full', 'square' => 'rounded-none', default => 'rounded-md' })
        @php($link = trim((string) ($b['link'] ?? '')))
        @php($tag = $link !== '' ? 'a' : 'span')
        <{{ $tag }} @if ($link !== '') href="{{ $link }}" onclick="event.stopPropagation()" @endif
            class="absolute {{ $pos }} z-[2] inline-block {{ $link !== '' ? 'pointer-events-auto' : 'pointer-events-none' }}"
            style="transform: rotate({{ (int) ($b['rotate'] ?? 0) }}deg); transform-origin: center;">
            @if ($isImg)
                <img src="{{ $b['image'] }}" alt="" class="{{ $sizeCls }} w-auto object-contain drop-shadow">
            @else
                <span class="inline-block whitespace-nowrap leading-none shadow-sm {{ $sizeCls }} {{ $radiusCls }} {{ ($b['weight'] ?? '') === 'bold' ? 'font-bold' : 'font-medium' }}"
                      style="background:{{ ($b['bg_color'] ?? '') !== '' ? $b['bg_color'] : '#282828' }};color:{{ ($b['text_color'] ?? '') !== '' ? $b['text_color'] : '#ffffff' }};">{{ $b['text'] }}</span>
            @endif
        </{{ $tag }}>
    @endforeach
@endif

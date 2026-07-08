{{--
    Chiaco editorial photo treatment (BrandBook v01): an image with the brand's
    signature diagonal Persian-Red cut across one corner, optionally carrying the
    wordmark / tagline. Used on heroes, lookbook and category banners.

    Props:
      src     : image URL (optional — omit to use the default slot/placeholder)
      alt     : alt text
      ratio   : aspect-ratio utility (default aspect-[4/5])
      label   : text shown on the red cut (e.g. 'CHIACO'); omit for none
      corner  : 'bottom' (default) | 'top' — which side the cut anchors to
      tone    : 'red' (default) | 'dark' — colour of the diagonal cut
      class   : extra classes on the wrapper
--}}
@props([
    'src' => null,
    'alt' => '',
    'ratio' => 'aspect-[4/5]',
    'label' => null,
    'corner' => 'bottom',
    'tone' => 'red',
    'class' => '',
])
@php
    $cutColor = $tone === 'dark' ? 'bg-brand-900' : 'bg-accent-600';
    // Polygon clip: a triangle hugging the chosen corner (RTL-agnostic).
    $clip = $corner === 'top'
        ? 'polygon(0 0, 100% 0, 0 62%)'
        : 'polygon(0 100%, 100% 100%, 100% 38%)';
    $labelPos = $corner === 'top' ? 'top-5 start-5' : 'bottom-5 end-5';
@endphp
<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-card '.$ratio.' '.$class]) }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy"
             class="h-full w-full object-cover">
    @else
        {{ $slot }}
    @endif

    {{-- Diagonal brand cut --}}
    <div class="pointer-events-none absolute inset-0 {{ $cutColor }} opacity-90 mix-blend-normal"
         style="clip-path: {{ $clip }};"></div>

    @if ($label)
        <div class="absolute {{ $labelPos }} z-[1]">
            <span class="text-lg font-bold uppercase tracking-[0.18em] text-white">{{ $label }}</span>
        </div>
    @endif
</div>

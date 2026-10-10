{{--
    Racket Club logo lockup: the square brand mark + the "RACKET CLUB" wordmark
    set in Mansory (the brand display face). The wordmark inherits colour from
    `currentColor` (set via the `class` prop) so it works on light and dark
    surfaces; the mark is a fixed two-tone SVG badge whose variant is chosen to
    contrast with the surface.

    Props (kept compatible with previous callers):
      sign  : show the square brand mark before the wordmark
      mark  : mark variant — 'navy' (default, for light surfaces) | 'cream' | 'green'
      latin : (legacy, ignored — the wordmark is always the Latin RACKET CLUB)
      class : colour utility (sets currentColor for the wordmark)
      h     : lockup / mark height (Tailwind height class, default h-6)
--}}
@props(['sign' => true, 'mark' => 'navy', 'latin' => false, 'class' => 'text-brand-900', 'h' => 'h-6'])
@php
    $markSrc = in_array($mark, ['navy', 'cream', 'green'], true) ? "/brand/mark-{$mark}.svg" : '/brand/mark-navy.svg';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 '.$class]) }}>
    @if ($sign)
        <img src="{{ $markSrc }}" alt="" aria-hidden="true" class="{{ $h }} w-auto shrink-0 rounded-[3px]">
    @endif
    <span class="font-display text-[1.05em] font-bold uppercase leading-none tracking-[0.12em]" role="img" aria-label="Racket Club">Racket&nbsp;Club</span>
</span>

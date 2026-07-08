{{--
    Racket Club mark — the square block monogram (two bars + a dot forming the
    "R"), redrawn on a transparent ground so it inherits colour via currentColor
    and tints to any surface. For the full two-tone badge use <x-brand-logo> or
    the /brand/mark-*.svg files directly.
    Props: class (sizing/colour utilities).
--}}
@props(['class' => 'h-7 w-auto'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="1360 1360 280 280"
     fill="currentColor" role="img" aria-label="Racket Club" xmlns="http://www.w3.org/2000/svg">
    <rect x="1472" y="1500" width="56" height="141.13"/>
    <rect x="1472.07" y="1386.88" width="56" height="280" transform="translate(-26.8095 3026.9558) rotate(-90)"/>
    <circle cx="1567.41" cy="1446.49" r="14.38"/>
</svg>

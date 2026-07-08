{{--
    Chiaco brand tagline "CHANGE · IN · AESTHETICS" (BrandBook v01).
    Two variants:
      - inline (default): horizontal wide-tracked lockup with bullseye separators
      - badge: circular stamp with the tagline wrapping a centred Gate sign
    Inherits colour via currentColor so it sits on light or dark surfaces.

    Props:
      variant : 'inline' | 'badge'
      sign    : (badge only) show the Gate sign in the centre
      class   : sizing / colour utilities
--}}
@props(['variant' => 'inline', 'sign' => true, 'class' => ''])

@if ($variant === 'badge')
    {{-- REAL circular tagline stamp (Asset 57), recolorable via currentColor --}}
    <span {{ $attributes->merge(['class' => 'relative inline-grid place-items-center '.($class ?: 'h-32 w-32')]) }}
          role="img" aria-label="Change in Aesthetics — چیاکو">
        <span class="absolute inset-0" aria-hidden="true"
              style="background: currentColor;
                     -webkit-mask: url('/img/brand/chiaco-tagline-badge.svg') no-repeat center / contain;
                     mask: url('/img/brand/chiaco-tagline-badge.svg') no-repeat center / contain;"></span>
        @if ($sign)
            <x-brand-sign class="relative h-2/5 w-2/5" />
        @endif
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.25em] '.$class]) }}>
        <span>CHANGE</span>
        <span class="text-accent-600" aria-hidden="true">·</span>
        <span>IN</span>
        <span class="text-accent-600" aria-hidden="true">·</span>
        <span>AESTHETICS</span>
    </span>
@endif

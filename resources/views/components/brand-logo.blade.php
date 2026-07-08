{{--
    Chiaco logo lockup: optional Gate sign + the REAL logotype extracted from the
    brand book (Asset 2). Defaults to the Persian چیاکو wordmark; pass :latin=true
    for the CHIACO wordmark. The wordmark is rendered via CSS mask so it inherits
    colour from `currentColor` (works on light and dark surfaces).

    Props:
      sign  : show the Gate sign before the wordmark
      latin : use the CHIACO wordmark instead of Persian چیاکو
      class : colour utility (sets currentColor for both sign and wordmark)
      h     : wordmark/sign height (Tailwind height class, default h-6)
--}}
@props(['sign' => true, 'latin' => false, 'class' => 'text-brand-900', 'h' => 'h-6'])
@php
    $src   = $latin ? '/img/brand/chiaco-logo-en.svg' : '/img/brand/chiaco-logo-fa.svg';
    $ratio = $latin ? 4.91 : 2.97;
    $label = $latin ? 'CHIACO' : 'چیاکو';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 '.$class]) }}>
    @if ($sign)
        <x-brand-sign class="{{ $h }} w-auto" />
    @endif
    <span class="{{ $h }} block" role="img" aria-label="{{ $label }}"
          style="aspect-ratio: {{ $ratio }}; background: currentColor;
                 -webkit-mask: url('{{ $src }}') no-repeat center / contain;
                 mask: url('{{ $src }}') no-repeat center / contain;"></span>
</span>

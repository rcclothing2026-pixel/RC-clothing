{{--
    Chiaco display typography (BrandBook v01 "CHIACO TYPE DESIGN").
    A creative, font-free emulation of the custom monoline display type: uppercase,
    geometric, wide-tracked — with every "O"/"0" swapped for the ⊙ bullseye
    (the wordmark signature, e.g. ⊙NLINE SH⊙PPING, CHIAC⊙). Words stay intact and
    wrap as units, so it is phone-first safe. Swap the CSS font stack for the real
    Chiaco webfont later with zero markup changes.

    Props:
      text : the display string (Latin) — required
      tag  : wrapper element (default span; use 'h1'/'h2' for headings)
      class: sizing / colour utilities
--}}
@props(['text' => '', 'tag' => 'span', 'class' => ''])
@php
    $words = preg_split('/\s+/u', mb_strtoupper(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
@endphp
<{{ $tag }} {{ $attributes->merge(['class' => 'type-chiaco '.$class]) }}>
    <span class="sr-only">{{ $text }}</span>
    <span aria-hidden="true" class="contents">
        @foreach ($words as $word)
            <span class="type-chiaco-word">
                @foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch)
                    @if (in_array($ch, ['O', '0', '⊙'], true))
                        <x-brand-dot class="type-chiaco-o" />
                    @else
                        <span>{{ $ch }}</span>
                    @endif
                @endforeach
            </span>
        @endforeach
    </span>
</{{ $tag }}>

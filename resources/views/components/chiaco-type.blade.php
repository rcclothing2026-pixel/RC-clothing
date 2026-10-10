{{--
    Racket Club display type — the brand wordmark/headline set in Mansory
    (the client display face): uppercase, wide-tracked, confident. Replaces the
    old font-free "CHIACO" emulation; same prop surface so callers don't change.

    Props:
      text : the display string (Latin) — required
      tag  : wrapper element (default span; use 'h1'/'h2' for headings)
      class: sizing / colour utilities
--}}
@props(['text' => '', 'tag' => 'span', 'class' => ''])
<{{ $tag }} {{ $attributes->merge(['class' => 'font-display font-bold uppercase tracking-[0.12em] leading-[1.05] '.$class]) }}>{{ $text }}</{{ $tag }}>

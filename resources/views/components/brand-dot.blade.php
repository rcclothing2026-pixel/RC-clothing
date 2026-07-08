{{--
    Chiaco bullseye ⊙ — the target dot from the CHIAC⊙ wordmark and the
    CHANGE·IN·AESTHETICS separators. A recurring micro-accent: list markers,
    kickers, dividers. Inherits colour via currentColor.
    Props: class (sizing/colour).
--}}
@props(['class' => 'h-3 w-3'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none"
     aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    <circle cx="12" cy="12" r="11" fill="currentColor"/>
    <circle cx="12" cy="12" r="4" fill="#fff"/>
</svg>

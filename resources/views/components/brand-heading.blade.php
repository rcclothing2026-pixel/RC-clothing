{{--
    Chiaco section heading (BrandBook v01): an uppercase wide-tracked kicker with
    the bullseye ⊙ accent (the dot from the CHIAC⊙ wordmark), above a bold title.
    Encapsulates the editorial heading rhythm used across the storefront.

    Props:
      kicker : small label above the title (optional)
      level  : 'h1' | 'h2' (default h2)
      align  : 'start' (default) | 'center'
      class  : extra classes on the title
    Slot: the heading text.
--}}
@props(['kicker' => null, 'level' => 'h2', 'align' => 'start', 'class' => ''])
@php
    $alignWrap = $align === 'center' ? 'text-center items-center' : 'text-start items-start';
    $titleSize = $level === 'h1' ? 'text-4xl sm:text-5xl md:text-6xl' : 'text-3xl sm:text-4xl';
@endphp
<div class="flex flex-col {{ $alignWrap }}">
    @if ($kicker)
        <span class="mb-2 inline-flex items-center gap-1.5 font-display text-xs font-semibold uppercase tracking-[0.22em] text-brand-400">
            <x-brand-dot class="h-2.5 w-2.5 text-accent-600" />
            {{ $kicker }}
        </span>
    @endif
    <{{ $level }} {{ $attributes->merge(['class' => 'font-bold leading-[1.1] text-brand-900 '.$titleSize.' '.$class]) }}>
        {{ $slot }}
    </{{ $level }}>
</div>

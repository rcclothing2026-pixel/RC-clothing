{{--
    Racket Club awning stripe — secondary (horizontal) variant. Same 66-unit
    equal-bar rhythm as <x-brand-pattern>, rotated 90° for banded headers so the
    two patterns read as one system without being identical. Single-colour bar
    over transparent ground; tints via currentColor (or the color prop).
    Props: class, color, opacity.
--}}
@props(['class' => '', 'color' => 'currentColor', 'opacity' => '0.06'])
@php($pid = 'rc-stripe-h-'.\Illuminate\Support\Str::random(6))
<svg {{ $attributes->merge(['class' => $class]) }} aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <pattern id="{{ $pid }}" width="66" height="66" patternUnits="userSpaceOnUse">
            <rect width="66" height="33" fill="{{ $color }}" opacity="{{ $opacity }}"/>
        </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#{{ $pid }})"/>
</svg>

{{--
    Racket Club awning stripe (Design System 1: assets/crest/racket-club-stripe).
    The signature club stripe — 66-unit repeat, equal 33/33 bars — drawn as a
    single-colour bar over a transparent ground so it tints via currentColor (or
    the color prop) and layers over any surface at low opacity, exactly like the
    pattern it replaces.
    Props: class, color, opacity. (stroke accepted for back-compat, unused.)
--}}
@props(['class' => '', 'color' => 'currentColor', 'opacity' => '0.5', 'stroke' => null])
@php($pid = 'rc-stripe-'.\Illuminate\Support\Str::random(6))
<svg {{ $attributes->merge(['class' => $class]) }} aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <pattern id="{{ $pid }}" width="66" height="66" patternUnits="userSpaceOnUse">
            <rect width="33" height="66" fill="{{ $color }}" opacity="{{ $opacity }}"/>
        </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#{{ $pid }})"/>
</svg>

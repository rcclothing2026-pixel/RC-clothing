{{--
    Chiaco secondary pattern (BrandBook v01): the quatrefoil / Iranian-geometric
    tile — four-petal motifs linked on a grid. Seamless and extendable; subtle by
    default. Inherits colour via currentColor. Unique id per instance.
    Props: class, color, opacity.
--}}
@props(['class' => '', 'color' => 'currentColor', 'opacity' => '0.06'])
@php($pid = 'chiaco-pat2-'.\Illuminate\Support\Str::random(6))
<svg {{ $attributes->merge(['class' => $class]) }} aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <pattern id="{{ $pid }}" width="80" height="80" patternUnits="userSpaceOnUse">
            <g fill="{{ $color }}" opacity="{{ $opacity }}">
                {{-- Quatrefoil at each corner (tiles seamlessly across the grid) --}}
                @foreach ([[0,0],[80,0],[0,80],[80,80],[40,40]] as [$cx, $cy])
                    <path transform="translate({{ $cx }},{{ $cy }})"
                          d="M0,-9 a9,9 0 0 1 9,9 a9,9 0 0 1 9,-9 a9,9 0 0 1 -9,9 a9,9 0 0 1 9,9
                             a9,9 0 0 1 -9,-9 a9,9 0 0 1 -9,9 a9,9 0 0 1 9,-9 a9,9 0 0 1 -9,-9
                             a9,9 0 0 1 9,9 a9,9 0 0 1 -9,-9 Z"/>
                @endforeach
                {{-- Connecting bars between motifs --}}
                <rect x="18" y="-2" width="4" height="4"/>
                <rect x="58" y="-2" width="4" height="4"/>
                <rect x="-2" y="18" width="4" height="4"/>
                <rect x="-2" y="58" width="4" height="4"/>
            </g>
        </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#{{ $pid }})"/>
</svg>

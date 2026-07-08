{{--
    Chiaco 3D extruded Gate sign (BrandBook v01): the logo sign rendered as an
    isometric tri-tone block in the official palette (Chiaco Light front, Persian
    Red top, Chiaco Dark side). A decorative brand accent for heroes / brand story.
    Props: class (sizing).
--}}
@props(['class' => 'h-40 w-auto'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="-4 -22 158 108"
     role="img" aria-label="نشان چیاکو" xmlns="http://www.w3.org/2000/svg">
    {{-- Back silhouette (extrusion depth) --}}
    <path transform="translate(26,-15)"
          d="M0,80 L0,32 L40,32 L40,0 L80,0 L80,32 L120,32 L120,80 L84,80 A24,24 0 0 0 36,80 Z"
          fill="#282828"/>

    {{-- Top faces (Persian Red) --}}
    <polygon points="0,32 40,32 66,17 26,17"   fill="#cc3333"/>
    <polygon points="40,0 80,0 106,-15 66,-15" fill="#cc3333"/>
    <polygon points="80,32 120,32 146,17 106,17" fill="#cc3333"/>

    {{-- Right face (Chiaco Dark) --}}
    <polygon points="120,32 120,80 146,65 146,17" fill="#282828"/>

    {{-- Front face (Chiaco Light) --}}
    <path d="M0,80 L0,32 L40,32 L40,0 L80,0 L80,32 L120,32 L120,80 L84,80 A24,24 0 0 0 36,80 Z"
          fill="#e0d5d9"/>
</svg>

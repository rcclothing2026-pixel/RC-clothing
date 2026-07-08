{{--
    Racket Club crest card (Design System 1: assets/crest/racket-club-card).
    The bordered club card — corner-cut double frame, RACKET CLUB, the RC
    monogram flanked by EST 2025 / TEHRAN, and the Pinyon slogan. Inlined SVG
    (not <img>) so it uses the page's loaded webfonts. Geometry is 1:1 from the
    design-system card, cropped to the card itself.
    Props: class (sizing utilities).
--}}
@props(['class' => 'w-64'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="190 154 444 292" role="img"
     aria-label="Racket Club — Legends &amp; Legacy" xmlns="http://www.w3.org/2000/svg">
    <path d="M206.5,157.5 L617.5,157.5 L630.5,170.5 L630.5,429.5 L617.5,442.5 L206.5,442.5 L193.5,429.5 L193.5,170.5 Z" fill="#18234F"/>
    <path d="M207.5,159.5 L616.5,159.5 L628.5,171.5 L628.5,428.5 L616.5,440.5 L207.5,440.5 L195.5,428.5 L195.5,171.5 Z" fill="#ECE7D0"/>
    <g stroke="#18234F" stroke-width="1.5">
        <line x1="255.5" y1="191.5" x2="568.5" y2="191.5"/>
        <line x1="255.5" y1="408.5" x2="568.5" y2="408.5"/>
        <line x1="239.5" y1="207.5" x2="239.5" y2="392.5"/>
        <line x1="584.5" y1="207.5" x2="584.5" y2="392.5"/>
    </g>
    <g fill="#18234F" font-family="Mansory, Georgia, serif">
        <text x="412" y="219.5" text-anchor="middle" font-weight="600" font-size="15" letter-spacing="2.7">RACKET CLUB</text>
        <text x="294" y="293.5" text-anchor="middle" font-weight="500" font-size="10" letter-spacing="2">EST</text>
        <text x="294" y="308.5" text-anchor="middle" font-weight="500" font-size="10" letter-spacing="2">2025</text>
        <text x="412" y="327.5" text-anchor="middle" font-weight="600" font-size="84" letter-spacing="1.7">RC</text>
        <text x="532" y="301.5" text-anchor="middle" font-weight="500" font-size="10" letter-spacing="2">TEHRAN</text>
        <text x="412" y="391.5" text-anchor="middle" font-family="'Pinyon Script', cursive" font-size="34">Legends <tspan fill="#A72F23">&amp;</tspan> Legacy</text>
    </g>
</svg>

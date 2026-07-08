{{--
    Editorial Hero / Banner — multi-slide rastah-style block.

    A single block can host one slide (static hero/banner) or many slides
    (auto-rotating slider). Each slide carries its own image (desktop +
    optional mobile), kicker / title / subtitle / CTA / optional countdown,
    plus independently-pickable text colour, title size, and 9-position
    overlay placement *per breakpoint* (desktop vs mobile).

    Height is a block-level setting — five presets from a thin promo
    "strip" (20vh) to a full-bleed "tall" hero (90vh). Slide transitions
    are Alpine-driven crossfades; manual arrows + swipe; pauses on hover.
--}}
@php
    // -------- Block-level fields --------
    // tall = true full viewport (100dvh handles mobile browser chrome so the
    // address bar collapse doesn't reveal a sliver of the next block).
    // natural = no clamp; the section's height is driven by the image's
    // intrinsic aspect ratio (the <img> renders as a normal block element
    // rather than absolute-positioned cover).
    $heightMode = $data['height'] ?? 'tall';
    $isNatural = $heightMode === 'natural';
    $isCustom = $heightMode === 'custom';
    $customVh = max(10, min(120, (int) ($data['height_custom'] ?? 55)));
    $heightClass = match ($heightMode) {
        'natural', 'custom' => '',
        'strip'   => 'min-h-[20vh]',
        'short'   => 'min-h-[40vh]',
        'banner'  => 'min-h-[55vh]',
        'med'     => 'min-h-[75vh]',
        default   => 'min-h-screen min-h-dvh',
    };
    // Custom height → inline min-height in vh (no Tailwind purge risk).
    $heightStyle = $isCustom ? "min-height: {$customVh}vh;" : '';
    // Image positioning + fit: natural lays out as block flow (drives the
    // section's height), every other mode covers absolutely from inset-0.
    // Slides are always absolute-positioned (the crossfade slider needs it).
    // For «natural» height, an in-flow sizer <img> (below) gives the SECTION
    // its height from the first image; these cover-fill that box exactly, so
    // there's no crop when the box aspect == the image aspect.
    $imgClassMobile  = 'absolute inset-0 h-full w-full object-cover md:hidden';
    $imgClassDesktop = 'absolute inset-0 hidden h-full w-full object-cover md:block';
    $overlayClass = match ($data['overlay'] ?? 'soft') {
        'none'   => '',
        'medium' => 'bg-brand-950/35',
        'strong' => 'bg-brand-950/50',
        default  => 'bg-brand-950/20',
    };
    $intervalSec = max(0, (int) ($data['interval'] ?? 6));

    // -------- Slides --------
    // Back-compat: if there are no slides but the legacy flat fields
    // (image, title, …) are set on the block, fold them into a single
    // synthetic slide so existing saved blocks keep rendering. Triggers
    // on EITHER image or title set — so a title-only legacy block still
    // produces a visible banner instead of disappearing.
    $slides = $data['slides'] ?? [];
    if ((! is_array($slides) || ! count($slides)) && (! empty($data['image']) || ! empty($data['title']))) {
        $slides = [[
            'image' => $data['image'] ?? '',
            'kicker' => $data['kicker'] ?? '',
            'title' => $data['title'] ?? '',
            'subtitle' => $data['subtitle'] ?? '',
            'cta_text' => $data['cta_text'] ?? '',
            'cta_link' => $data['cta_link'] ?? '',
            'ends_at' => $data['ends_at'] ?? '',
            'text_color' => $data['text_color'] ?? 'white',
            'position_desktop' => $data['text_position'] ?? 'mc',
            'position_mobile' => $data['text_position'] ?? 'mc',
            'title_size' => 'xl',
        ]];
    }
    $slides = array_values(array_filter(
        is_array($slides) ? $slides : [],
        fn ($s) => is_array($s) && (trim((string) ($s['image'] ?? '')) !== '' || trim((string) ($s['title'] ?? '')) !== '')
    ));

    // "natural" height sizer sources — the first slide's images. Computed here (in
    // the block @php) to avoid an inline @php() with nested parens in the markup.
    $sizerD = trim((string) ($slides[0]['image'] ?? ''));
    $sizerM = trim((string) ($slides[0]['image_mobile'] ?? '')) ?: $sizerD;

    // Position presets → flex alignment + per-breakpoint utility
    $posMap = [
        'tl' => 'items-start justify-start text-start',
        'tc' => 'items-start justify-center text-center',
        'tr' => 'items-start justify-end text-end',
        'ml' => 'items-center justify-start text-start',
        'mc' => 'items-center justify-center text-center',
        'mr' => 'items-center justify-end text-end',
        'bl' => 'items-end justify-start text-start',
        'bc' => 'items-end justify-center text-center',
        'br' => 'items-end justify-end text-end',
    ];

    // Title size presets — fluid across breakpoints.
    $titleSizeMap = [
        'sm' => 'text-2xl sm:text-3xl md:text-4xl',
        'md' => 'text-3xl sm:text-4xl md:text-5xl',
        'lg' => 'text-4xl sm:text-5xl md:text-6xl',
        'xl' => 'text-4xl sm:text-6xl md:text-7xl lg:text-[88px]',
    ];

    // -------- R-17: full-bleed background + floating PNG overlay --------
    $bgColor   = trim((string) ($data['bg_color'] ?? ''));
    $bgImage   = trim((string) ($data['bg_image'] ?? ''));
    $bgOpacity = max(0, min(100, (int) ($data['bg_opacity'] ?? 100)));
    $sectionStyle = $bgColor !== '' ? 'background-color: '.$bgColor.';' : '';

    $overlayPng    = trim((string) ($data['overlay_png'] ?? ''));
    $overlayW      = max(1, min(100, (int) ($data['overlay_width'] ?? 25)));
    // Position unit: '%' (0–100, scales) or 'px' (exact, ≥0). Clamp only for %.
    $ovu           = (($data['overlay_unit'] ?? '%') === 'px') ? 'px' : '%';
    $ovClamp       = fn ($v) => $ovu === 'px' ? max(0, (int) $v) : max(0, min(100, (int) $v));
    $ovxD          = $ovClamp($data['overlay_x_desktop'] ?? 50);
    $ovyD          = $ovClamp($data['overlay_y_desktop'] ?? 50);
    $ovxM          = $ovClamp($data['overlay_x_mobile']  ?? $ovxD);
    $ovyM          = $ovClamp($data['overlay_y_mobile']  ?? $ovyD);
@endphp

@if (! empty($slides))
    <section data-editorial-hero
             class="relative w-full overflow-hidden bg-brand-900 {{ $heightClass }}"
             style="{{ $sectionStyle }} {{ $heightStyle }}"
             x-data="{
                 i: 0,
                 count: {{ count($slides) }},
                 timer: null,
                 _tx: 0,
                 start() { if (this.count > 1 && {{ $intervalSec }} > 0) this.timer = setInterval(() => this.next(), {{ $intervalSec * 1000 }}) },
                 stop()  { clearInterval(this.timer); this.timer = null },
                 next()  { this.i = (this.i + 1) % this.count },
                 prev()  { this.i = (this.i - 1 + this.count) % this.count },
                 touchStart(e) { this._tx = e.changedTouches[0].clientX },
                 touchEnd(e)   { const d = e.changedTouches[0].clientX - this._tx; if (Math.abs(d) > 40) (d > 0 ? this.prev : this.next).call(this) },
             }"
             x-init="start()"
             @mouseenter="stop()" @mouseleave="start()"
             @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">

        {{-- "natural" height sizer. Every slide is absolute-positioned
             for the crossfade, so with no min-height the section would collapse
             to 0. This invisible in-flow image is the ONLY flow child, so it
             gives the section the first slide's natural height; the absolute
             slides then cover-fill that box exactly. Desktop/mobile variants so
             the height matches whichever image is shown. --}}
        @if ($isNatural && $sizerD !== '')
            <img src="{{ $sizerM }}" alt="" aria-hidden="true" class="invisible block h-auto w-full md:hidden">
            <img src="{{ $sizerD }}" alt="" aria-hidden="true" class="invisible hidden h-auto w-full md:block">
        @endif

        {{-- Full-bleed background image layer (sits BEHIND every slide). --}}
        @if ($bgImage !== '')
            <img src="{{ $bgImage }}" alt="" aria-hidden="true" loading="lazy"
                 class="pointer-events-none absolute inset-0 z-0 h-full w-full object-cover"
                 style="opacity: {{ $bgOpacity / 100 }};">
        @endif

        @foreach ($slides as $idx => $slide)
            @php
                $imgDesktop = trim((string) ($slide['image'] ?? ''));
                $imgMobile  = trim((string) ($slide['image_mobile'] ?? '')) ?: $imgDesktop;
                // Focal point — which part of the image stays in view when it is
                // cropped to fill the banner (object-cover). Admin saves precise
                // focal_x/focal_y percentages (sliders); the older key-based
                // 'focal' values remain as back-compat for already-saved slides.
                $focalKeyMap = [
                    'tl' => '0% 0%', 'tc' => '50% 0%', 'tr' => '100% 0%',
                    'ml' => '0% 50%', 'mc' => '50% 50%', 'mr' => '100% 50%',
                    'bl' => '0% 100%', 'bc' => '50% 100%', 'br' => '100% 100%',
                    'center' => '50% 50%', 'top' => '50% 0%', 'bottom' => '50% 100%',
                    'left' => '0% 50%', 'right' => '100% 50%',
                    'top-left' => '0% 0%', 'top-right' => '100% 0%',
                    'bottom-left' => '0% 100%', 'bottom-right' => '100% 100%',
                ];
                $fx = $slide['focal_x'] ?? '';
                $fy = $slide['focal_y'] ?? '';
                if ($fx !== '' || $fy !== '') {
                    $fx = max(0, min(100, (int) ($fx === '' ? 50 : $fx)));
                    $fy = max(0, min(100, (int) ($fy === '' ? 50 : $fy)));
                    $focal = "{$fx}% {$fy}%";
                } else {
                    $focal = $focalKeyMap[$slide['focal'] ?? 'mc'] ?? '50% 50%';
                }
                $kicker     = trim((string) ($slide['kicker'] ?? ''));
                $title      = trim((string) ($slide['title'] ?? ''));
                $subtitle   = trim((string) ($slide['subtitle'] ?? ''));
                $ctaText    = trim((string) ($slide['cta_text'] ?? ''));
                $ctaLink    = trim((string) ($slide['cta_link'] ?? ''));
                $slideLink  = trim((string) ($slide['link'] ?? ''));
                $endsRaw    = trim((string) ($slide['ends_at'] ?? ''));
                $endsAt     = null;
                if ($endsRaw !== '') {
                    // Try Jalali first (the admin's date+time picker emits a
                    // Jalali datetime like "1404/01/01 12:30"); fall through to
                    // native Carbon for legacy "YYYY-MM-DD HH:MM" strings saved before R-16.
                    $gregorian = \App\Support\Jalali::parseDateTime($endsRaw);
                    try { $endsAt = \Carbon\Carbon::parse($gregorian ?: $endsRaw, 'Asia/Tehran'); }
                    catch (\Throwable $e) { $endsAt = null; }
                }
                $isDark = ($slide['text_color'] ?? 'white') === 'dark';
                $textClass = $isDark ? 'text-brand-900' : 'text-white';
                $subtleClass = $isDark ? 'text-brand-700' : 'text-white/70';
                $underlineClass = $isDark ? 'decoration-brand-900' : 'decoration-white';
                $titleSizeClass = $titleSizeMap[$slide['title_size'] ?? 'xl'];

                // Per-breakpoint position classes. Mobile applies always;
                // desktop class kicks in at md+ with a `md:` prefix.
                $posDesktopRaw = $posMap[$slide['position_desktop'] ?? 'mc'] ?? $posMap['mc'];
                $posMobileRaw  = $posMap[$slide['position_mobile']  ?? 'mc'] ?? $posMap['mc'];
                $posDesktop = collect(explode(' ', $posDesktopRaw))->map(fn ($c) => "md:$c")->implode(' ');
                $posClasses = $posMobileRaw.' '.$posDesktop;

                // ---- Per-slide font / overlay / CTA styling ----
                $titleFontClass = match ($slide['title_font'] ?? 'sans') {
                    'display' => 'font-display',
                    'mono'    => 'font-mono',
                    default   => 'font-sans',
                };
                $titleWeightClass = match ($slide['title_weight'] ?? 'extrabold') {
                    'light'    => 'font-light',
                    'normal'   => 'font-normal',
                    'semibold' => 'font-semibold',
                    'bold'     => 'font-bold',
                    default    => 'font-extrabold',
                };
                $titleTrackingClass = match ($slide['title_tracking'] ?? 'tight') {
                    'normal' => 'tracking-normal',
                    'wide'   => 'tracking-wide',
                    'wider'  => 'tracking-widest',
                    default  => 'tracking-tight',
                };
                $kickerClass = match ($slide['kicker_font'] ?? 'sans-italic') {
                    'display-italic' => 'font-display italic font-light',
                    'display-upper'  => 'font-display uppercase tracking-[0.22em] font-semibold',
                    default          => 'font-sans italic font-light',
                };

                // Per-slide overlay tint (overrides block-level when not 'none').
                // Class strings are emitted as literal `match` arms so Tailwind's
                // purger sees them at build time.
                $sOC = $slide['slide_overlay_color'] ?? 'none';
                $sOO = (string) ($slide['slide_overlay_opacity'] ?? '0');
                $slideOverlayClass = match (true) {
                    $sOC === 'dark'  && $sOO === '20' => 'bg-brand-950/20',
                    $sOC === 'dark'  && $sOO === '35' => 'bg-brand-950/35',
                    $sOC === 'dark'  && $sOO === '50' => 'bg-brand-950/50',
                    $sOC === 'dark'  && $sOO === '70' => 'bg-brand-950/70',
                    $sOC === 'dark'  && $sOO === '85' => 'bg-brand-950/85',
                    $sOC === 'light' && $sOO === '20' => 'bg-white/20',
                    $sOC === 'light' && $sOO === '35' => 'bg-white/35',
                    $sOC === 'light' && $sOO === '50' => 'bg-white/50',
                    $sOC === 'light' && $sOO === '70' => 'bg-white/70',
                    $sOC === 'light' && $sOO === '85' => 'bg-white/85',
                    $sOC === 'red'   && $sOO === '20' => 'bg-accent-600/20',
                    $sOC === 'red'   && $sOO === '35' => 'bg-accent-600/35',
                    $sOC === 'red'   && $sOO === '50' => 'bg-accent-600/50',
                    $sOC === 'red'   && $sOO === '70' => 'bg-accent-600/70',
                    $sOC === 'red'   && $sOO === '85' => 'bg-accent-600/85',
                    $sOC === 'gradient-bottom'        => 'bg-gradient-to-t from-black/70 via-black/10 to-transparent',
                    default                            => null,
                };

                // CTA style
                $ctaCls = match ($slide['cta_style'] ?? 'underline') {
                    'pill-light'  => 'rounded-full bg-white px-7 py-3 text-sm font-semibold text-brand-900 transition hover:bg-brand-100',
                    'pill-dark'   => 'rounded-full bg-brand-900 px-7 py-3 text-sm font-semibold text-white transition hover:bg-brand-800',
                    'pill-accent' => 'rounded-full bg-accent-600 px-7 py-3 text-sm font-semibold text-white transition hover:bg-accent-700',
                    'ghost'       => 'rounded-full border px-7 py-3 text-sm font-semibold transition hover:bg-white/10 '.($isDark ? 'border-brand-900 text-brand-900' : 'border-white text-white'),
                    default       => 'inline-block border-b pb-1 text-sm font-medium underline-offset-4 transition hover:opacity-70 sm:text-base '.$textClass.' '.$underlineClass,
                };

                // Per-slide text padding
                $textPadClass = match ($slide['text_padding'] ?? 'normal') {
                    'tight' => 'px-4 py-8 sm:px-6',
                    'loose' => 'px-8 py-20 sm:px-16 sm:py-28',
                    default => 'px-6 py-16 sm:px-10',
                };
            @endphp

            <div x-show="i === {{ $idx }}" x-transition.opacity.duration.700ms
                 @if ($idx > 0) x-cloak @endif
                 class="absolute inset-0">
                {{-- Per-breakpoint imagery: hidden swap via responsive utility --}}
                @if ($imgDesktop !== '')
                    <img src="{{ $imgMobile }}" alt="{{ $title }}"
                         class="{{ $imgClassMobile }}" style="object-position: {{ $focal }};">
                    <img src="{{ $imgDesktop }}" alt="{{ $title }}"
                         class="{{ $imgClassDesktop }}" style="object-position: {{ $focal }};">
                @endif
                @if ($slideOverlayClass)
                    <div class="absolute inset-0 {{ $slideOverlayClass }}"></div>
                @elseif ($overlayClass)
                    <div class="absolute inset-0 {{ $overlayClass }}"></div>
                @endif

                {{-- Whole-slide link: full-bleed anchor beneath the copy (z-5). The
                     copy layer is made pointer-events-none so clicks on empty area
                     fall through to this anchor; the CTA re-enables pointer events
                     so it still works. --}}
                @if ($slideLink !== '')
                    <a href="{{ $slideLink }}" class="absolute inset-0 z-[5]" aria-label="{{ $title !== '' ? $title : ($kicker !== '' ? $kicker : __('View')) }}"></a>
                @endif

                {{-- Overlay copy — positioned by per-breakpoint flex alignment --}}
                <div class="relative z-10 flex h-full min-h-inherit w-full {{ $textPadClass }} {{ $posClasses }} {{ $heightClass }} {{ $slideLink !== '' ? 'pointer-events-none' : '' }}">
                    <div class="reveal max-w-2xl {{ $textClass }}">
                        @if ($kicker !== '')
                            <p class="{{ $kickerClass }} text-base tracking-wide sm:text-lg md:text-xl">{{ $kicker }}</p>
                        @endif

                        @if ($title !== '')
                            <h2 class="mt-3 uppercase leading-[1.05] {{ $titleFontClass }} {{ $titleWeightClass }} {{ $titleTrackingClass }} {{ $titleSizeClass }}">{{ $title }}</h2>
                        @endif

                        @if ($endsAt && $endsAt->isFuture())
                            <div class="mt-6 flex items-end justify-center gap-4 fa-num sm:gap-6 md:justify-start" dir="ltr"
                                 x-data="{
                                    end: new Date('{{ $endsAt->toIso8601String() }}').getTime(),
                                    d:0, h:0, m:0, s:0, visible:true,
                                    tick() {
                                        const diff = this.end - Date.now();
                                        if (diff <= 0) { this.visible = false; return }
                                        this.d = Math.floor(diff / 86400000);
                                        this.h = Math.floor(diff % 86400000 / 3600000);
                                        this.m = Math.floor(diff % 3600000 / 60000);
                                        this.s = Math.floor(diff % 60000 / 1000);
                                    },
                                    fa(n) { return String(n).padStart(2,'0'); },
                                 }"
                                 x-init="tick(); setInterval(() => tick(), 1000)"
                                 x-show="visible">
                                @foreach (['d' => __('D'), 'h' => __('H'), 'm' => __('M'), 's' => __('S')] as $key => $unit)
                                    <div class="flex items-end gap-1">
                                        <span class="text-3xl font-bold leading-none sm:text-5xl md:text-6xl" x-text="fa({{ $key }})">00</span>
                                        <span class="font-display text-sm italic {{ $subtleClass }} sm:text-base">{{ $unit }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($ctaText !== '' && $ctaLink !== '')
                            <div class="mt-7">
                                <a href="{{ $ctaLink }}" class="pointer-events-auto {{ $ctaCls }}">{{ $ctaText }}</a>
                            </div>
                        @endif

                        @if ($subtitle !== '')
                            <p class="mt-5 max-w-md text-xs leading-7 {{ $subtleClass }} sm:text-sm">{{ $subtitle }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Slider chrome: arrows + dots, only when there's more than one slide --}}
        @if (count($slides) > 1)
            <button type="button" @click.stop="prev(); stop()" aria-label="{{ __('Previous') }}"
                    class="absolute end-4 top-1/2 z-20 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 sm:end-8">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <button type="button" @click.stop="next(); stop()" aria-label="{{ __('Next') }}"
                    class="absolute start-4 top-1/2 z-20 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/20 sm:start-8">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <div class="absolute bottom-6 left-1/2 z-20 flex -translate-x-1/2 gap-1.5">
                @foreach ($slides as $idx => $_)
                    <button type="button" @click="i = {{ $idx }}; stop()"
                            :class="i === {{ $idx }} ? 'bg-white w-6' : 'bg-white/40 w-2'"
                            class="h-2 rounded-full transition-all"
                            aria-label="{{ __('Go to slide') }} {{ $idx + 1 }}"></button>
                @endforeach
            </div>
        @endif

        {{-- Floating PNG overlay — sits ABOVE the slide images and overlay,
             positioned by % per breakpoint (admin-driven). Mobile defaults to
             the desktop position if no separate mobile values are set. --}}
        @if ($overlayPng !== '')
            @php $overlayLink = trim((string) ($data['overlay_png_link'] ?? '')); @endphp
            @if ($overlayLink !== '')
                {{-- Linked: the PNG itself is the click target (pointer-events-auto). --}}
                <a href="{{ $overlayLink }}" aria-label="{{ __('Banner') }}" data-eh-overlay data-eh-bp="mobile"
                   class="absolute z-20 -translate-x-1/2 -translate-y-1/2 md:hidden"
                   style="left: {{ $ovxM }}{{ $ovu }}; top: {{ $ovyM }}{{ $ovu }}; width: {{ $overlayW }}%;">
                    <img src="{{ $overlayPng }}" alt="" loading="lazy" class="block w-full">
                </a>
                <a href="{{ $overlayLink }}" aria-label="{{ __('Banner') }}" data-eh-overlay data-eh-bp="desktop"
                   class="absolute z-20 hidden -translate-x-1/2 -translate-y-1/2 md:block"
                   style="left: {{ $ovxD }}{{ $ovu }}; top: {{ $ovyD }}{{ $ovu }}; width: {{ $overlayW }}%;">
                    <img src="{{ $overlayPng }}" alt="" loading="lazy" class="block w-full">
                </a>
            @else
                <img src="{{ $overlayPng }}" alt="" aria-hidden="true" loading="lazy" data-eh-overlay data-eh-bp="mobile"
                     class="pointer-events-none absolute z-20 -translate-x-1/2 -translate-y-1/2 md:hidden"
                     style="left: {{ $ovxM }}{{ $ovu }}; top: {{ $ovyM }}{{ $ovu }}; width: {{ $overlayW }}%;">
                <img src="{{ $overlayPng }}" alt="" aria-hidden="true" loading="lazy" data-eh-overlay data-eh-bp="desktop"
                     class="pointer-events-none absolute z-20 hidden -translate-x-1/2 -translate-y-1/2 md:block"
                     style="left: {{ $ovxD }}{{ $ovu }}; top: {{ $ovyD }}{{ $ovu }}; width: {{ $overlayW }}%;">
            @endif
        @endif
    </section>
@endif

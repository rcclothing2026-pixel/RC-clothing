{{--
    Velour Parallax Banner — a crushed-velvet artwork with rising gold bubbles
    and cursor-driven depth parallax. Adapted from a standalone specimen into a
    reusable page-builder block: scoped `.vb` classes, a unique id per instance,
    admin-driven image / copy / accent / height / caption side / bubble
    intensity / parallax toggle. Uses the site's own fonts (Jost + JetBrains
    Mono) — no external Google Fonts request.
--}}
@php
    $id       = 'vb-'.\Illuminate\Support\Str::random(6);
    $imgD     = trim((string) ($data['image'] ?? ''));
    $imgM     = trim((string) ($data['image_mobile'] ?? '')) ?: $imgD;
    // Artwork framing — focal point (x/y %) + zoom (background-size %).
    $imgX     = max(0, min(100, (int) ($data['image_x'] ?? 50)));
    $imgY     = max(0, min(100, (int) ($data['image_y'] ?? 44)));
    $imgZoom  = max(100, min(220, (int) ($data['image_zoom'] ?? 132)));
    $kicker   = trim((string) ($data['kicker'] ?? ''));
    $title    = trim((string) ($data['title'] ?? ''));
    $titleEm  = trim((string) ($data['title_em'] ?? ''));
    $metaRaw  = $data['meta'] ?? [];
    $meta     = is_array($metaRaw) ? $metaRaw : array_filter(array_map('trim', explode("\n", (string) $metaRaw)));
    $link     = trim((string) ($data['link'] ?? ''));
    $accent   = trim((string) ($data['accent'] ?? '')) ?: '#d8b978';
    $capRaw   = $data['caption_position'] ?? 'right';
    $capPos   = in_array($capRaw, ['right','left','center'], true) ? $capRaw : 'right';
    $bubRaw   = $data['bubbles'] ?? 'subtle';
    $bubbles  = in_array($bubRaw, ['subtle','rich','none'], true) ? $bubRaw : 'subtle';
    $parallax = ($data['parallax'] ?? 'on') !== 'off';

    // Height — presets + custom, mirrors editorial_hero.
    $heightMode = $data['height'] ?? 'banner';
    $customVh   = max(10, min(120, (int) ($data['height_custom'] ?? 40)));
    $heightClass = match ($heightMode) {
        'strip'  => 'min-h-[20vh]',
        'short'  => 'min-h-[40vh]',
        'med'    => 'min-h-[75vh]',
        'tall'   => 'min-h-screen min-h-dvh',
        'custom' => '',
        default  => 'min-h-[55vh]', // banner
    };
    $heightStyle = $heightMode === 'custom' ? "min-height: {$customVh}vh;" : '';
    $Tag = $link !== '' ? 'a' : 'div';
@endphp

    {{-- Always render (dark velvet panel even with no image yet) so the block
         is visible in the page-builder preview instead of silently vanishing. --}}
    <{{ $Tag }} @if ($link !== '') href="{{ $link }}" @endif
        id="{{ $id }}" data-velour-banner data-bubbles="{{ $bubbles }}" data-parallax="{{ $parallax ? '1' : '0' }}"
        class="vb vb--cap-{{ $capPos }} relative block w-full overflow-hidden {{ $heightClass }}"
        style="--vb-img: url('{{ $imgD }}'); --vb-img-m: url('{{ $imgM }}'); --vb-accent: {{ $accent }}; --vb-x: {{ $imgX }}%; --vb-y: {{ $imgY }}%; --vb-zoom: {{ $imgZoom }}%; {{ $heightStyle }}">
        <div class="vb__layer" data-layer="bg">
            <div class="vb__art"></div>
            <div class="vb__nap"></div>
            <div class="vb__pile"></div>
            <div class="vb__artvig"></div>
        </div>
        <div class="vb__layer" data-layer="far"></div>
        <div class="vb__layer" data-layer="mid"></div>
        <div class="vb__layer" data-layer="near"></div>
        <div class="vb__sheen" data-layer="sheen"></div>
        <div class="vb__grain"></div>
        <div class="vb__edge"></div>
        <div class="vb__floor"></div>
        <div class="vb__mat"></div>

        @if ($kicker !== '' || $title !== '' || $titleEm !== '' || count($meta))
            <div class="vb__caption">
                @if ($kicker !== '')<div class="vb__kicker">{{ $kicker }}</div>@endif
                @if ($title !== '' || $titleEm !== '')
                    <div class="vb__title">{{ $title }}@if ($titleEm !== '')<br><em>{{ $titleEm }}</em>@endif</div>
                @endif
                @if (count($meta))
                    <div class="vb__rule"></div>
                    <div class="vb__meta">{!! implode('<br>', array_map('e', $meta)) !!}</div>
                @endif
            </div>
        @endif
    </{{ $Tag }}>

    @once
    <style>
        .vb { background:#050505; cursor:crosshair; }
        .vb__layer { position:absolute; inset:0; will-change:transform; }
        .vb__art { position:absolute; inset:-3%; background-image:var(--vb-img); background-size:var(--vb-zoom, 132%) auto; background-position:var(--vb-x, 50%) var(--vb-y, 44%); background-repeat:no-repeat; filter:saturate(.9) contrast(.95) brightness(.96) blur(.4px); }
        @media (max-width:767px){ .vb__art { background-image:var(--vb-img-m, var(--vb-img)); } }
        .vb__nap { position:absolute; inset:-4%; background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPSc2MDAnIGhlaWdodD0nNjAwJz48ZmlsdGVyIGlkPSdtJz48ZmVUdXJidWxlbmNlIHR5cGU9J2ZyYWN0YWxOb2lzZScgYmFzZUZyZXF1ZW5jeT0nMC4wMTIgMC4wMicgbnVtT2N0YXZlcz0nMycgc3RpdGNoVGlsZXM9J3N0aXRjaCcvPjxmZUNvbG9yTWF0cml4IHR5cGU9J3NhdHVyYXRlJyB2YWx1ZXM9JzAnLz48L2ZpbHRlcj48cmVjdCB3aWR0aD0nMTAwJScgaGVpZ2h0PScxMDAlJyBmaWx0ZXI9J3VybCgjbSknLz48L3N2Zz4="); background-size:700px 700px; mix-blend-mode:overlay; opacity:.55; }
        .vb__pile { position:absolute; inset:-4%; background-image:repeating-linear-gradient(94deg, rgba(0,0,0,.10) 0px, rgba(0,0,0,0) 1.5px, rgba(255,255,255,.05) 3px, rgba(0,0,0,0) 4.5px); mix-blend-mode:soft-light; opacity:.6; }
        .vb__artvig { position:absolute; inset:0; background:radial-gradient(120% 120% at 50% 46%, rgba(5,5,5,0) 55%, rgba(5,5,5,.5) 100%); }
        .vb__sheen { position:absolute; inset:-6%; pointer-events:none; background:radial-gradient(closest-side at 50% 38%, rgba(255,250,238,.13), rgba(255,250,238,0) 70%); mix-blend-mode:soft-light; will-change:transform; }
        .vb__grain { position:absolute; inset:0; pointer-events:none; background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnIHdpZHRoPScxNDAnIGhlaWdodD0nMTQwJz48ZmlsdGVyIGlkPSduJz48ZmVUdXJidWxlbmNlIHR5cGU9J2ZyYWN0YWxOb2lzZScgYmFzZUZyZXF1ZW5jeT0nMC44NScgbnVtT2N0YXZlcz0nMicgc3RpdGNoVGlsZXM9J3N0aXRjaCcvPjxmZUNvbG9yTWF0cml4IHR5cGU9J3NhdHVyYXRlJyB2YWx1ZXM9JzAnLz48L2ZpbHRlcj48cmVjdCB3aWR0aD0nMTAwJScgaGVpZ2h0PScxMDAlJyBmaWx0ZXI9J3VybCgjbiknLz48L3N2Zz4="); background-size:150px 150px; mix-blend-mode:soft-light; opacity:.5; }
        .vb__edge { position:absolute; inset:0; pointer-events:none; background:linear-gradient(90deg, rgba(5,5,5,.55) 0%, rgba(5,5,5,0) 26%, rgba(5,5,5,0) 74%, rgba(5,5,5,.55) 100%); }
        .vb__floor { position:absolute; inset:0; pointer-events:none; background:linear-gradient(0deg, rgba(5,5,5,.6) 0%, rgba(5,5,5,0) 34%); }
        .vb__mat { position:absolute; inset:20px; pointer-events:none; border:1px solid rgba(236,231,221,.16); }
        .vb__caption { position:absolute; bottom:9%; max-width:560px; pointer-events:none; }
        .vb--cap-right .vb__caption { right:4%; left:auto; text-align:right; }
        .vb--cap-left  .vb__caption { left:4%; right:auto; text-align:left; }
        .vb--cap-center .vb__caption { left:50%; transform:translateX(-50%); text-align:center; }
        .vb__kicker { font-family:'JetBrains Mono', monospace; font-size:clamp(9px,.7vw,11px); letter-spacing:4px; color:var(--vb-accent); text-transform:uppercase; margin-bottom:16px; }
        .vb__title { font-family:'Jost', sans-serif; font-size:clamp(30px,3.6vw,58px); line-height:.98; font-weight:600; color:#f2ede3; letter-spacing:.3px; }
        .vb__title em { font-style:italic; font-weight:400; }
        .vb__rule { width:44px; height:1px; background:color-mix(in srgb, var(--vb-accent) 60%, transparent); margin:22px 0 14px; }
        .vb--cap-center .vb__rule { margin-left:auto; margin-right:auto; }
        .vb__meta { font-family:'JetBrains Mono', monospace; font-size:clamp(9px,.7vw,11px); letter-spacing:1.5px; color:#a89f8c; line-height:1.7; }
        .vb__bub { position:absolute; border-radius:50%; opacity:0; will-change:transform, opacity; background:radial-gradient(circle at 34% 28%, rgba(255,248,231,.95), rgba(236,201,132,.42) 52%, rgba(236,201,132,0) 72%); animation-name:vbRise; animation-timing-function:linear; animation-iteration-count:infinite; }
        @keyframes vbRise { 0%{transform:translate(0,0) scale(.85); opacity:0;} 8%{opacity:var(--maxop);} 88%{opacity:var(--maxop);} 100%{transform:translate(var(--drift), calc(-1 * var(--travel))) scale(1.12); opacity:0;} }
        @media (prefers-reduced-motion: reduce){ .vb__bub{ animation:none; } }
    </style>
    <script>
    (function () {
        function rand(a, b){ return a + Math.random() * (b - a); }
        function fill(frame, layerName, n, cfg){
            var host = frame.querySelector('[data-layer="' + layerName + '"]');
            if (!host) return;
            for (var i = 0; i < n; i++){
                var size = rand(cfg.min, cfg.max), dur = rand(cfg.durMin, cfg.durMax);
                var b = document.createElement('div'); b.className = 'vb__bub'; var s = b.style;
                s.top = '100%'; s.left = rand(-2, 100).toFixed(2) + '%';
                s.width = s.height = size.toFixed(1) + 'px';
                s.boxShadow = '0 0 ' + (size * 0.9).toFixed(1) + 'px rgba(240,210,150,.25)';
                s.filter = cfg.blur ? 'blur(' + cfg.blur + 'px)' : 'none';
                s.setProperty('--drift', rand(-cfg.drift, cfg.drift).toFixed(1) + 'px');
                s.setProperty('--travel', (620 + size).toFixed(0) + 'px');
                s.setProperty('--maxop', String(cfg.op));
                s.animationDuration = dur.toFixed(1) + 's';
                s.animationDelay = (-rand(0, dur)).toFixed(1) + 's';
                host.appendChild(b);
            }
        }
        function initBanner(frame){
            if (frame.__vbInit) return; frame.__vbInit = true;
            var level = frame.getAttribute('data-bubbles') || 'subtle';
            var scale = level === 'rich' ? 1.6 : (level === 'none' ? 0 : 1);
            if (scale > 0){
                fill(frame, 'far',  Math.round(18 * scale), { min:3, max:8,  durMin:15, durMax:24, drift:30, op:0.22, blur:0.6 });
                fill(frame, 'mid',  Math.round(13 * scale), { min:7, max:15, durMin:10, durMax:16, drift:55, op:0.34, blur:0 });
                fill(frame, 'near', Math.round(7  * scale), { min:16,max:32, durMin:7,  durMax:11, drift:85, op:0.42, blur:1.6 });
            }
            if (frame.getAttribute('data-parallax') !== '1') return;
            var q = function (s){ return frame.querySelector('[data-layer="' + s + '"]'); };
            var layers = [
                { el:q('bg'),   fx:-16, fy:-10, dfx:-8, dfy:5  },
                { el:q('far'),  fx:8,   fy:5,   dfx:5,  dfy:-4 },
                { el:q('mid'),  fx:20,  fy:12,  dfx:9,  dfy:-7 },
                { el:q('near'), fx:40,  fy:24,  dfx:15, dfy:-10 },
                { el:q('sheen'),fx:70,  fy:46,  dfx:22, dfy:16 }
            ];
            var mx=0, my=0, cx=0, cy=0;
            frame.addEventListener('mousemove', function (e){
                var r = frame.getBoundingClientRect();
                mx = ((e.clientX - r.left) / r.width - 0.5) * 2;
                my = ((e.clientY - r.top) / r.height - 0.5) * 2;
            });
            frame.addEventListener('mouseleave', function (){ mx = 0; my = 0; });
            (function loop(t){
                cx += (mx - cx) * 0.05; cy += (my - cy) * 0.05;
                var dx = Math.sin(t / 4200), dy = Math.cos(t / 5600);
                for (var i = 0; i < layers.length; i++){
                    var L = layers[i];
                    if (L.el) L.el.style.transform = 'translate(' + (cx*L.fx + dx*L.dfx).toFixed(2) + 'px,' + (cy*L.fy + dy*L.dfy).toFixed(2) + 'px)';
                }
                requestAnimationFrame(loop);
            })(0);
        }
        function boot(){ document.querySelectorAll('[data-velour-banner]').forEach(initBanner); }
        if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
    })();
    </script>
    @endonce

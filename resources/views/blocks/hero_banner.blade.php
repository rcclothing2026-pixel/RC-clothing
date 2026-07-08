@php
    $cfg = \App\Support\Hero::config();
    $slides = $cfg['slides'] ?? [];
@endphp

@once
<style>
  .chiaco-hero{width:100%;display:flex;align-items:center;justify-content:center;padding:48px 32px;background:radial-gradient(120% 120% at 50% 0%, #f4eff1 0%, #E0D5D9 100%);font-family:'Helvetica Neue',Helvetica,Arial,system-ui,sans-serif;}
  .chiaco-hero *{box-sizing:border-box;}
  .ch-card{position:relative;width:100%;max-width:1180px;aspect-ratio:1180/640;background:#fff;border-radius:34px;box-shadow:0 40px 90px -30px rgba(40,30,40,.28),0 12px 30px -12px rgba(0,0,0,.12);overflow:hidden;}
  .ch-card{--ch-anim:800ms;}
  .ch-slide{position:absolute;inset:0;opacity:0;pointer-events:none;transition:opacity .45s ease;}
  .ch-slide.is-active{opacity:1;pointer-events:auto;}
  .ch-slide.is-leaving{opacity:1;pointer-events:none;}
  .ch-slide.is-entering .ch-headline,.ch-slide.is-entering .ch-hero,
  .ch-slide.is-leaving .ch-headline,.ch-slide.is-leaving .ch-hero{animation-duration:var(--ch-anim)!important;}
  /* idle bob on product image */
  .ch-hero img,.ch-hero .ch-shape{animation:ch-idle 6s ease-in-out infinite;}
  @keyframes ch-idle{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
  /* enter/exit rows */
  .ch-slide.is-entering .ch-row{animation:ch-row-in .5s ease both;animation-delay:calc(var(--i,0)*70ms + .12s);}
  .ch-slide.is-entering .ch-title,.ch-slide.is-entering .ch-thead{animation:ch-fade-in .6s ease both;}
  .ch-slide.is-leaving .ch-headline{animation:ch-h-out .42s ease both;}
  .ch-slide.is-leaving .ch-hero{animation:ch-hero-out .42s ease both;}
  .ch-slide.is-leaving .ch-row,.ch-slide.is-leaving .ch-title,.ch-slide.is-leaving .ch-thead{animation:ch-fade-out .32s ease both;}
  @keyframes ch-fade-in{from{opacity:0}to{opacity:1}}
  @keyframes ch-fade-out{from{opacity:1}to{opacity:0}}
  @keyframes ch-row-in{from{opacity:0;transform:translateX(26px)}to{opacity:1;transform:translateX(0)}}
  @keyframes ch-h-out{from{opacity:1;transform:translateY(0)}to{opacity:0;transform:translateY(-26px)}}
  @keyframes ch-hero-out{from{opacity:1;transform:scale(1)}to{opacity:0;transform:translate(-20px,20px) scale(.88)}}
  /* DRIFT */
  .ch-slide[data-motion="drift"].is-entering .ch-headline{animation:ch-h-in-drift .7s cubic-bezier(.22,1,.36,1) both;}
  .ch-slide[data-motion="drift"].is-entering .ch-hero{animation:ch-hero-in-drift .8s cubic-bezier(.22,1,.36,1) both;}
  @keyframes ch-h-in-drift{from{opacity:0;transform:translateY(42px)}to{opacity:1;transform:translateY(0)}}
  @keyframes ch-hero-in-drift{from{opacity:0;transform:translate(-24px,36px) scale(.88)}to{opacity:1;transform:translate(0,0) scale(1)}}
  /* SLIDE */
  .ch-slide[data-motion="slide"].is-entering .ch-headline{animation:ch-h-in-slide .7s cubic-bezier(.22,1,.36,1) both;}
  .ch-slide[data-motion="slide"].is-entering .ch-hero{animation:ch-hero-in-slide .75s cubic-bezier(.22,1,.36,1) both;}
  @keyframes ch-h-in-slide{from{opacity:0;transform:translateX(64px)}to{opacity:1;transform:translateX(0)}}
  @keyframes ch-hero-in-slide{from{opacity:0;transform:translateX(80px) scale(.92)}to{opacity:1;transform:translate(0,0) scale(1)}}
  /* FADE */
  .ch-slide[data-motion="fade"].is-entering .ch-headline{animation:ch-fade-in .65s ease both;}
  .ch-slide[data-motion="fade"].is-entering .ch-hero{animation:ch-fade-in .8s ease both;}
  /* ZOOM */
  .ch-slide[data-motion="zoom"].is-entering .ch-headline{animation:ch-h-in-zoom .7s cubic-bezier(.22,1,.36,1) both;}
  .ch-slide[data-motion="zoom"].is-entering .ch-hero{animation:ch-hero-in-zoom .75s cubic-bezier(.22,1,.36,1) both;}
  @keyframes ch-h-in-zoom{from{opacity:0;transform:scale(.86)}to{opacity:1;transform:scale(1)}}
  @keyframes ch-hero-in-zoom{from{opacity:0;transform:scale(.6)}to{opacity:1;transform:scale(1)}}
  @media (prefers-reduced-motion:reduce){
    .ch-slide *{animation:none!important;}
    .ch-hero img,.ch-hero .ch-shape{animation:none!important;}
  }
  /* left coloured blob panel */
  .ch-blob{position:absolute;top:0;left:0;width:58%;height:100%;z-index:1;}
  .ch-blob>svg{position:absolute;inset:0;width:100%;height:100%;display:block;}
  .ch-pattern{position:absolute;inset:0;opacity:.10;background-image:repeating-linear-gradient(135deg,transparent 0 13px,#fff 13px 15px);clip-path:path('M0,0 L470,0 C500,120 430,210 360,300 C300,378 360,470 410,560 C440,615 430,640 360,640 L0,640 Z');}
  .ch-watermark{position:absolute;left:34px;bottom:42px;opacity:.12;}
  /* headline */
  .ch-headline{position:absolute;top:21%;left:48px;z-index:5;max-width:44%;margin:0;font-weight:700;font-size:clamp(40px,6.6vw,84px);line-height:.94;letter-spacing:-.01em;color:#fff;white-space:pre-line;}
  /* white bean anchor — always visible */
  .ch-bean{position:absolute;bottom:30%;left:54px;width:62px;height:30px;background:#fff;border-radius:999px;transform:rotate(-18deg);box-shadow:0 8px 18px rgba(0,0,0,.12);z-index:5;opacity:.92;}
  /* hero product — z-index 6; positioned to overflow past the panel boundary (~590px) */
  .ch-hero{position:absolute;top:5%;left:24%;z-index:6;display:block;text-decoration:none;}
  .ch-hero img{width:400px;height:auto;display:block;filter:drop-shadow(0 26px 36px rgba(40,20,30,.4));}
  .ch-shape{position:relative;display:block;width:300px;height:170px;filter:drop-shadow(0 30px 40px rgba(40,20,30,.35));}
  .ch-shape i{position:absolute;display:block;background:var(--g);}
  .ch-shape .p1{left:8px;right:8px;top:6px;height:96px;border-radius:80px 80px 70px 70px;box-shadow:inset 0 -10px 24px rgba(0,0,0,.18),inset 0 12px 22px rgba(255,255,255,.22);}
  .ch-shape .p2{left:0;width:74px;height:118px;top:30px;border-radius:60px 40px 50px 60px;box-shadow:inset 0 8px 18px rgba(255,255,255,.2);}
  .ch-shape .p3{right:0;width:74px;height:118px;top:30px;border-radius:40px 60px 60px 50px;box-shadow:inset 0 8px 18px rgba(255,255,255,.2);}
  .ch-shape .p4{left:34px;top:96px;width:96px;height:52px;border-radius:46px;box-shadow:inset 0 -8px 16px rgba(0,0,0,.16);}
  .ch-shape .l1{left:40px;bottom:-8px;width:10px;height:30px;border-radius:6px;background:#1a1414;transform:rotate(10deg);}
  .ch-shape .l2{right:40px;bottom:-8px;width:10px;height:30px;border-radius:6px;background:#1a1414;transform:rotate(-10deg);}
  /* floaters — position/size/blur/color all come from inline style; only animation varies by slot */
  .ch-floater{position:absolute;border-radius:50%;z-index:3;display:block;text-decoration:none;}
  .ch-floater[data-slot="0"]{--rot:18deg;animation:ch-float 7s ease-in-out infinite;}
  .ch-floater[data-slot="1"]{--rot:0deg;animation:ch-floatslow 9s ease-in-out infinite;}
  .ch-floater[data-slot="2"]{--rot:0deg;animation:ch-float 6s ease-in-out infinite;}
  .ch-floater[data-slot="3"]{--rot:0deg;animation:ch-floatslow 8s ease-in-out infinite;}
  @keyframes ch-float{0%{transform:translateY(0) rotate(var(--rot,0deg));}50%{transform:translateY(-14px) rotate(calc(var(--rot,0deg) + 4deg));}100%{transform:translateY(0) rotate(var(--rot,0deg));}}
  @keyframes ch-floatslow{0%{transform:translateY(0) rotate(var(--rot,0deg));}50%{transform:translateY(10px) rotate(calc(var(--rot,0deg) - 3deg));}100%{transform:translateY(0) rotate(var(--rot,0deg));}}
  /* right panel */
  .ch-panel{position:absolute;top:0;right:0;width:50%;height:100%;padding:44px 48px;z-index:4;display:flex;flex-direction:column;}
  .ch-title{margin:0;font-weight:300;font-size:clamp(26px,3vw,42px);line-height:1.04;color:#282828;letter-spacing:-.01em;}
  .ch-title b{font-weight:600;}
  .ch-thead{display:flex;justify-content:space-between;align-items:center;margin-top:28px;padding-bottom:14px;border-bottom:1px solid #ededf1;}
  .ch-thead span{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:#bdb3b7;}
  .ch-row{display:flex;align-items:center;gap:16px;padding:16px 8px;margin:0 -8px;border-bottom:1px solid #f3f0f1;text-decoration:none;border-radius:12px;transition:background .2s ease;}
  .ch-row:hover{background:#faf6f7;}
  .ch-name{flex:1;min-width:0;font-weight:500;font-size:clamp(14px,1.3vw,18px);color:#282828;}
  .ch-dims{font-size:12px;color:#c2b8bc;white-space:nowrap;}
  .ch-tile{width:46px;height:46px;border-radius:14px;flex-shrink:0;background-size:cover;background-position:center;box-shadow:0 8px 16px -6px rgba(0,0,0,.25);}
  .ch-no{font-size:13px;color:#9b9094;width:26px;text-align:left;}
  .ch-plus{width:30px;height:30px;border-radius:50%;background:#f4eff1;color:#282828;font-size:18px;line-height:1;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
  /* chrome */
  .ch-logo{position:absolute;top:38px;left:48px;z-index:6;display:block;}
  .ch-toolbar{position:absolute;left:50%;bottom:30px;transform:translateX(-50%);z-index:8;display:flex;align-items:center;gap:6px;background:#fff;border-radius:999px;padding:8px 8px 8px 18px;box-shadow:0 18px 40px -14px rgba(40,20,30,.32);}
  .ch-toolbar a{border:none;background:none;color:#5a5256;display:flex;align-items:center;cursor:pointer;padding:8px;text-decoration:none;}
  .ch-toolbar a.accent{color:#CC3333;}
  .ch-go{width:52px;height:52px;border-radius:50%;background:#282828;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;margin-left:4px;text-decoration:none;}
  .ch-dots{position:absolute;left:50%;bottom:8px;transform:translateX(-50%);z-index:9;display:flex;gap:7px;}
  .ch-dots button{border:none;cursor:pointer;height:7px;width:7px;border-radius:999px;transition:all .3s ease;padding:0;background:#d8cccf;}
  .ch-dots button.is-active{width:22px;background:#282828;}
  /* mobile */
  @media (max-width:820px){
    .chiaco-hero{padding:18px 12px;}
    .ch-card{aspect-ratio:auto;border-radius:26px;}
    .ch-slide{position:relative;}
    .ch-slide:not(.is-active){display:none;}
    .ch-blob{position:relative;width:100%;height:300px;}
    .ch-pattern{clip-path:none;}
    .ch-headline{position:absolute;top:196px;bottom:auto;left:20px;right:auto;max-width:52%;font-size:28px;line-height:1.1;}
    .ch-watermark{display:none;}
    /* show only the smaller floaters on mobile, scaled down */
    .ch-floater{display:none;}
    .ch-floater[data-slot="2"],.ch-floater[data-slot="3"]{display:block;transform:scale(.7);transform-origin:top left;}
    /* hero image: flows naturally below blob, before product rows */
    .ch-hero{display:block;position:relative;margin:8px auto 0;width:fit-content;z-index:6;}
    .ch-hero img{width:200px;height:auto;}
    .ch-shape{width:190px;height:108px;}
    .ch-slide.is-entering .ch-hero{animation:ch-fade-in .55s ease both!important;}
    .ch-slide.is-entering .ch-headline{animation:ch-fade-in .55s ease both!important;}
    .ch-slide.is-leaving .ch-hero{animation:ch-fade-out .32s ease both!important;}
    .ch-slide.is-leaving .ch-headline{animation:ch-fade-out .32s ease both!important;}
    .ch-bean{bottom:32%;left:16px;width:48px;height:24px;}
    .ch-panel{position:relative;width:100%;height:auto;padding:20px 18px 8px;}
    .ch-title{font-size:22px;}
    .ch-logo{top:18px;left:22px;}
    .ch-toolbar{display:none;}
    .ch-dots{position:relative;left:auto;bottom:auto;transform:none;justify-content:center;margin:10px 0 16px;}
  }
</style>
<script>
(function () {
  function init() {
    document.querySelectorAll('[data-chiaco-hero]').forEach(function (card) {
      if (card.dataset.heroInit) return; card.dataset.heroInit = '1';
      var slides = [].slice.call(card.querySelectorAll('.ch-slide'));
      var dots = [].slice.call(card.querySelectorAll('.ch-dots button'));
      if (slides.length < 2) return;
      var rotation = Math.max(2000, (parseFloat(card.dataset.rotation) || 5) * 1000);
      var autoplay = card.dataset.autoplay !== '0';
      var pauseHover = card.dataset.pause !== '0';
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var anim = Math.max(200, Math.min(2000, parseInt(card.dataset.anim, 10) || 800));
      var EXIT = Math.round(anim * 0.55), ENTER = anim + 20;
      var idx = 0, t = null, busy = false;
      function dotsActive(i) { dots.forEach(function (d, k) { d.classList.toggle('is-active', k === i); }); }
      function goTo(i) {
        if (i === idx || busy) return;
        busy = true;
        clearTimeout(t);
        var leaving = slides[idx], entering = slides[i];
        leaving.classList.add('is-leaving');
        leaving.classList.remove('is-active');
        entering.classList.add('is-active', 'is-entering');
        dotsActive(i);
        setTimeout(function () { leaving.classList.remove('is-leaving'); }, EXIT);
        setTimeout(function () {
          entering.classList.remove('is-entering');
          idx = i; busy = false; schedule();
        }, ENTER);
      }
      function advance() { goTo((idx + 1) % slides.length); }
      function schedule() { clearTimeout(t); if (autoplay && !reduce) t = setTimeout(advance, rotation); }
      function stop() { clearTimeout(t); }
      // touch swipe
      var tx = 0;
      card.addEventListener('touchstart', function(e){ tx = e.touches[0].clientX; }, {passive:true});
      card.addEventListener('touchend', function(e){
        var dx = e.changedTouches[0].clientX - tx;
        if (Math.abs(dx) > 44) goTo(dx < 0 ? (idx+1)%slides.length : (idx-1+slides.length)%slides.length);
      }, {passive:true});
      dots.forEach(function (d, i) { d.addEventListener('click', function () { goTo(i); }); });
      if (pauseHover) {
        card.addEventListener('mouseenter', stop);
        card.addEventListener('mouseleave', schedule);
      }
      slides[0].classList.add('is-entering');
      setTimeout(function () { slides[0].classList.remove('is-entering'); }, ENTER);
      schedule();
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>
@endonce

@php
    $signPath = 'M5 39 V31 H13 V23 H21 V13 H27 M51 39 V31 H43 V23 H35 V13 H29 M27 13 H29 M24 39 V30 H32 V39';
    $autoplay = ! empty($cfg['autoplay']) ? 1 : 0;
    $pause = ! empty($cfg['pauseOnHover']) ? 1 : 0;
    $firstCta = $slides[0]['ctaLink'] ?? '#';
    $avatarGrad = \App\Support\Hero::grad($slides[0]['accent'] ?? 'red');
    $bgPattern = $cfg['bgPattern'] ?? 'none';

    // Auto-fill each hero product tile from the product's CURRENT catalog photo
    // (resolved by its /product/{slug} link), so tiles stay up to date even if
    // the product had no image when it was added. An explicit/uploaded img wins.
    $heroProductImages = [];
    $heroSlugs = [];
    foreach ($slides as $s) {
        foreach (($s['products'] ?? []) as $p) {
            if (empty($p['img']) && preg_match('~/product/([^/?#]+)~', (string) ($p['link'] ?? ''), $m)) {
                $heroSlugs[] = $m[1];
            }
        }
    }
    if ($heroSlugs) {
        $heroProductImages = \App\Models\Product::whereIn('slug', array_values(array_unique($heroSlugs)))
            ->with('images')->get()
            ->mapWithKeys(fn ($pp) => [$pp->slug => $pp->primary_image_url])
            ->filter()->all();
    }
@endphp
<section class="chiaco-hero" style="position:relative;">
  @if ($bgPattern !== 'none')
    <div class="ch-bg-pattern" aria-hidden="true" style="position:absolute;inset:0;z-index:0;pointer-events:none;color:#282828;">
      @if ($bgPattern === 'quatrefoil')
        <x-brand-pattern-2 class="h-full w-full" :opacity="'0.07'" />
      @else
        <x-brand-pattern class="h-full w-full" :opacity="'0.5'" :stroke="'0.6'" />
      @endif
    </div>
  @endif
  <div class="ch-card" data-chiaco-hero data-autoplay="{{ $autoplay }}" data-pause="{{ $pause }}" data-rotation="{{ $cfg['rotationSeconds'] ?? 5 }}" data-anim="{{ (int) ($cfg['animationSpeedMs'] ?? 800) }}" style="position:relative;z-index:1;--ch-anim:{{ (int) ($cfg['animationSpeedMs'] ?? 800) }}ms;">
    @foreach ($slides as $si => $s)
      @php
        // Free hex bg_color overrides named accent
        if (! empty($s['bg_color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $s['bg_color'])) {
            $accent = $s['bg_color'];
            $top    = \App\Support\Hero::mix($accent, '#ffffff', 0.45);
            $bot    = \App\Support\Hero::mix($accent, '#000000', 0.28);
            $heroGrad = \App\Support\Hero::gradFromHex($accent);
        } else {
            $accent   = \App\Support\Hero::hex($s['accent'] ?? 'red');
            $top      = \App\Support\Hero::mix($accent, 'ffffff', 0.45);
            $bot      = \App\Support\Hero::mix($accent, '000000', 0.28);
            $heroGrad = \App\Support\Hero::grad($s['accent'] ?? 'red');
        }
        // Floater defaults by slot index
        $fDefaults = [[6, 30, 70, 1.2], [44, 16, 96, 1.0], [12, 8, 34, 0.6], [55, 40, 50, 0.8]];
      @endphp
      <div class="ch-slide{{ $si === 0 ? ' is-active' : '' }}" data-motion="{{ $s['motion'] ?? 'drift' }}">
        <div class="ch-blob">
          <svg viewBox="0 0 600 640" preserveAspectRatio="none">
            <defs><linearGradient id="chBlob{{ $si }}" x1="0" y1="0" x2="0.6" y2="1">
              <stop offset="0%" stop-color="{{ $top }}"></stop>
              <stop offset="55%" stop-color="{{ $accent }}"></stop>
              <stop offset="100%" stop-color="{{ $bot }}"></stop>
            </linearGradient></defs>
            <path d="M0,0 L470,0 C500,120 430,210 360,300 C300,378 360,470 410,560 C440,615 430,640 360,640 L0,640 Z" fill="url(#chBlob{{ $si }})"></path>
          </svg>
          <div class="ch-pattern"></div>
          <svg class="ch-watermark" viewBox="0 0 56 44" width="150" fill="none" stroke="#fff" stroke-width="2.4" stroke-linejoin="miter"><path d="{{ $signPath }}"></path></svg>
        </div>

        <h1 class="ch-headline">{{ $s['headline'] ?? '' }}</h1>
        <span class="ch-bean"></span>

        @php
          $hTop   = isset($s['hero_top'])   ? (float)$s['hero_top']   : 5;
          $hLeft  = isset($s['hero_left'])  ? (float)$s['hero_left']  : 24;
          $hWidth = isset($s['hero_width']) ? (int)$s['hero_width']   : 400;
        @endphp
        <a class="ch-hero" href="{{ $s['ctaLink'] ?? '#' }}" style="top:{{ $hTop }}%;left:{{ $hLeft }}%;">
          @if (!empty($s['heroImg']))
            <img src="{{ $s['heroImg'] }}" alt="" style="width:{{ $hWidth }}px;">
          @else
            <span class="ch-shape" style="--g:{{ $heroGrad }};width:{{ $hWidth }}px;height:{{ round($hWidth*0.57) }}px;"><i class="p1"></i><i class="p2"></i><i class="p3"></i><i class="p4"></i><i class="l1"></i><i class="l2"></i></span>
          @endif
        </a>

        @foreach (($s['floaters'] ?? []) as $fi => $f)
          @php
            // Per-floater color: free hex overrides named accent gradient
            $fGrad = (! empty($f['color_hex']) && preg_match('/^#[0-9a-fA-F]{6}$/', $f['color_hex']))
                ? \App\Support\Hero::gradFromHex($f['color_hex'])
                : \App\Support\Hero::grad($f['color'] ?? 'red');
            $fDef  = $fDefaults[$fi] ?? [10, 20, 60, 1.0];
            $fTop  = isset($f['top'])  ? (float) $f['top']  : $fDef[0];
            $fLeft = isset($f['left']) ? (float) $f['left'] : $fDef[1];
            $fSize = isset($f['size']) ? (int)   $f['size'] : $fDef[2];
            $fBlur = isset($f['blur']) ? (float) $f['blur'] : $fDef[3];
          @endphp
          <a class="ch-floater" data-slot="{{ $fi }}" href="{{ $f['link'] ?? '#' }}"
             style="top:{{ $fTop }}%;left:{{ $fLeft }}%;width:{{ $fSize }}px;height:{{ $fSize }}px;filter:blur({{ $fBlur }}px) drop-shadow(0 16px 22px rgba(0,0,0,.2));background:{{ $fGrad }};"></a>
        @endforeach

        <div class="ch-panel">
          <h2 class="ch-title">{{ $cfg['brandLine'] ?? '' }}<br><b>{{ $cfg['storeTitle'] ?? '' }}</b></h2>
          <div class="ch-thead"><span>Product</span><span>Code</span></div>
          @foreach (($s['products'] ?? []) as $p)
            @php
                // Priority: an explicit/uploaded image wins (override); else the
                // linked product's current catalog photo. The accent color is the
                // tile background, with the photo layered on top as an <img> that
                // removes itself if it fails to load — so a broken/404 image URL
                // shows the colour instead of an empty box.
                $pImg = $p['img'] ?? '';
                if ($pImg === '' && preg_match('~/product/([^/?#]+)~', (string) ($p['link'] ?? ''), $pm)) {
                    $pImg = $heroProductImages[$pm[1]] ?? '';
                }
                $tileColor = \App\Support\Hero::grad($p['color'] ?? 'red');
            @endphp
            <a class="ch-row" href="{{ $p['link'] ?? '#' }}" style="--i:{{ $loop->index }}">
              <span class="ch-name">{{ $p['name'] ?? '' }}</span>
              <span class="ch-dims">{{ $p['dims'] ?? '' }}</span>
              <span class="ch-tile" style="background:{{ $tileColor }}">@if ($pImg !== '')<img src="{{ $pImg }}" alt="" loading="lazy" onerror="this.style.display='none'" style="display:block;width:100%;height:100%;object-fit:cover;border-radius:inherit;">@endif</span>
              <span class="ch-no">{{ $p['no'] ?? '' }}</span>
              <span class="ch-plus">+</span>
            </a>
          @endforeach
        </div>
      </div>
    @endforeach

    <a class="ch-logo" href="{{ $cfg['logoLink'] ?? '#' }}"><svg viewBox="0 0 56 44" width="26" fill="none" stroke="rgba(255,255,255,.92)" stroke-width="2.6" stroke-linejoin="miter"><path d="{{ $signPath }}"></path></svg></a>

    @php($tb = $cfg['toolbar'] ?? [])
    <div class="ch-toolbar">
      <a href="{{ $tb['sizes'] ?? '#' }}" aria-label="Sizes"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 8h18v8H3zM7 8v3M11 8v4M15 8v3M19 8v4"></path></svg></a>
      <a href="{{ $tb['edit'] ?? '#' }}" aria-label="Edit" class="accent"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M16 3 21 8 8 21H3v-5L16 3Z"></path></svg></a>
      <a href="{{ $tb['account'] ?? '/account' }}" aria-label="Account"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="3.2"></circle><path d="M5 20c0-3.3 3.1-6 7-6s7 2.7 7 6"></path></svg></a>
      <a class="ch-go" href="{{ ($tb['cta'] ?? '') ?: $firstCta }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M5 12h14M13 6l6 6-6 6"></path></svg></a>
    </div>

    <div class="ch-dots">
      @foreach ($slides as $si => $s)
        <button type="button" class="{{ $si === 0 ? 'is-active' : '' }}" data-index="{{ $si }}"></button>
      @endforeach
    </div>
  </div>
</section>

<!DOCTYPE html>
@php($__locale = app()->getLocale())
<html lang="{{ $__locale }}" dir="{{ $__locale === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', ($site['site.seo_title'] ?? null) ?: 'Racket Club | The Art of Leisure')</title>
    <meta name="description" content="@yield('meta_description', ($site['site.seo_description'] ?? null) ?: 'Racket Club — quiet-luxury leisurewear for the life off the court. Legends & Legacy.')">
    @if ($kw = ($site['site.seo_keywords'] ?? null))<meta name="keywords" content="{{ $kw }}">@endif
    {{-- hreflang + canonical + structured data. Everything is computed in ONE
         @php…@endphp block: the inline @php()/@json() directives choke on
         multi-line array literals (they broke Blade compilation → 500). Persian
         (the default) is the clean URL; English is ?lang=en; canonical is
         self-referencing per language; x-default → Persian. --}}
    @php
        $__urlFa = request()->fullUrlWithoutQuery(['lang']);
        $__urlEn = request()->fullUrlWithQuery(['lang' => 'en']);
        $__canonical = $__locale === config('app.locale', 'fa') ? $__urlFa : $__urlEn;
        $__brand = ($site['site.store_name'] ?? null) ?: 'Racket Club';
        $__social = array_values(array_filter([
            ($ig = ($site['site.instagram'] ?? null)) ? 'https://instagram.com/'.ltrim($ig, '@') : null,
            ($tg = ($site['site.telegram'] ?? null)) ? 'https://t.me/'.ltrim($tg, '@') : null,
            ($wa = ($site['site.whatsapp'] ?? null)) ? 'https://wa.me/'.preg_replace('/\D/', '', $wa) : null,
        ]));
        $__orgLd = json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $__brand,
            'url' => url('/'),
            'logo' => asset('brand/mark-navy.svg'),
            'sameAs' => $__social ?: null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $__siteLd = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $__brand,
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/shop').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @endphp
    <link rel="canonical" href="{{ $__canonical }}">
    <link rel="alternate" hreflang="fa" href="{{ $__urlFa }}">
    <link rel="alternate" hreflang="en" href="{{ $__urlEn }}">
    <link rel="alternate" hreflang="x-default" href="{{ $__urlFa }}">
    {{-- Open Graph / social --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ $__brand }}">
    <meta property="og:title" content="@yield('title', 'Racket Club | The Art of Leisure')">
    <meta property="og:description" content="@yield('meta_description', 'Racket Club — quiet-luxury leisurewear. Legends & Legacy.')">
    <meta property="og:url" content="{{ $__canonical }}">
    <meta property="og:locale" content="{{ $__locale === 'fa' ? 'fa_IR' : 'en_US' }}">
    <meta name="twitter:card" content="summary_large_image">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
        <meta name="twitter:image" content="@yield('og_image')">
    @elseif (trim((string) ($site['site.og_image'] ?? '')) !== '')
        <meta property="og:image" content="{{ $site['site.og_image'] }}">
        <meta name="twitter:image" content="{{ $site['site.og_image'] }}">
    @endif
    <script type="application/ld+json">{!! $__orgLd !!}</script>
    <script type="application/ld+json">{!! $__siteLd !!}</script>
    @stack('head')
    @if ($gv = ($site['site.google_verification'] ?? null))
        <meta name="google-site-verification" content="{{ $gv }}">
    @endif
    @if ($ga = ($site['site.ga4_id'] ?? null))
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $ga }}');
        </script>
    @endif
    @if ($gtm = ($site['site.gtm_id'] ?? null))
        {{-- Google Tag Manager (head snippet) — paired with the <noscript> right after <body>. --}}
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $gtm }}');</script>
    @endif
    {{-- Admin-pasted scripts (Google tag / GTM / chat) — in <head> so Google can
         detect the tag and gtag/GTM load correctly. Trusted admin input. --}}
    @if ($chat = ($site['site.live_chat_code'] ?? null))
        {!! $chat !!}
    @endif
    @stack('analytics')
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#18234f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Racket Club">
    <link rel="apple-touch-icon" href="/favicon.svg">
    @if(config('services.nj_focus.key'))
    {{-- NJ Focus browser error capture → central event hub (nj-focus-IR) --}}
    <script>
    (function(){
      var E=@json((rtrim((string) config('services.nj_focus.base_url'), '/') ?: 'https://chiacoservice.ir').'/api/hub/report'),K=@json(config('services.nj_focus.key')),APP=@json(config('services.nj_focus.app','rc-clothing'));
      function send(d){d.app=APP;fetch(E,{method:'POST',headers:{'Content-Type':'application/json','X-API-Key':K},body:JSON.stringify(d)}).catch(function(){});}
      window.onerror=function(msg,src,l,c,err){send({message:String(msg),stack:err&&err.stack,url:src,severity:'high'});};
      window.addEventListener('unhandledrejection',function(e){send({message:e.reason&&e.reason.message||String(e.reason),stack:e.reason&&e.reason.stack,severity:'medium'});});
      var _ce=console.error.bind(console);
      console.error=function(){_ce.apply(console,arguments);try{send({message:Array.from(arguments).map(String).join(' '),severity:'low'});}catch(e){}};
    })();
    </script>
    @endif
</head>
<body class="min-h-dvh flex flex-col bg-paper text-ink pb-[calc(3.75rem+env(safe-area-inset-bottom,0px))] lg:pb-0">
    @if ($gtm = ($site['site.gtm_id'] ?? null))
        {{-- Google Tag Manager (noscript fallback). Must sit immediately after <body>. --}}
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:inset-x-4 focus:top-3 focus:z-50 focus:block focus:rounded-xl focus:bg-brand-900 focus:p-4 focus:text-center focus:text-sm focus:font-bold focus:text-white">{{ __('Skip to main content') }}</a>

    @include('partials.promo-bar')
    @include('partials.header')

    <div x-data="{ show: true }" x-show="show" x-cloak
         x-init="setTimeout(() => show = false, 5000)"
         class="mx-auto max-w-7xl px-4 pt-4 sm:px-6">
        @if (session('success'))
            <div class="flex items-center justify-between rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200" role="alert">
                <span>{{ session('success') }}</span>
                <button @click="show = false" class="text-green-500 hover:text-green-700" aria-label="{{ __('Close') }}">&times;</button>
            </div>
        @endif
        @if (session('error'))
            <div class="flex items-center justify-between rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert">
                <span>{{ session('error') }}</span>
                <button @click="show = false" class="text-red-400 hover:text-red-600" aria-label="{{ __('Close') }}">&times;</button>
            </div>
        @endif
    </div>

    {{-- overflow-x: clip kills any rogue horizontal overflow (a full-bleed
         section, a marquee, a wide grid) that would otherwise push the page
         wider than the viewport and make `mx-auto`-centred blocks look
         shifted/off-centre — a common RTL symptom. `clip` (not `hidden`) does
         NOT create a scroll container, so the sticky header is unaffected. --}}
    <main id="main-content" class="flex-1 outline-none" style="overflow-x: clip;">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.bottom-nav')
    @include('partials.popups')
    @include('partials.mini-cart')
    @include('partials.free-ship-widget')

    <div x-data="{ show: !localStorage.getItem('cookie_consent') }" x-show="show" x-cloak
         class="fixed inset-x-0 bottom-0 z-50 border-t border-brand-200 bg-white p-4 text-sm shadow-lg">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
            <p class="text-brand-600">{{ __('We use cookies to improve your experience.') }} <a href="{{ route('page', 'privacy') }}" class="text-accent-600 hover:underline">{{ __('Learn more') }}</a></p>
            <button @click="localStorage.setItem('cookie_consent', '1'); show = false"
                    class="shrink-0 rounded-full bg-brand-900 px-5 py-2 text-xs font-bold text-white transition hover:bg-brand-800">{{ __('Accept') }}</button>
        </div>
    </div>
</body>
</html>

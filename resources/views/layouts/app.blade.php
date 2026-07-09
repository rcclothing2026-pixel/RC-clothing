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
    <link rel="canonical" href="{{ url()->current() }}">
    {{-- Open Graph / social --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ ($site['site.store_name'] ?? null) ?: 'Racket Club' }}">
    <meta property="og:title" content="@yield('title', 'Racket Club | The Art of Leisure')">
    <meta property="og:description" content="@yield('meta_description', 'Racket Club — quiet-luxury leisurewear. Legends & Legacy.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
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

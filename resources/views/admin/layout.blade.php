<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت') | چیاکو</title>

    {{-- Favicon: SVG for modern browsers, ICO fallback, Apple touch icon. --}}
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak]{display:none!important}
        .admin-table thead { position:sticky; top:0; z-index:10; }
        .admin-table thead::after { content:''; position:absolute; inset:0; background:inherit; z-index:-1; }
    </style>
</head>
<body class="min-h-screen bg-brand-50 text-ink">
    {{-- Mobile top bar --}}
    <div class="sticky top-0 z-30 flex items-center justify-between border-b border-brand-100 bg-white px-4 py-3 lg:hidden">
        <button type="button" id="admin-menu-btn" aria-label="منو" class="grid h-9 w-9 place-items-center rounded-lg text-brand-700 ring-1 ring-brand-200">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <span class="text-base font-bold text-brand-800">چیاکو <span class="text-xs font-normal text-brand-400">مدیریت</span></span>
        <a href="{{ route('home') }}" class="text-xs text-brand-400">سایت</a>
    </div>

    {{-- Off-canvas backdrop (mobile) --}}
    <div id="admin-backdrop" class="fixed inset-0 z-40 hidden bg-brand-900/40 lg:hidden"></div>

    {{-- Nav collapse state. Uses a v2 localStorage key so any stale `closed`
         value from earlier iterations is ignored — sidebar always starts
         visible. Floating «open» button uses stock `grid` (no `lg:` variant)
         so it works even if the CSS bundle hasn't been rebuilt. --}}
    <div x-data="{ nav: localStorage.getItem('admin-nav-v2') !== 'closed' }"
         x-init="$watch('nav', v => localStorage.setItem('admin-nav-v2', v ? 'open' : 'closed'))"
         class="flex min-h-screen">

        {{-- Floating «open nav» button — visible only while nav is collapsed. --}}
        <button x-show="!nav" @click="nav = true" x-cloak type="button"
                class="fixed end-3 top-3 z-40 grid h-9 w-9 place-items-center rounded-lg bg-white text-brand-600 shadow-md ring-1 ring-brand-200 transition hover:bg-brand-50"
                title="نمایش نوار کناری" aria-label="نمایش نوار کناری">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        {{-- Sidebar (off-canvas on mobile, collapsable on desktop via Alpine `nav`).
             Mobile: slides via `translate-x-full` controlled by the existing JS.
             Desktop: when nav=false, we add `lg:hidden` which is stock Tailwind
             (display:none on ≥lg) — no rebuild required. The floating «open»
             button above lives outside the aside so it stays clickable. --}}
        <aside id="admin-sidebar"
               :class="nav ? '' : 'lg:hidden'"
               class="fixed inset-y-0 right-0 z-50 w-64 shrink-0 translate-x-full overflow-y-auto border-l border-brand-100 bg-white p-5 transition-transform duration-200 lg:static lg:z-auto lg:translate-x-0">
            <div class="mb-2 flex items-center justify-between">
                <a href="{{ route('home') }}" class="block text-xl font-bold text-brand-800">چیاکو <span class="text-xs font-normal text-brand-400">مدیریت</span></a>
                <button type="button" id="admin-menu-close" class="text-brand-400 lg:hidden" aria-label="بستن">✕</button>
                <button type="button" @click="nav = false" class="hidden text-brand-400 hover:text-brand-700 lg:block" aria-label="جمع کردن نوار کناری" title="جمع کردن">←</button>
            </div>
            @php
                $sections = [
                    'فروش' => [
                        ['admin.dashboard', 'داشبورد'],
                        ['admin.orders.index', 'سفارش‌ها'],
                        ['admin.reports.reconciliation', 'تسویه و مغایرت'],
                        ['admin.returns.index', 'مرجوعی‌ها'],
                        ['admin.stock-log.index', 'گردش موجودی StoqS'],
                    ],
                    'کاتالوگ' => [
                        ['admin.products.index', 'محصولات'],
                        ['admin.categories.index', 'دسته‌بندی‌ها'],
                        ['admin.collections.index', 'مجموعه‌ها'],
                        ['admin.size-guides.index', 'راهنمای سایز'],
                        ['admin.media.index', 'کتابخانه رسانه'],
                    ],
                    'مشتریان' => [
                        ['admin.customers.index', 'مشتریان (CRM)'],
                        ['admin.subscribers.index', 'مشترکین خبرنامه'],
                        ['admin.messages.index', 'پیام‌ها'],
                        ['admin.sms.settings', 'پنل پیامک'],
                    ],
                    'تخفیف‌ها و کارت هدیه' => [
                        ['admin.discounts.index', 'کدها و قوانین تخفیف'],
                        ['admin.discount-rules.index', 'تخفیف‌های خودکار'],
                        ['admin.gift-cards.index', 'کارت‌های هدیه'],
                    ],
                    'ظاهر فروشگاه' => [
                        ['admin.hero.edit', 'بنر هیرو'],
                        ['admin.pages.index', 'صفحه‌ساز'],
                        ['admin.menus.index', 'منوها'],
                        ['admin.popups.index', 'پاپ‌آپ‌ها'],
                        ['admin.settings.fonts', 'فونت‌ها'],
                        ['admin.settings.home', 'صفحه اصلی (سریع)'],
                    ],
                    'تنظیمات' => [
                        ['admin.shipping.index', 'روش‌های ارسال'],
                        ['admin.settings.payments', 'درگاه‌های پرداخت'],
                        ['admin.settings.integration', 'اتصال به StoqS'],
                        ['admin.settings.site', 'تنظیمات سایت'],
                        ['admin.system-log.index', 'گزارش خطاها'],
                    ],
                ];

                $activeSection = '';
                foreach ($sections as $title => $items) {
                    foreach ($items as [$route, $label]) {
                        $prefix = str_replace('.index', '', $route);
                        if (request()->routeIs($prefix . '*')) {
                            $activeSection = $title;
                            break 2;
                        }
                    }
                }
                if (!$activeSection && request()->routeIs('admin.discount-rules*')) {
                    $activeSection = 'بازاریابی';
                }
            @endphp
            <nav class="mt-6 text-sm"
                 x-data="{ open: '{{ $activeSection }}' }">
                @foreach ($sections as $title => $items)
                    <div class="{{ $loop->first ? '' : 'mt-1' }}">
                        <button type="button"
                                @click="open = (open === '{{ $title }}' ? '' : '{{ $title }}')"
                                class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-semibold tracking-wide transition {{ $title === $activeSection ? 'text-brand-800' : 'text-brand-400 hover:text-brand-600' }}">
                            <span>{{ $title }}</span>
                            <svg class="h-3.5 w-3.5 transition-transform duration-200"
                                 :class="{ 'rotate-180': open === '{{ $title }}' }"
                                 fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </button>
                        <div x-show="open === '{{ $title }}'" x-collapse x-cloak>
                            <div class="mr-2 mt-0.5 space-y-0.5 border-r border-brand-100 pr-2">
                                @foreach ($items as [$route, $label])
                                    <a href="{{ route($route) }}"
                                       class="block rounded-lg px-3 py-1.5 text-sm font-medium transition {{ request()->routeIs(str_replace('.index','',$route).'*') ? 'bg-brand-900 text-white' : 'text-brand-700 hover:bg-brand-100' }}">
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </nav>
            <form action="{{ route('logout') }}" method="POST" class="mt-8">
                @csrf
                <button class="text-xs text-brand-400 hover:text-red-500">خروج</button>
            </form>
        </aside>

        {{-- Main --}}
        <main class="flex-1 p-5 sm:p-8">
            <div class="mx-auto max-w-6xl">
                @if (session('error'))
                    <div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{{ session('error') }}</div>
                @endif
                @if (session('success'))
                    <div class="mb-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        (function () {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('admin-backdrop');
            const open = () => { sidebar.classList.remove('translate-x-full'); backdrop.classList.remove('hidden'); };
            const close = () => { sidebar.classList.add('translate-x-full'); backdrop.classList.add('hidden'); };
            document.getElementById('admin-menu-btn')?.addEventListener('click', open);
            document.getElementById('admin-menu-close')?.addEventListener('click', close);
            backdrop?.addEventListener('click', close);
            sidebar?.querySelectorAll('nav a').forEach((a) => a.addEventListener('click', close));
        })();
    </script>
    <script>
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (e) => {
                if (! confirm(form.dataset.confirm || 'آیا مطمئن هستید؟')) {
                    e.preventDefault();
                }
            });
        });
    </script>
    @stack('scripts')
</body>
</html>

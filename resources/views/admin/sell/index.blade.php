<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>فروش سریع | Racket Club</title>
    {{-- Installable on the phone's home screen, scoped to /admin/sell only. --}}
    <link rel="manifest" href="/sell-manifest.webmanifest">
    <meta name="theme-color" content="#18234f">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="فروش راکت کلاب">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="/img/sell-icon-192.png">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-50 text-ink antialiased">
<div x-data="sellApp()" x-init="boot()" class="mx-auto min-h-screen max-w-lg pb-28" style="padding-top: env(safe-area-inset-top)">

    {{-- ─────────────── Pick products ─────────────── --}}
    <section x-show="screen === 'pick'">
        <header class="flex items-center justify-between px-4 pb-2 pt-4">
            <div>
                <p class="text-xs text-brand-500">فروش اینستاگرام</p>
                <h1 class="text-xl font-extrabold text-brand-900">Racket Club · فروش سریع</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.dashboard') }}" class="grid h-11 w-11 place-items-center rounded-xl bg-white text-brand-700 ring-1 ring-brand-200" aria-label="پنل مدیریت">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h7v7H4zM13 5h7v4h-7zM13 11h7v8h-7zM4 14h7v5H4z"/></svg>
                </a>
                <button type="button" @click="openLinks()" class="grid h-11 w-11 place-items-center rounded-xl bg-white text-brand-900 ring-1 ring-brand-200" aria-label="لینک‌های من">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
                </button>
            </div>
        </header>

        <div class="sticky top-0 z-20 flex gap-2 bg-brand-50 px-4 pb-3 pt-1">
            <label class="flex h-12 flex-1 items-center gap-2 rounded-2xl bg-white px-3.5 ring-1 ring-brand-200">
                <svg class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" x-model="q" @input.debounce.300ms="search()" placeholder="نام محصول یا بارکد" aria-label="جستجوی محصول"
                       class="w-full bg-transparent text-[15px] outline-none">
            </label>
            <button type="button" x-show="canScan" @click="startScan()" class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-900 text-white" aria-label="اسکن بارکد">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><path d="M8 9v6M11 9v6M14 9v6M17 9v6"/></svg>
            </button>
        </div>

        <p x-show="loading" class="px-4 py-6 text-center text-sm text-brand-500">در حال جستجو…</p>
        <p x-show="!loading && products.length === 0" x-cloak class="px-4 py-6 text-center text-sm text-brand-500">محصولی پیدا نشد.</p>

        <div class="grid grid-cols-2 gap-3 px-4">
            <template x-for="p in products" :key="p.id">
                <button type="button" @click="openProduct(p)" class="flex flex-col overflow-hidden rounded-2xl bg-white text-right ring-1 ring-brand-100"
                        :class="p.stock < 1 ? 'opacity-60' : ''">
                    <div class="relative aspect-[4/5] w-full bg-brand-100">
                        <img x-show="p.image" :src="p.image" :alt="p.name" class="h-full w-full object-cover" loading="lazy">
                        <span class="absolute bottom-2 right-2 rounded-full bg-white/90 px-2 py-0.5 text-[11px] text-brand-900"
                              x-text="p.stock > 0 ? fa(p.stock) + ' موجود' : 'ناموجود'"></span>
                    </div>
                    <div class="flex flex-col gap-0.5 p-3">
                        <span class="line-clamp-2 text-sm font-medium text-brand-900" x-text="p.name"></span>
                        <span class="text-[13px] text-brand-600" x-text="p.price"></span>
                    </div>
                </button>
            </template>
        </div>
    </section>

    {{-- ─────────────── Customer cart ─────────────── --}}
    <section x-show="screen === 'cart'" x-cloak>
        <header class="flex items-center gap-2 px-4 pb-3 pt-4">
            <button type="button" @click="screen = 'pick'" class="grid h-11 w-11 place-items-center rounded-xl bg-white ring-1 ring-brand-200" aria-label="بازگشت">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
            </button>
            <h1 class="text-xl font-extrabold text-brand-900">سبد مشتری</h1>
            <span class="ms-auto text-sm text-brand-500" x-text="fa(cart.length) + ' کالا'"></span>
        </header>

        <div class="space-y-3 px-4">
            <template x-for="(l, i) in cart" :key="l.variant_id">
                <div class="flex items-center gap-3 rounded-2xl bg-white p-2.5 ring-1 ring-brand-100">
                    <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-brand-100">
                        <img x-show="l.image" :src="l.image" alt="" class="h-full w-full object-cover">
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-bold" x-text="l.name"></p>
                        <p class="text-[13px] text-brand-500" x-text="l.label"></p>
                        <p class="text-sm" x-text="toman(l.price * l.qty)"></p>
                    </div>
                    <div class="flex flex-col items-center rounded-xl ring-1 ring-brand-200">
                        <button type="button" @click="l.qty = Math.min(l.qty + 1, l.stock, 20); save()" class="h-9 w-10 text-lg" aria-label="زیاد کردن">+</button>
                        <span class="text-[15px] font-bold" x-text="fa(l.qty)"></span>
                        <button type="button" @click="l.qty > 1 ? l.qty-- : cart.splice(i, 1); save()" class="h-9 w-10 text-lg" aria-label="کم کردن">−</button>
                    </div>
                </div>
            </template>

            <div class="space-y-3 rounded-2xl bg-white p-3.5 ring-1 ring-brand-100">
                <label class="block text-[13px] font-bold">کد تخفیف (اختیاری)
                    <input x-model="coupon" dir="ltr" autocapitalize="characters" class="mt-1.5 h-12 w-full rounded-xl border border-brand-200 px-3 text-[15px] uppercase outline-none focus:border-brand-800">
                </label>
                <label class="block text-[13px] font-bold">برای (اختیاری — فقط برای پیگیری شما)
                    <input x-model="handle" placeholder="@آیدی اینستاگرام مشتری" class="mt-1.5 h-12 w-full rounded-xl border border-brand-200 px-3 text-[15px] outline-none focus:border-brand-800">
                </label>
                <div>
                    <span class="text-[13px] font-bold">اعتبار لینک</span>
                    <div class="mt-1.5 grid grid-cols-3 gap-2">
                        <template x-for="h in [[24, '۲۴ ساعت'], [72, '۳ روز'], [168, '۷ روز']]" :key="h[0]">
                            <button type="button" @click="hours = h[0]" class="h-11 rounded-xl text-sm"
                                    :class="hours === h[0] ? 'bg-brand-900 text-white' : 'bg-white ring-1 ring-brand-200'" x-text="h[1]"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-brand-100 bg-white px-4 pt-3" style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
            <div class="mx-auto max-w-lg space-y-2">
                <div class="flex justify-between text-sm text-brand-600"><span>جمع کالاها</span><span x-text="toman(subtotal())"></span></div>
                <p x-show="error" x-text="error" class="text-sm text-red-600" role="alert"></p>
                <button type="button" @click="createLink()" :disabled="busy || cart.length === 0"
                        class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-accent-600 text-[17px] font-extrabold text-white disabled:opacity-50">
                    <span x-text="busy ? 'در حال ساخت…' : 'ساخت لینک خرید'"></span>
                </button>
            </div>
        </div>
    </section>

    {{-- ─────────────── Link ready ─────────────── --}}
    <section x-show="screen === 'done'" x-cloak class="flex min-h-screen flex-col gap-5 px-5 pb-8 pt-10">
        <div class="flex flex-col items-center gap-2 text-center">
            <div class="grid h-16 w-16 place-items-center rounded-full bg-green-100 text-green-700">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
            </div>
            <h1 class="text-xl font-extrabold">لینک خرید آماده است</h1>
            <p class="text-sm text-brand-600" x-text="result ? fa(result.units) + ' کالا · ' + result.total + ' · معتبر تا ' + hoursLabel() : ''"></p>
        </div>
        <div class="flex items-center gap-2 rounded-2xl bg-white p-1.5 ring-1 ring-brand-200">
            <span dir="ltr" class="flex-1 truncate px-3 text-left text-[17px] font-bold" x-text="result?.url.replace(/^https?:\/\//, '')"></span>
            <button type="button" @click="copy()" class="h-11 rounded-xl bg-brand-100 px-4 text-sm font-bold" x-text="copied ? 'کپی شد' : 'کپی'"></button>
        </div>
        <div class="mt-auto space-y-2.5">
            <button type="button" @click="share()" class="flex h-14 w-full items-center justify-center gap-2 rounded-2xl bg-accent-600 text-[17px] font-extrabold text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M12 3v12"/><path d="m7 8 5-5 5 5"/></svg>
                ارسال به دایرکت
            </button>
            <button type="button" @click="reset()" class="h-12 w-full rounded-2xl bg-white text-[15px] font-bold ring-1 ring-brand-200">سبد جدید برای مشتری بعدی</button>
        </div>
    </section>

    {{-- ─────────────── My links ─────────────── --}}
    <section x-show="screen === 'links'" x-cloak>
        <header class="flex items-center gap-2 px-4 pb-3 pt-4">
            <button type="button" @click="screen = 'pick'" class="grid h-11 w-11 place-items-center rounded-xl bg-white ring-1 ring-brand-200" aria-label="بازگشت">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
            </button>
            <h1 class="text-xl font-extrabold">لینک‌های من</h1>
        </header>
        <div class="grid grid-cols-3 gap-2 px-4 pb-3">
            <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-brand-100"><p class="text-xs text-brand-500">امروز ساخته شد</p><p class="text-xl font-extrabold" x-text="fa(today.created)"></p></div>
            <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-brand-100"><p class="text-xs text-brand-500">باز شده</p><p class="text-xl font-extrabold" x-text="fa(today.opened)"></p></div>
            <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-brand-100"><p class="text-xs text-brand-500">سفارش</p><p class="text-xl font-extrabold text-green-700" x-text="fa(today.ordered)"></p></div>
        </div>
        <div class="space-y-2.5 px-4">
            <template x-for="k in links" :key="k.code">
                <button type="button" @click="navigator.clipboard?.writeText(k.url)" class="flex w-full items-center gap-3 rounded-2xl bg-white px-3.5 py-3 text-right ring-1 ring-brand-100">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-bold" x-text="k.handle || 'بدون نام'"></p>
                        <p class="truncate text-xs text-brand-500" x-text="k.code + ' · ' + fa(k.units) + ' کالا · ' + k.when + (k.order ? ' · سفارش ' + k.order : '')"></p>
                    </div>
                    <span class="whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold"
                          :class="{ paid: 'bg-green-100 text-green-800', ordered: 'bg-amber-100 text-amber-800', opened: 'bg-amber-50 text-amber-800', sent: 'bg-brand-100 text-brand-700', expired: 'bg-white text-brand-500 ring-1 ring-brand-200' }[k.status]"
                          x-text="k.label"></span>
                </button>
            </template>
            <p x-show="links.length === 0" class="py-6 text-center text-sm text-brand-500">هنوز لینکی ساخته نشده.</p>
        </div>
    </section>

    {{-- Cart bar (pick screen) --}}
    <button type="button" x-show="screen === 'pick' && cart.length" x-cloak @click="screen = 'cart'"
            class="fixed inset-x-4 bottom-5 z-30 mx-auto flex max-w-lg items-center justify-between rounded-2xl bg-accent-600 px-5 py-4 text-white shadow-xl">
        <span class="flex items-center gap-2.5 text-[15px] font-bold">
            <span class="grid h-6 min-w-6 place-items-center rounded-full bg-white px-1.5 text-[13px] text-accent-600" x-text="fa(units())"></span>
            سبد مشتری
        </span>
        <span class="text-[15px] font-bold" x-text="toman(subtotal())"></span>
    </button>

    {{-- Size / colour sheet --}}
    <div x-show="sheet" x-cloak class="fixed inset-0 z-40 bg-brand-900/40" @click.self="sheet = null">
        <section role="dialog" aria-modal="true" aria-label="انتخاب رنگ و سایز" class="absolute inset-x-0 bottom-0 mx-auto max-w-lg space-y-4 rounded-t-3xl bg-white px-5 pt-3"
                 style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom))">
            <div class="mx-auto h-1.5 w-10 rounded-full bg-brand-200"></div>
            <template x-if="sheet">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-brand-100"><img x-show="sheet.image" :src="sheet.image" alt="" class="h-full w-full object-cover"></div>
                        <div>
                            <p class="text-base font-bold" x-text="sheet.name"></p>
                            <p class="text-sm text-brand-600" x-text="chosen() ? toman(chosen().price) : sheet.price"></p>
                            <p x-show="chosen()?.sku" dir="ltr" class="text-right text-xs text-brand-500" x-text="'SKU ' + (chosen()?.sku || '')"></p>
                        </div>
                    </div>
                    <div x-show="colors().length > 0">
                        <p class="mb-2 text-sm font-bold">رنگ: <span class="font-normal" x-text="color || '—'"></span></p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="c in colors()" :key="c.name">
                                <button type="button" @click="color = c.name; size = null" :aria-pressed="color === c.name"
                                        class="h-11 rounded-full px-3 text-sm ring-1"
                                        :class="color === c.name ? 'bg-brand-900 text-white ring-brand-900' : 'bg-white ring-brand-200'">
                                    <span class="me-1 inline-block h-4 w-4 rounded-full align-middle ring-1 ring-brand-200" :style="c.hex ? 'background:' + c.hex : 'display:none'"></span>
                                    <span x-text="c.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-bold">سایز</p>
                        <div class="grid grid-cols-4 gap-2">
                            <template x-for="v in sizesFor()" :key="v.id">
                                <button type="button" @click="v.stock > 0 && (size = v.id)" :disabled="v.stock < 1"
                                        class="flex h-14 flex-col items-center justify-center rounded-xl"
                                        :class="v.stock < 1 ? 'bg-brand-100 text-brand-500 line-through' : (size === v.id ? 'bg-brand-900 text-white' : 'bg-white ring-1 ring-brand-200')">
                                    <span class="text-[15px] font-bold" x-text="v.size || 'تک‌سایز'"></span>
                                    <span class="text-[11px]" x-text="v.stock > 0 ? fa(v.stock) + ' عدد' : 'تمام'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex h-14 items-center rounded-xl ring-1 ring-brand-200">
                            <button type="button" @click="qty = Math.max(1, qty - 1)" class="h-full w-12 text-xl" aria-label="کم کردن">−</button>
                            <span class="w-7 text-center text-lg font-bold" x-text="fa(qty)"></span>
                            <button type="button" @click="qty = Math.min(qty + 1, chosen()?.stock || 20, 20)" class="h-full w-12 text-xl" aria-label="زیاد کردن">+</button>
                        </div>
                        <button type="button" @click="addToCart()" :disabled="!chosen()"
                                class="h-14 flex-1 rounded-xl bg-brand-900 text-base font-bold text-white disabled:opacity-40">افزودن به سبد مشتری</button>
                    </div>
                </div>
            </template>
        </section>
    </div>

    {{-- Barcode scanner --}}
    <div x-show="scanning" x-cloak class="fixed inset-0 z-50 flex flex-col bg-black">
        <video x-ref="video" class="h-full w-full flex-1 object-cover" playsinline muted></video>
        <button type="button" @click="stopScan()" class="absolute inset-x-6 bottom-8 h-14 rounded-2xl bg-white text-base font-bold">بستن</button>
    </div>
</div>

<script>
    window.sellApp = () => ({
        screen: 'pick', q: '', products: [], loading: false,
        cart: [], coupon: '', handle: '', hours: 72, busy: false, error: '',
        sheet: null, color: null, size: null, qty: 1,
        result: null, copied: false, links: [], today: { created: 0, opened: 0, ordered: 0 },
        canScan: 'BarcodeDetector' in window && !!navigator.mediaDevices, scanning: false, stream: null,

        boot() {
            try { this.cart = JSON.parse(localStorage.getItem('sell_cart') || '[]'); } catch (e) { this.cart = []; }
            this.search();
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sell-sw.js', { scope: '/admin/sell' }).catch(() => {});
            }
        },
        save() { try { localStorage.setItem('sell_cart', JSON.stringify(this.cart)); } catch (e) {} },
        fa(n) { return String(n ?? 0).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]); },
        toman(n) { return this.fa(Math.round(n || 0).toLocaleString('en-US').replace(/,/g, '٬')) + ' تومان'; },
        units() { return this.cart.reduce((s, l) => s + l.qty, 0); },
        subtotal() { return this.cart.reduce((s, l) => s + l.price * l.qty, 0); },
        hoursLabel() { return { 24: '۲۴ ساعت', 72: '۳ روز', 168: '۷ روز' }[this.hours]; },

        async search() {
            this.loading = true;
            try {
                const res = await fetch(@js(route('admin.sell.search')) + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
                this.products = (await res.json()).products || [];
                // A scanned/typed barcode that matches exactly one size → open it pre-selected.
                const code = this.q.trim();
                if (code) {
                    for (const p of this.products) {
                        const v = p.variants.find(v => v.sku && v.sku === code);
                        if (v) { this.openProduct(p, v); break; }
                    }
                }
            } catch (e) {
                this.products = [];
            } finally {
                this.loading = false;
            }
        },

        openProduct(p, preset = null) {
            this.sheet = p; this.qty = 1;
            const firstInStock = preset || p.variants.find(v => v.stock > 0) || p.variants[0];
            this.color = firstInStock?.color || null;
            this.size = preset ? preset.id : (p.variants.length === 1 && firstInStock?.stock > 0 ? firstInStock.id : null);
        },
        colors() {
            const seen = new Map();
            (this.sheet?.variants || []).forEach(v => { if (v.color && !seen.has(v.color)) seen.set(v.color, { name: v.color, hex: v.color_hex }); });
            return [...seen.values()];
        },
        sizesFor() {
            const byColor = this.colors().length > 0;
            return (this.sheet?.variants || []).filter(v => !byColor || (v.color || '') === (this.color || ''));
        },
        chosen() { return (this.sheet?.variants || []).find(v => v.id === this.size) || null; },

        addToCart() {
            const v = this.chosen(); if (!v) return;
            const line = this.cart.find(l => l.variant_id === v.id);
            if (line) {
                line.qty = Math.min(line.qty + this.qty, v.stock, 20);
            } else {
                this.cart.push({
                    variant_id: v.id, qty: Math.min(this.qty, v.stock, 20), price: v.price, stock: v.stock,
                    name: this.sheet.name, image: this.sheet.image,
                    label: [v.color, v.size].filter(Boolean).join(' · '),
                });
            }
            this.save(); this.sheet = null;
        },

        async createLink() {
            this.busy = true; this.error = '';
            try {
                const res = await fetch(@js(route('admin.sell.store')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ items: this.cart.map(l => ({ variant_id: l.variant_id, qty: l.qty })), coupon: this.coupon.trim() || null, handle: this.handle.trim() || null, hours: this.hours }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { this.error = data.message || 'ساخت لینک انجام نشد.'; return; }
                this.result = data; this.copied = false; this.screen = 'done';
            } catch (e) {
                this.error = 'اتصال برقرار نشد. دوباره تلاش کنید.';
            } finally {
                this.busy = false;
            }
        },
        async copy() {
            try { await navigator.clipboard.writeText(this.result.url); this.copied = true; } catch (e) {}
        },
        async share() {
            const text = 'سبد خرید شما در Racket Club آماده است — برای پرداخت بزنید:';
            if (navigator.share) {
                try { await navigator.share({ title: 'Racket Club', text, url: this.result.url }); return; } catch (e) { if (e.name === 'AbortError') return; }
            }
            this.copy();
        },
        reset() {
            this.cart = []; this.coupon = ''; this.handle = ''; this.hours = 72; this.result = null;
            this.save(); this.screen = 'pick';
        },
        async openLinks() {
            this.screen = 'links';
            try {
                const data = await (await fetch(@js(route('admin.sell.links')), { headers: { Accept: 'application/json' } })).json();
                this.links = data.links || []; this.today = data.today || this.today;
            } catch (e) {}
        },

        async startScan() {
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            } catch (e) { return; }
            this.scanning = true;
            const video = this.$refs.video;
            video.srcObject = this.stream; await video.play();
            const detector = new BarcodeDetector();
            const tick = async () => {
                if (!this.scanning) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length) { this.q = codes[0].rawValue; this.stopScan(); this.search(); return; }
                } catch (e) {}
                setTimeout(tick, 250);
            };
            tick();
        },
        stopScan() {
            this.scanning = false;
            this.stream?.getTracks().forEach(t => t.stop());
            this.stream = null;
        },
    });
</script>
</body>
</html>

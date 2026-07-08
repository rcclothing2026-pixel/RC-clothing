{{--
    Mini-cart drawer + global add-to-cart interceptor.

    Any <form action="/cart/add"> on the site posts via fetch instead of a
    full redirect; on success the drawer slides in from the start side (RTL
    = right) showing the just-added item, the top 4 cart lines, the
    subtotal, and three CTAs (continue shopping / view cart / checkout).

    The non-JS path is preserved: if JS is off, the form submits normally
    and the user lands on the cart page (existing behaviour).
--}}
<div x-data="miniCart()"
     x-on:open-mini-cart.window="open($event.detail)"
     x-on:keydown.escape.window="close()"
     x-cloak>
    {{-- Scrim --}}
    <div x-show="visible" x-transition.opacity
         @click="close()"
         class="fixed inset-0 z-[60] bg-brand-950/40"></div>

    {{-- Drawer (slides from start side = right in RTL) --}}
    <aside x-show="visible"
           x-transition:enter="transition transform ease-out duration-300"
           x-transition:enter-start="translate-x-full rtl:-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition transform ease-in duration-200"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full rtl:-translate-x-full"
           class="fixed inset-y-0 start-0 z-[61] flex w-full max-w-md flex-col bg-white shadow-2xl"
           role="dialog" aria-modal="true" aria-labelledby="mini-cart-title">

        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-brand-100 px-5 py-4">
            <h2 id="mini-cart-title" class="flex items-center gap-2 text-base font-bold text-brand-900">
                <svg class="h-5 w-5 text-accent-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6 5 3H3"/></svg>
                به سبد اضافه شد
                <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-bold text-brand-700 fa-num" x-text="toPersianDigits(state.count)"></span>
            </h2>
            <button type="button" @click="close()" aria-label="بستن"
                    class="grid h-9 w-9 place-items-center rounded-full text-brand-400 transition hover:bg-brand-50 hover:text-brand-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        {{-- Just-added highlight --}}
        <template x-if="state.just_added">
            <div class="border-b border-brand-100 bg-green-50/60 px-5 py-3">
                <div class="flex items-center gap-3">
                    <div class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-brand-100">
                        <template x-if="state.just_added.image">
                            <img :src="state.just_added.image" :alt="state.just_added.name" class="h-full w-full object-cover">
                        </template>
                    </div>
                    <div class="min-w-0">
                        <p class="line-clamp-1 text-sm font-semibold text-brand-900" x-text="state.just_added.name"></p>
                        <p class="mt-0.5 text-xs text-brand-500" x-text="state.just_added.variant_label || ''"></p>
                    </div>
                </div>
            </div>
        </template>

        {{-- Top items --}}
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <template x-if="state.items && state.items.length">
                <div class="space-y-3">
                    <template x-for="(item, idx) in state.items" :key="idx">
                        <a :href="item.url || '#'" class="flex items-center gap-3 rounded-xl ring-1 ring-brand-100 p-3 transition hover:ring-brand-200">
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-brand-100">
                                <template x-if="item.image">
                                    <img :src="item.image" :alt="item.name" class="h-full w-full object-cover">
                                </template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-1 text-sm font-medium text-brand-800" x-text="item.name"></p>
                                <p class="mt-0.5 text-xs text-brand-400" x-text="(item.variant_label ? item.variant_label + ' · ' : '') + 'تعداد: ' + toPersianDigits(item.quantity)"></p>
                            </div>
                            <span class="shrink-0 text-sm font-bold text-brand-900 fa-num" x-text="item.line_total"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer: subtotal + CTAs --}}
        <div class="border-t border-brand-100 bg-brand-50/60 px-5 py-4">
            <div class="mb-3 flex items-center justify-between text-sm">
                <span class="text-brand-500">جمع کل</span>
                <span class="text-lg font-bold text-brand-900 fa-num" x-text="state.subtotal"></span>
            </div>
            <div class="flex gap-2">
                <a :href="state.checkout_url"
                   class="flex-1 rounded-full bg-brand-900 py-3 text-center text-sm font-semibold text-white transition hover:bg-brand-800">تسویه‌حساب</a>
                <a :href="state.cart_url"
                   class="rounded-full px-5 py-3 text-center text-sm font-semibold text-brand-700 ring-1 ring-brand-200 transition hover:bg-white">سبد خرید</a>
            </div>
            <button type="button" @click="close()"
                    class="mt-2 w-full py-2 text-xs text-brand-400 hover:text-brand-600">ادامه خرید</button>
        </div>
    </aside>
</div>

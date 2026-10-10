{{--
    Floating free-delivery tracker. Reuses the shipping methods' free_over
    threshold (free_over). Shows only when the cart has items; live-updates when
    an item is added (via the mini-cart's `open-mini-cart` event → subtotal_raw).
    Desktop: bottom-end corner pill. Mobile: lifted above the sticky add-to-cart
    bar. Dismissible for the session; re-shows when a new item is added.
--}}
@php
    $__fsThreshold = (int) (\App\Models\ShippingMethod::whereNotNull('free_over')->where('free_over', '>', 0)->min('free_over') ?? 0);
    $__fsCart = app(\App\Services\Cart\Cart::class);
@endphp
@if ($__fsThreshold > 0 && ! request()->routeIs('cart.*', 'checkout.*'))
    <div id="free-ship-widget"
         x-data="{
            threshold: {{ $__fsThreshold }},
            subtotal: {{ (int) $__fsCart->subtotal() }},
            count: {{ (int) $__fsCart->count() }},
            dismissed: sessionStorage.getItem('fsw_dismissed') === '1',
            get reached() { return this.subtotal >= this.threshold; },
            get remaining() { return Math.max(0, this.threshold - this.subtotal); },
            get pct() { return Math.min(100, Math.round(this.subtotal / this.threshold * 100)); },
            get remainingLabel() { return new Intl.NumberFormat('en-US').format(this.remaining) + ' Toman'; },
            get visible() { return !this.dismissed && this.count > 0; },
            dismiss() { this.dismissed = true; sessionStorage.setItem('fsw_dismissed', '1'); },
            init() {
                window.addEventListener('open-mini-cart', (e) => {
                    const d = e.detail || {};
                    if (typeof d.subtotal_raw === 'number') this.subtotal = d.subtotal_raw;
                    if (typeof d.count === 'number') this.count = d.count;
                    this.dismissed = false; sessionStorage.removeItem('fsw_dismissed');
                });
            }
         }"
         x-show="visible" x-cloak x-transition
         class="fixed bottom-20 end-3 z-40 w-[min(19rem,calc(100vw-1.5rem))] sm:bottom-4 sm:end-4">
        <div class="relative rounded-2xl bg-white p-3.5 shadow-xl ring-1 ring-brand-100">
            <button @click="dismiss()" aria-label="{{ __('Close') }}"
                    class="absolute top-2 start-2 grid h-6 w-6 place-items-center rounded-full text-brand-300 transition hover:bg-brand-50 hover:text-brand-600">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl transition-colors"
                      :class="reached ? 'bg-green-50 text-green-600' : 'bg-accent-600/10 text-accent-600'">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs leading-5 text-brand-700" x-show="!reached">
                        <span class="fa-num font-bold text-brand-900" x-text="remainingLabel"></span>
                        {{ __('away from') }} <span class="font-semibold">{{ __('free shipping') }}</span>
                    </p>
                    <p class="text-xs font-bold text-green-600" x-show="reached" x-cloak>{{ __('Free shipping unlocked') }}</p>
                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-brand-100">
                        <div class="h-full rounded-full transition-all duration-500"
                             :class="reached ? 'bg-green-500' : 'bg-accent-500'" :style="`width:${pct}%`"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

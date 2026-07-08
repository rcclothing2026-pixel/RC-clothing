@extends('layouts.app')

@section('title', 'Checkout | Racket Club')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6" x-data="{ submitting: false }">
        {{-- Progress stepper --}}
        <div class="mb-10 flex items-center justify-center gap-0 text-xs sm:text-sm">
            <div class="flex items-center">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-900 text-white">1</span>
                <span class="mr-2 font-medium text-brand-900">Bag</span>
            </div>
            <div class="mx-3 h-px w-10 bg-brand-300 sm:w-16"></div>
            <div class="flex items-center">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-900 text-white">2</span>
                <span class="mr-2 font-medium text-brand-900">Checkout</span>
            </div>
            <div class="mx-3 h-px w-10 bg-brand-300 sm:w-16"></div>
            <div class="flex items-center">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-200 text-brand-500">3</span>
                <span class="mr-2 text-brand-400">Confirmation</span>
            </div>
        </div>

        <form action="{{ route('checkout.place') }}" method="POST" class="grid gap-8 lg:grid-cols-[1fr_340px]" data-checkout
              x-on:submit="submitting = true"
              data-subtotal="{{ $subtotal }}" data-discount="{{ $combinedDiscount }}" data-freeship="{{ $freeShipping ? '1' : '0' }}"
              data-loyalty-tpp="{{ $loyalty['toman_per_point'] ?? 0 }}" data-loyalty-max="{{ $loyalty['points'] ?? 0 }}"
              data-gift-balance="{{ $giftCard?->balance ?? 0 }}" data-surcharge="{{ $surcharge }}">
            @csrf
            <div class="space-y-6">
                {{-- Address --}}
                <section class="rounded-card bg-white p-6 ring-1 ring-brand-100" x-data="{ mode: '{{ $addresses->isNotEmpty() ? 'saved' : 'new' }}' }">
                    <h2 class="mb-4 text-base font-bold text-brand-900">Delivery Address</h2>

                    @if ($addresses->isNotEmpty())
                        <div class="space-y-2">
                            @foreach ($addresses as $address)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-brand-200 p-3 text-sm has-[:checked]:border-brand-800 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="address_id" value="{{ $address->id }}" class="mt-1"
                                           data-province="{{ $address->province }}"
                                           x-on:change="mode = 'saved'"
                                           @checked($loop->first || $address->is_default)>
                                    <span>
                                        <span class="font-medium text-brand-800">{{ $address->recipient_name }} — <span class="fa-num" dir="ltr">{{ $address->phone }}</span></span>
                                        <span class="mt-1 block text-xs text-brand-500">{{ $address->province }}، {{ $address->city }} — {{ $address->line }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <button type="button" x-on:click="mode = (mode === 'new' ? 'saved' : 'new')"
                                class="mt-3 text-sm font-medium text-accent-600 hover:underline"
                                x-text="mode === 'new' ? 'Choose a saved address' : '+ New address'"></button>
                    @endif

                    <div x-show="mode === 'new'" x-transition x-cloak class="mt-3 grid grid-cols-2 gap-2"
                         x-data="{ province: '{{ old('province', $defaultProvince) }}', cities: [], loading: false, city: '{{ old('city', $defaultCity) }}' }"
                         x-init="$watch('province', async val => { if (!val) { cities = []; return }; loading = true; cities = await (await fetch('{{ url('checkout/cities') }}/' + encodeURIComponent(val))).json(); loading = false; if (cities.length && !cities.includes(city)) city = '' }); (province && !cities.length) && fetch('{{ url('checkout/cities') }}/' + encodeURIComponent(province)).then(r => r.json()).then(d => cities = d)">
                        {{-- These inputs only count when adding a new address. Disabling them
                             when a saved address is selected keeps their `required` from
                             blocking submit (a disabled field is skipped by validation) and
                             stops the hidden address_id=new from overriding the chosen one. --}}
                        <input type="hidden" name="address_id" x-ref="newAddr" value="new" :disabled="mode !== 'new'">
                        <input name="recipient_name" value="{{ old('recipient_name', $user->name) }}" :disabled="mode !== 'new'"
                               placeholder="Recipient name" class="rounded-lg border border-brand-200 px-3 py-2 text-sm" required>
                        <input name="phone" value="{{ old('phone', $user->phone) }}" dir="ltr" inputmode="numeric" :disabled="mode !== 'new'"
                               placeholder="Recipient mobile" class="rounded-lg border border-brand-200 px-3 py-2 text-sm" required>
                        <x-province-select name="province" value="{{ old('province', $defaultProvince) }}" required
                                           ::disabled="mode !== 'new'" x-on:change="province = $el.value" />
                        <div class="relative">
                            <select name="city" x-model="city" required :disabled="mode !== 'new'"
                                    class="w-full rounded-lg border border-brand-200 px-3 py-2.5 text-sm outline-none focus:border-brand-400">
                                <option value="">Select city</option>
                                <template x-for="c in cities" :key="c">
                                    <option x-text="c" :value="c"></option>
                                </template>
                            </select>
                            <span x-show="loading" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-brand-300 border-t-brand-600"></span>
                        </div>
                        <input name="postal_code" value="{{ old('postal_code') }}" dir="ltr" inputmode="numeric" :disabled="mode !== 'new'"
                               placeholder="Postal code (optional)" class="col-span-2 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <textarea name="line" placeholder="Full address" class="col-span-2 rounded-lg border border-brand-200 px-3 py-2 text-sm" required :disabled="mode !== 'new'">{{ old('line', $defaultAddress) }}</textarea>
                    </div>

                    @error('address_id')<p class="mt-2 text-xs text-red-500">{{ $message }}</p>@enderror
                </section>

                {{-- Customer note --}}
                <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                    <h2 class="mb-4 text-base font-bold text-brand-900">Note</h2>
                    <textarea name="customer_note" placeholder="Add a note for your order (optional)" rows="3"
                              class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm outline-none focus:border-brand-400">{{ old('customer_note') }}</textarea>
                </section>

                {{-- Gift wrap (rendered only when the admin has enabled it). Per-item
                     fee × cart-items count, becomes free once subtotal ≥ free-over. --}}
                @if (($site['site.giftwrap_enabled'] ?? null))
                    @php
                        $wrapPerItem = (int) ($site['site.giftwrap_per_item_price'] ?? 0);
                        $wrapFreeOver = (int) ($site['site.giftwrap_free_over'] ?? 0);
                        $wrapLabel = $site['site.giftwrap_label'] ?? '';
                        $itemCount = (int) $lines->sum('quantity');
                    @endphp
                    <section class="rounded-card bg-white p-6 ring-1 ring-brand-100"
                             x-data="{ wrap: {{ old('gift_wrap') ? 'true' : 'false' }} }"
                             data-giftwrap data-giftwrap-per-item="{{ $wrapPerItem }}" data-giftwrap-free-over="{{ $wrapFreeOver }}" data-items="{{ $itemCount }}">
                        <h2 class="mb-2 text-base font-bold text-brand-900">Gift Wrapping</h2>
                        @if ($wrapLabel)
                            <p class="mb-3 text-xs text-brand-500">{{ $wrapLabel }}</p>
                        @endif
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-brand-200 p-3 text-sm has-[:checked]:border-brand-800 has-[:checked]:bg-brand-50">
                            <input type="checkbox" name="gift_wrap" value="1" class="mt-1" x-model="wrap" data-giftwrap-toggle @checked(old('gift_wrap'))>
                            <span class="flex-1">
                                <span class="font-medium text-brand-800">Send my order gift-wrapped</span>
                                <span class="mt-1 block text-xs text-brand-500" data-giftwrap-price-hint>—</span>
                            </span>
                        </label>
                        <div x-show="wrap" x-cloak class="mt-3">
                            <label class="mb-1 block text-sm font-medium text-brand-700">Gift message (printed on the card — optional)</label>
                            <textarea name="gift_message" rows="3" maxlength="500" placeholder="e.g. Happy birthday..."
                                      class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm outline-none focus:border-brand-400">{{ old('gift_message') }}</textarea>
                        </div>
                    </section>
                @endif

                {{-- Shipping --}}
                <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                    <h2 class="mb-4 text-base font-bold text-brand-900">Shipping Method</h2>
                    <div class="space-y-2">
                        @foreach ($shippingMethods as $method)
                            <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-brand-200 p-3 text-sm has-[:checked]:border-brand-800 has-[:checked]:bg-brand-50">
                                <span class="flex items-center gap-3">
                                    <input type="radio" name="shipping_method_id" value="{{ $method->id }}"
                                           data-cost="{{ $method->costFor($subtotal) }}"
                                           data-base="{{ $method->price }}" data-free-over="{{ $method->free_over ?? 0 }}"
                                           data-cod="{{ $method->cost_on_delivery ? '1' : '0' }}"
                                           data-zones='@json($method->zones ?? new stdClass)' @checked($loop->first) required>
                                    <span>
                                        <span class="font-medium text-brand-800">{{ $method->name }}</span>
                                        @if ($method->cost_on_delivery)
                                            <span class="ms-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Pay on delivery</span>
                                        @endif
                                        @if ($method->description)<span class="block text-xs text-brand-400">{{ $method->description }}</span>@endif
                                    </span>
                                </span>
                                <span class="font-semibold text-brand-700">
                                    @if ($method->cost_on_delivery)
                                        Pay courier
                                    @else
                                        {{ $method->costFor($subtotal) > 0 ? \App\Support\Money::toman($method->costFor($subtotal)) : 'Free' }}
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('shipping_method_id')<p class="mt-2 text-xs text-red-500">{{ $message }}</p>@enderror
                </section>

                {{-- Payment method --}}
                <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                    <h2 class="mb-4 text-base font-bold text-brand-900">Payment Method</h2>
                    @if ($paymentMethods->isEmpty())
                        <p class="text-sm text-red-500">No payment gateway is active.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($paymentMethods as $pm)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-brand-200 p-3 text-sm has-[:checked]:border-brand-800 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="payment_method" value="{{ $pm->key }}" @checked($pm->is_default || $loop->first) required>
                                    <span>
                                        <span class="font-medium text-brand-800">{{ $pm->label }}</span>
                                        @if ($pm->description)<span class="block text-xs text-brand-400">{{ $pm->description }}</span>@endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('payment_method')<p class="mt-2 text-xs text-red-500">{{ $message }}</p>@enderror
                </section>
            </div>

            {{-- Summary --}}
            <aside class="h-fit rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">Order Summary</h2>
                <div class="max-h-48 space-y-2 overflow-auto border-b border-brand-100 pb-3">
                    @foreach ($lines as $line)
                        <div class="flex items-center justify-between text-xs text-brand-600">
                            <span class="line-clamp-1">{{ $line['variant']->product->name }} ×<span class="fa-num">{{ $line['quantity'] }}</span></span>
                            <span>{{ \App\Support\Money::toman($line['line_total'], false) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="space-y-2 py-3 text-sm" aria-live="polite">
                    <div class="flex justify-between"><span class="text-brand-500">Subtotal</span><span>{{ \App\Support\Money::toman($subtotal) }}</span></div>
                    @if ($coupon)
                        <div class="flex justify-between"><span class="text-green-600">Code "{{ $coupon->code }}"</span><span class="text-green-600">{{ $discount > 0 ? '−'.\App\Support\Money::toman($discount) : 'Free shipping' }}</span></div>
                    @endif
                    @foreach ($ruleDiscounts as $rd)
                        @php $rda = (int) ($rd['amount'] ?? 0); @endphp
                        @if ($rda > 0)
                            <div class="flex justify-between"><span class="text-green-600">{{ $rd['rule_name'] ?? 'Smart discount' }}</span><span class="text-green-600">−{{ \App\Support\Money::toman($rda) }}</span></div>
                        @elseif ($rda < 0)
                            <div class="flex justify-between"><span class="text-amber-600">{{ $rd['rule_name'] ?? 'Surcharge' }}</span><span class="text-amber-600">+{{ \App\Support\Money::toman(abs($rda)) }}</span></div>
                        @endif
                    @endforeach
                    @if ($loyalty && $loyalty['points'] >= $loyalty['min_redeem'] && $loyalty['toman_per_point'] > 0)
                        <div class="border-t border-brand-100 pt-2">
                            <label class="flex items-center justify-between text-sm">
                                <span class="text-brand-600">Club points (balance <span class="fa-num">{{ $loyalty['points'] }}</span>)</span>
                            </label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="number" name="redeem_points" min="0" max="{{ $loyalty['points'] }}" value="0" data-redeem inputmode="numeric"
                                       class="w-24 rounded-lg border border-brand-200 px-2 py-1.5 text-sm fa-num" placeholder="Points">
                                <span class="text-xs text-brand-400">equals <span data-redeem-value>0</span> Toman</span>
                            </div>
                        </div>
                        <div class="flex justify-between" data-loyalty-row style="display:none"><span class="text-green-600">Points discount</span><span class="text-green-600" data-loyalty-display>—</span></div>
                    @endif
                    <div class="flex justify-between"><span class="text-brand-500">Shipping</span><span data-shipping-display>—</span></div>
                    @if (($site['site.giftwrap_enabled'] ?? null))
                        <div class="flex justify-between" data-giftwrap-row style="display:none"><span class="text-brand-500">Gift wrapping</span><span data-giftwrap-display>—</span></div>
                    @endif
                    @if ($giftCard)
                        <div class="flex justify-between" data-gift-row style="display:none"><span class="text-green-600">Gift card</span><span class="text-green-600" data-gift-display>—</span></div>
                    @endif
                    <div class="flex justify-between border-t border-brand-100 pt-2 text-base font-bold"><span>Total</span><span class="text-brand-900" data-total-display>—</span></div>
                </div>
                <button type="submit" :disabled="submitting || {{ $paymentMethods->isEmpty() ? 'true' : 'false' }}"
                        class="flex w-full items-center justify-center gap-2 rounded-full bg-brand-900 py-3 text-sm font-semibold text-white transition hover:bg-brand-800 disabled:opacity-50">
                    <span x-show="!submitting">Pay & Place Order</span>
                    <span x-show="submitting" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                    <span x-show="submitting">Connecting to gateway...</span>
                </button>
                <div class="mt-3 flex items-center justify-center gap-2">
                    <svg aria-hidden="true" class="h-6 w-auto text-brand-300" viewBox="0 0 40 24" fill="currentColor"><rect width="40" height="24" rx="4"/><text x="20" y="16" text-anchor="middle" fill="white" font-size="8" font-weight="bold">Shaparak</text></svg>
                    <svg aria-hidden="true" class="h-6 w-auto text-brand-300" viewBox="0 0 40 24" fill="currentColor"><rect width="40" height="24" rx="4"/><text x="20" y="16" text-anchor="middle" fill="white" font-size="9" font-weight="bold">Sadad</text></svg>
                    <svg aria-hidden="true" class="h-6 w-auto text-brand-300" viewBox="0 0 40 24" fill="currentColor"><rect width="40" height="24" rx="4"/><text x="20" y="16" text-anchor="middle" fill="white" font-size="9" font-weight="bold">Mellat</text></svg>
                    <svg aria-hidden="true" class="h-6 w-auto text-brand-300" viewBox="0 0 40 24" fill="currentColor"><rect width="40" height="24" rx="4"/><text x="20" y="16" text-anchor="middle" fill="white" font-size="7" font-weight="bold">Pasargad</text></svg>
                </div>
                {{-- Reassurance strip at the decision point: cuts checkout anxiety
                     (secure gateway · return guarantee · real support). --}}
                <div class="mt-4 grid grid-cols-3 gap-2 border-t border-brand-100 pt-4 text-center">
                    <div class="flex flex-col items-center gap-1">
                        <svg aria-hidden="true" class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"/><path d="m9 12 2 2 4-4"/></svg>
                        <span class="text-[11px] leading-4 text-brand-500">Secure payment<br>trusted bank gateway</span>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <svg aria-hidden="true" class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9 9 0 0 0-7 3.5"/><path d="M3 3v4h4"/></svg>
                        <span class="text-[11px] leading-4 text-brand-500">7-day guarantee<br>easy returns</span>
                    </div>
                    <div class="flex flex-col items-center gap-1">
                        <svg aria-hidden="true" class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span class="text-[11px] leading-4 text-brand-500">Responsive<br>support</span>
                    </div>
                </div>
            </aside>
        </form>
    </div>

    <script>
        (function () {
            const root = document.querySelector('[data-checkout]');
            if (!root) return;
            const subtotal = parseInt(root.dataset.subtotal || '0', 10);
            const discount = parseInt(root.dataset.discount || '0', 10);
            const freeShip = root.dataset.freeship === '1';
            const tpp = parseFloat(root.dataset.loyaltyTpp || '0');
            const maxPts = parseInt(root.dataset.loyaltyMax || '0', 10);
            const shippingEl = root.querySelector('[data-shipping-display]');
            const totalEl = root.querySelector('[data-total-display]');
            const redeemEl = root.querySelector('[data-redeem]');
            const redeemValEl = root.querySelector('[data-redeem-value]');
            const loyRow = root.querySelector('[data-loyalty-row]');
            const loyEl = root.querySelector('[data-loyalty-display]');
            const fmt = (n) => new Intl.NumberFormat('en-US').format(n) + ' Toman';
            const faN = (n) => String(n);

            function loyaltyDiscount() {
                if (!redeemEl || tpp <= 0) return 0;
                let pts = Math.min(Math.max(0, parseInt(redeemEl.value || '0', 10)), maxPts);
                const cap = Math.max(0, subtotal - discount);
                return Math.min(Math.floor(pts * tpp), cap);
            }

            function selectedProvince() {
                const newProv = root.querySelector('[name="province"]');
                if (newProv && newProv.value) return newProv.value;
                const a = root.querySelector('input[name="address_id"]:checked');
                return a ? (a.dataset.province || '') : '';
            }
            function shippingIsCod() {
                const sel = root.querySelector('input[name="shipping_method_id"]:checked');
                return sel ? sel.dataset.cod === '1' : false;
            }
            function shippingCost() {
                const sel = root.querySelector('input[name="shipping_method_id"]:checked');
                if (!sel || freeShip) return 0;
                // Pay-on-delivery — courier collects on delivery; doesn't count toward online total.
                if (sel.dataset.cod === '1') return 0;
                const freeOver = parseInt(sel.dataset.freeOver || '0', 10);
                if (freeOver > 0 && subtotal >= freeOver) return 0;
                let zones = {};
                try { zones = JSON.parse(sel.dataset.zones || '{}'); } catch (e) {}
                const prov = selectedProvince();
                if (prov && zones[prov] != null) return parseInt(zones[prov], 10);
                return parseInt(sel.dataset.base || sel.dataset.cost || '0', 10);
            }

            // Gift-wrap fee = per-item × items count, free when subtotal ≥ free-over.
            // The price is recomputed server-side in CheckoutController::place too —
            // this is just for the live summary so the customer sees the change.
            const wrapSection = root.querySelector('[data-giftwrap]');
            const wrapToggle = root.querySelector('[data-giftwrap-toggle]');
            const wrapRow = root.querySelector('[data-giftwrap-row]');
            const wrapEl = root.querySelector('[data-giftwrap-display]');
            const wrapHint = root.querySelector('[data-giftwrap-price-hint]');
            function giftWrapCost() {
                if (!wrapSection || !wrapToggle?.checked) return 0;
                const perItem = parseInt(wrapSection.dataset.giftwrapPerItem || '0', 10);
                const freeOver = parseInt(wrapSection.dataset.giftwrapFreeOver || '0', 10);
                const items = parseInt(wrapSection.dataset.items || '0', 10);
                if (freeOver > 0 && subtotal >= freeOver) return 0;
                return Math.max(0, perItem * items);
            }
            function paintGiftWrapHint() {
                if (!wrapSection || !wrapHint) return;
                const perItem = parseInt(wrapSection.dataset.giftwrapPerItem || '0', 10);
                const freeOver = parseInt(wrapSection.dataset.giftwrapFreeOver || '0', 10);
                const items = parseInt(wrapSection.dataset.items || '0', 10);
                if (freeOver > 0 && subtotal >= freeOver) {
                    wrapHint.textContent = 'Free for this order.';
                } else {
                    wrapHint.textContent = fmt(perItem) + ' × ' + faN(String(items)) + ' items = ' + fmt(Math.max(0, perItem * items));
                }
            }

            function update() {
                let cost = shippingCost();
                const loy = loyaltyDiscount();
                const wrap = giftWrapCost();
                if (redeemValEl) redeemValEl.textContent = faN(loy.toLocaleString('en-US'));
                if (loyRow) loyRow.style.display = loy > 0 ? '' : 'none';
                if (loyEl) loyEl.textContent = '−' + fmt(loy);
                if (shippingEl) shippingEl.textContent = shippingIsCod() ? 'Pay on delivery' : (cost > 0 ? fmt(cost) : 'Free');
                if (wrapRow) wrapRow.style.display = wrapToggle?.checked ? '' : 'none';
                if (wrapEl) wrapEl.textContent = wrap > 0 ? fmt(wrap) : 'Free';
                const surcharge = parseInt(root.dataset.surcharge || '0', 10);
                let total = Math.max(0, subtotal + cost + surcharge + wrap - discount - loy);
                const giftBal = parseInt(root.dataset.giftBalance || '0', 10);
                const gift = Math.min(giftBal, total);
                const giftRow = root.querySelector('[data-gift-row]');
                const giftEl = root.querySelector('[data-gift-display]');
                if (giftRow) giftRow.style.display = gift > 0 ? '' : 'none';
                if (giftEl) giftEl.textContent = '−' + fmt(gift);
                if (totalEl) totalEl.textContent = fmt(Math.max(0, total - gift));
            }
            root.querySelectorAll('[name="shipping_method_id"], [name="address_id"], [name="province"]').forEach((el) =>
                el.addEventListener('change', update)
            );
            redeemEl?.addEventListener('input', update);
            wrapToggle?.addEventListener('change', update);
            paintGiftWrapHint();
            update();

            // Clear guidance for missing required fields. On a blocked
            // submit the browser fires `invalid` per field: we give each a precise
            // message (shown in the native prompt) and highlight every
            // missing field in red so the user sees exactly what to complete.
            const FA_MSG = {
                recipient_name: 'Please enter the recipient name.',
                phone: 'Please enter the recipient mobile number.',
                province: 'Please select a province.',
                city: 'Please select a city.',
                line: 'Please enter the full delivery address.',
                address_id: 'Please select or enter a delivery address.',
                shipping_method_id: 'Please select a shipping method.',
                payment_method: 'Please select a payment method.',
            };
            const mark = (el, on) => {
                el.style.borderColor = on ? '#ef4444' : '';
                el.style.boxShadow = on ? '0 0 0 2px rgba(239,68,68,.30)' : '';
            };
            root.querySelectorAll('[required]').forEach((el) => {
                el.addEventListener('invalid', () => {
                    el.setCustomValidity(FA_MSG[el.name] || 'This field is required.');
                    mark(el, true);
                });
                const clear = () => { el.setCustomValidity(''); mark(el, false); };
                el.addEventListener('input', clear);
                el.addEventListener('change', clear);
            });
        })();
    </script>
@endsection

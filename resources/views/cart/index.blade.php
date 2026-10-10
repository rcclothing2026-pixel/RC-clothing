@extends('layouts.app')

@section('title', __('Your Bag') . ' | Racket Club')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        <h1 class="mb-8 text-2xl font-bold text-brand-900">{{ __('Your Bag') }}</h1>

        @if ($lines->isEmpty())
            <x-empty-state
                icon="cart"
                title="{{ __('Your bag is empty') }}"
                caption="{{ __('You haven\'t added anything yet. Start with the shop and add your picks.') }}"
                :cta="['label' => __('Go to Shop'), 'href' => route('shop.index')]">
                @if ($recommended->isNotEmpty())
                    <div class="mt-10 border-t border-brand-100 pt-8 text-start">
                        <h3 class="mb-4 text-center text-sm font-semibold text-brand-700">Racket Club Picks</h3>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ($recommended as $product)
                                <x-product-card :product="$product" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-empty-state>
        @else
            <div class="grid gap-8 lg:grid-cols-[1fr_320px]">
                <div class="space-y-4">
                    @foreach ($lines as $line)
                        @php($variant = $line['variant'])
                        <div class="flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-brand-100 transition hover:ring-brand-200">
                            {{-- Product image --}}
                            <a href="{{ route('product.show', $variant->product) }}"
                               class="h-28 w-22 shrink-0 overflow-hidden rounded-xl bg-brand-50">
                                <img src="{{ $variant->product->primary_image_url ?? '/placeholder?w=200&h=250&label='.urlencode($variant->product->name) }}"
                                     alt="{{ $variant->product->name }}" class="h-full w-full object-cover">
                            </a>
                            {{-- Info --}}
                            <div class="flex min-w-0 flex-1 flex-col">
                                <a href="{{ route('product.show', $variant->product) }}"
                                   class="truncate text-sm font-semibold text-brand-800 transition hover:text-accent-600">{{ $variant->product->name }}</a>
                                <p class="mt-1 text-xs text-brand-400">
                                    @if ($variant->color)<span>{{ __('Color') }}: {{ $variant->color }}</span>@endif
                                    @if ($variant->color && $variant->size)<span class="mx-1">·</span>@endif
                                    @if ($variant->size)<span>{{ __('Size') }}: {{ $variant->size }}</span>@endif
                                </p>
                                <div class="mt-auto flex items-center justify-between gap-3 pt-3">
                                    {{-- +/- stepper --}}
                                    <form action="{{ route('cart.update') }}" method="POST" data-cart-stepper
                                          data-unit-price="{{ $variant->price }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                        <div class="flex items-center rounded-xl border border-brand-200">
                                            <button type="button" data-step="-1"
                                                    class="flex h-9 w-9 items-center justify-center rounded-r-xl text-brand-400 transition hover:bg-brand-50 hover:text-brand-900">
                                                <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14"/></svg>
                                            </button>
                                            <input type="number" name="quantity" value="{{ $line['quantity'] }}" min="0" max="20" inputmode="numeric"
                                                   class="w-10 border-0 bg-transparent text-center text-sm font-semibold text-brand-900 outline-none fa-num"
                                                   onchange="this.form.requestSubmit()">
                                            <button type="button" data-step="1"
                                                    class="flex h-9 w-9 items-center justify-center rounded-l-xl text-brand-400 transition hover:bg-brand-50 hover:text-brand-900">
                                                <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                                            </button>
                                        </div>
                                    </form>
                                    <span class="text-sm font-bold text-brand-900 fa-num" data-line-total>{{ \App\Support\Money::toman($line['line_total']) }}</span>
                                </div>
                            </div>
                            {{-- Remove --}}
                            <form action="{{ route('cart.remove') }}" method="POST" class="self-start">
                                @csrf @method('DELETE')
                                <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                <button class="grid h-8 w-8 place-items-center rounded-lg text-brand-300 transition hover:bg-red-50 hover:text-red-500" aria-label="{{ __('Remove') }}">
                                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-1 0v14H9V6"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>

                {{-- Summary --}}
                <aside class="h-fit rounded-card bg-white p-6 ring-1 ring-brand-100">
                    <h2 class="mb-4 text-base font-bold text-brand-900">{{ __('Order Summary') }}</h2>
                    <div class="flex items-center justify-between border-b border-brand-100 py-2 text-sm">
                        <span class="text-brand-500">{{ __('Subtotal') }}</span>
                        <span class="font-semibold text-brand-900">{{ \App\Support\Money::toman($subtotal) }}</span>
                    </div>

                    {{-- Free-shipping progress bar --}}
                    @if ($minFreeShipping && count($lines))
                        @php($pct = min(100, (int) round($subtotal / max(1, $minFreeShipping) * 100)))
                        <div class="border-b border-brand-100 py-3">
                            @if ($subtotal >= $minFreeShipping)
                                <div class="flex items-center gap-2 text-xs font-semibold text-green-600">
                                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
                                    <span>{{ __('Free shipping unlocked') }}</span>
                                </div>
                            @else
                                <p class="mb-2 text-xs text-brand-500">
                                    <span class="fa-num font-semibold text-brand-800">{{ \App\Support\Money::toman($minFreeShipping - $subtotal) }}</span> {{ __('away from free shipping') }}
                                </p>
                            @endif
                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-brand-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}">
                                <div class="h-full rounded-full {{ $subtotal >= $minFreeShipping ? 'bg-green-500' : 'bg-accent-500' }} transition-all duration-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if ($coupon)
                        <div class="flex items-center justify-between border-b border-brand-100 py-2 text-sm">
                            <span class="text-green-600">{{ __('Code') }} "{{ $coupon->code }}" {{ $coupon->isFreeShipping() ? '— '.__('free shipping') : '' }}</span>
                            <form action="{{ route('cart.coupon.remove') }}" method="POST">@csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-600">{{ __('Remove') }}</button>
                            </form>
                        </div>
                        @if ($discount > 0)
                            <div class="flex items-center justify-between border-b border-brand-100 py-2 text-sm">
                                <span class="text-brand-500">{{ __('Discount') }}</span>
                                <span class="font-semibold text-green-600">−{{ \App\Support\Money::toman($discount) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-2 text-sm font-bold">
                                <span class="text-brand-700">{{ __('After discount') }}</span>
                                <span class="text-brand-900">{{ \App\Support\Money::toman($subtotal - $discount) }}</span>
                            </div>
                        @endif
                    @else
                        <div x-data="{ code: '', loading: false, message: '', ok: null }" class="py-3">
                            <form @submit.prevent="if(code.trim()){ loading = true; message = ''; ok = null; fetch('{{ route('cart.coupon.apply') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ code }) }).then(r => r.json()).then(d => { ok = d.ok; message = d.message; loading = false; }).catch(() => { ok = false; message = '{{ __('Connection error') }}'; loading = false; }) }"
                                  class="flex gap-2">
                                @csrf
                                <input name="code" x-model="code" placeholder="{{ __('Promo code') }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm outline-none focus:border-brand-400" dir="ltr">
                                <button type="submit" :disabled="loading" class="rounded-lg bg-brand-100 px-4 text-sm font-medium text-brand-700 transition hover:bg-brand-200 disabled:opacity-50">
                                    <span x-show="!loading">{{ __('Apply') }}</span>
                                    <span x-show="loading" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-brand-400 border-t-transparent"></span>
                                </button>
                            </form>
                            <p x-show="message" x-text="message" x-cloak
                               :class="ok ? 'text-green-600' : 'text-red-500'" class="mt-1 text-xs" aria-live="polite"></p>
                        </div>
                    @endif

                    @foreach ($ruleDiscounts as $rd)
                        @php($amount = (int) ($rd['amount'] ?? 0))
                        @if ($amount > 0)
                            <div class="flex items-center justify-between border-b border-brand-100 py-2 text-sm">
                                <span class="text-green-600">{{ $rd['rule_name'] ?? __('Smart discount') }}</span>
                                <span class="font-semibold text-green-600">−{{ \App\Support\Money::toman($amount) }}</span>
                            </div>
                        @elseif ($amount < 0)
                            <div class="flex items-center justify-between border-b border-brand-100 py-2 text-sm">
                                <span class="text-amber-600">{{ $rd['rule_name'] ?? __('Surcharge') }}</span>
                                <span class="font-semibold text-amber-600">+{{ \App\Support\Money::toman(abs($amount)) }}</span>
                            </div>
                        @endif
                    @endforeach

                    {{-- Gift card --}}
                    @if ($giftCard)
                        <div class="flex items-center justify-between border-b border-brand-100 py-2 text-sm">
                            <span class="text-green-600">{{ __('Gift card') }} "{{ $giftCard->code }}" — {{ __('balance') }} {{ \App\Support\Money::toman($giftCard->balance) }}</span>
                            <form action="{{ route('cart.gift.remove') }}" method="POST">@csrf @method('DELETE')
                                <button class="text-xs text-red-400 hover:text-red-600">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    @else
                        <form action="{{ route('cart.gift.apply') }}" method="POST" class="flex gap-2 py-2">
                            @csrf
                            <input name="code" placeholder="{{ __('Gift card code') }}" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" dir="ltr">
                            <button class="rounded-lg bg-brand-100 px-4 text-sm font-medium text-brand-700">{{ __('Apply') }}</button>
                        </form>
                    @endif

                    <p class="py-3 text-xs text-brand-400">{{ __('Shipping is calculated at checkout.') }}</p>
                    <a href="{{ route('checkout.index') }}" class="block rounded-full bg-brand-900 py-3 text-center text-sm font-semibold text-white transition hover:bg-brand-800">{{ __('Proceed to Checkout') }}</a>
                </aside>
            </div>
        @endif
    </div>
@endsection

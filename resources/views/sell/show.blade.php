@extends('layouts.app')

@section('title', __('Your cart is ready') . ' | Racket Club')
@section('meta_description', __('The cart we put together for you in the DM — pay in one tap.'))
@php
    $firstImage = $linkVariants->first()?->product?->primary_image_url;
@endphp
@if ($firstImage)
    @section('og_image', $firstImage)
@endif

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    @php $guest = ! auth()->check(); $verifyFailed = $errors->has('code'); @endphp
    <div class="mx-auto max-w-lg px-4 pb-48 pt-6 sm:px-6"
         x-data="sellLogin({ open: {{ $verifyFailed ? 'true' : 'false' }}, step: {{ $verifyFailed ? "'code'" : "'phone'" }}, phone: @js(old('phone', '')) })">

        <h1 class="text-2xl font-bold text-brand-900">{{ __('Your cart is ready') }}</h1>
        <p class="mt-1 text-sm text-brand-600">{{ __('The items you chose in the DM, with size and colour.') }}</p>

        @if ($soldOut)
            <div class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200" role="status">
                {{ __('Sold out, not added to your cart:') }} {{ implode('، ', $soldOut) }}
            </div>
        @endif

        @if ($lines->isEmpty())
            <div class="mt-6 rounded-card bg-white p-6 text-center ring-1 ring-brand-100">
                <p class="text-sm text-brand-700">{{ __('Sorry, the items in this link are not available right now.') }}</p>
                <a href="{{ route('home') }}" class="mt-4 inline-flex h-12 items-center rounded-full bg-brand-900 px-6 text-sm font-bold text-white">{{ __('Browse more products') }}</a>
            </div>
        @else
            <div class="mt-5 space-y-3">
                @foreach ($lines as $line)
                    @php $v = $line['variant']; @endphp
                    <div class="flex items-center gap-3 rounded-card bg-white p-3 ring-1 ring-brand-100">
                        <a href="{{ route('product.show', $v->product) }}" class="block h-24 w-20 shrink-0 overflow-hidden rounded-card bg-brand-50">
                            @if ($v->product->primary_image_url)
                                <img src="{{ $v->product->primary_image_url }}" alt="{{ $v->product->name }}" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[15px] font-bold text-brand-900">{{ $v->product->name }}</p>
                            <p class="mt-0.5 text-sm text-brand-600">
                                {{ collect([$v->color, $v->size ? __('Size').' '.$v->size : null])->filter()->implode(' · ') }}
                                · <span class="fa-num">{{ \App\Support\Money::toPersianDigits((string) $line['quantity']) }}</span> {{ __('pcs') }}
                            </p>
                            <p class="mt-1 text-sm font-medium text-brand-800">{{ \App\Support\Money::toman($line['line_total']) }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('home') }}"
               class="mt-4 flex h-12 items-center justify-center gap-2 rounded-lg border border-dashed border-brand-300 text-sm font-bold text-brand-800 transition hover:bg-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                {{ __('Add more from the shop') }}
            </a>
            <p class="mt-2 text-center text-xs text-brand-500">{{ __('Your cart is kept — come back and pay whenever you like.') }}</p>

            {{-- Sticky summary + pay. Above the mobile bottom nav and the cookie bar, like the product page's buy bar. --}}
            <div class="fixed inset-x-0 bottom-0 z-[60] border-t border-brand-100 bg-white px-4 pt-3 shadow-[0_-8px_24px_rgba(0,0,0,.08)]"
                 style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                <div class="mx-auto max-w-lg space-y-1.5 text-sm">
                    <div class="flex justify-between text-brand-600"><span>{{ __('Items') }}</span><span>{{ \App\Support\Money::toman($subtotal) }}</span></div>
                    @if ($discount > 0)
                        <div class="flex justify-between text-green-700"><span>{{ __('Discount') }}{{ $coupon ? ' '.$coupon->code : '' }}</span><span>−{{ \App\Support\Money::toman($discount) }}</span></div>
                    @endif
                    <div class="flex justify-between text-xs text-brand-500"><span>{{ __('Shipping') }}</span><span>{{ __('Next step') }}</span></div>
                    @if ($guest)
                        <button type="button" @click="open = true; $nextTick(() => $refs.phone?.focus())"
                                class="mt-2 flex h-14 w-full items-center justify-between rounded-lg bg-accent-600 px-5 text-base font-bold text-white transition hover:bg-accent-700">
                            <span>{{ __('Continue to payment') }}</span><span class="text-sm">{{ \App\Support\Money::toman($total) }}</span>
                        </button>
                    @else
                        <a href="{{ route('checkout.index') }}"
                           class="mt-2 flex h-14 w-full items-center justify-between rounded-lg bg-accent-600 px-5 text-base font-bold text-white transition hover:bg-accent-700">
                            <span>{{ __('Continue to payment') }}</span><span class="text-sm">{{ \App\Support\Money::toman($total) }}</span>
                        </a>
                    @endif
                </div>
            </div>
        @endif

        @if ($guest && $lines->isNotEmpty())
            {{-- Inline SMS login: first purchase only; returning customers stay logged in. --}}
            <div x-show="open" x-cloak class="fixed inset-0 z-[70] bg-brand-950/50" @click.self="open = false" @keydown.escape.window="open = false">
                <section role="dialog" aria-modal="true" aria-label="{{ __('Log in with your mobile number') }}"
                         class="absolute inset-x-0 bottom-0 mx-auto max-w-lg rounded-t-2xl bg-white px-5 pt-3 shadow-2xl"
                         style="padding-bottom: max(1.75rem, env(safe-area-inset-bottom))">
                    <div class="mx-auto mb-4 h-1.5 w-10 rounded-full bg-brand-200"></div>
                    <h2 class="text-xl font-bold text-brand-900">{{ __('Your mobile number, to pay') }}</h2>
                    <p class="mt-1 text-sm text-brand-600">{{ __('First time only. Next time you go straight to payment.') }}</p>

                    <form x-show="step === 'phone'" @submit.prevent="send" class="mt-5 space-y-4">
                        <label class="block text-xs font-bold text-brand-800">{{ __('Mobile number') }}
                            <input x-ref="phone" x-model="phone" type="tel" inputmode="numeric" autocomplete="tel" dir="ltr" required
                                   placeholder="09xxxxxxxxx"
                                   class="mt-1.5 h-14 w-full rounded-lg border border-brand-200 px-4 text-left text-lg font-medium outline-none focus:border-brand-600">
                        </label>
                        <p x-show="error" x-text="error" class="text-sm text-red-600" role="alert"></p>
                        <button type="submit" :disabled="busy"
                                class="flex h-14 w-full items-center justify-center rounded-lg bg-accent-600 text-base font-bold text-white transition hover:bg-accent-700 disabled:opacity-60">
                            <span x-text="busy ? @js(__('Sending…')) : @js(__('Send code'))"></span>
                        </button>
                    </form>

                    <form x-show="step === 'code'" x-cloak action="{{ route('login.verify') }}" method="POST" class="mt-5 space-y-4" @submit="busy = true">
                        @csrf
                        <input type="hidden" name="phone" :value="phone">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-brand-700">{{ __('Code sent to') }} <span dir="ltr" class="font-bold" x-text="phone"></span></span>
                            <button type="button" @click="step = 'phone'; error = ''" class="min-h-11 px-2 text-sm font-bold text-accent-600">{{ __('Edit') }}</button>
                        </div>
                        <label class="block text-xs font-bold text-brand-800">{{ __('Code from the SMS') }}
                            <input x-ref="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" required
                                   maxlength="{{ (int) config('sms.otp_length') }}"
                                   class="mt-1.5 h-14 w-full rounded-lg border-2 border-brand-600 px-4 text-center text-2xl font-bold tracking-[0.5em] outline-none">
                        </label>
                        @error('code')<p class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
                        <p x-show="devCode" class="text-xs text-brand-500">{{ __('Dev code (log mode):') }} <span class="font-bold" x-text="devCode"></span></p>
                        <div class="flex justify-between text-xs text-brand-500">
                            <span>{{ __('Your phone suggests the code from the SMS') }}</span>
                            <button type="button" @click="send" :disabled="busy" class="font-bold text-accent-600 disabled:opacity-50">{{ __('Resend') }}</button>
                        </div>
                        <button type="submit" :disabled="busy"
                                class="flex h-14 w-full items-center justify-center rounded-lg bg-accent-600 text-base font-bold text-white transition hover:bg-accent-700 disabled:opacity-60">
                            {{ __('Confirm and continue') }}
                        </button>
                    </form>
                </section>
            </div>

            <script>
                window.sellLogin = (init) => ({
                    ...init, busy: false, error: '', devCode: '',
                    async send() {
                        this.busy = true; this.error = '';
                        try {
                            const res = await fetch(@js(route('login.otp')), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json' },
                                body: new URLSearchParams({ phone: this.phone }),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                this.error = data.error || data.message || @js(__('Could not send the code. Please try again.'));
                                return;
                            }
                            this.devCode = data.dev_code || '';
                            this.step = 'code';
                            this.$nextTick(() => this.$refs.code?.focus());
                        } catch (e) {
                            this.error = @js(__('Connection failed. Please try again.'));
                        } finally {
                            this.busy = false;
                        }
                    },
                });
            </script>
        @else
            <script>window.sellLogin = (init) => init;</script>
        @endif
    </div>
@endsection

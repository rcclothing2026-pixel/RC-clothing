@extends('layouts.app')

@section('title', __('Payment Failed') . ' | Racket Club')

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center sm:px-6">
        <div class="rounded-card bg-white p-10 ring-1 ring-brand-100">
            <div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-full bg-red-100 text-red-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </div>
            @if (($retryUrl ?? null) || ($unclear ?? false))
                <h1 class="text-xl font-bold text-brand-900">{{ __('Payment not confirmed yet') }}</h1>
                <p class="mt-2 text-sm text-brand-500">{{ __('Order') }} <span class="font-semibold fa-num" dir="ltr">{{ $order->number }}</span> {{ __('could not be confirmed by the payment gateway yet. If money left your account, do not pay again: check again in a minute. If it is not confirmed, the amount is returned to you.') }}</p>
            @else
                <h1 class="text-xl font-bold text-brand-900">{{ __('Payment failed') }}</h1>
                <p class="mt-2 text-sm text-brand-500">{{ __('Order') }} <span class="font-semibold fa-num" dir="ltr">{{ $order->number }}</span> {{ __('was not paid. No amount has been charged to your account.') }}</p>
            @endif

            <div class="mt-8 flex justify-center gap-3">
                @if ($retryUrl ?? null)
                    <a href="{{ $retryUrl }}" class="rounded-full bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">{{ __('Check again') }}</a>
                @endif
                <a href="{{ route('cart.index') }}" class="rounded-full bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">{{ __('Back to Bag') }}</a>
                <a href="{{ route('home') }}" class="rounded-full px-6 py-2.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50">{{ __('Home') }}</a>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Payment Failed | Racket Club')

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center sm:px-6">
        <div class="rounded-card bg-white p-10 ring-1 ring-brand-100">
            <div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-full bg-red-100 text-red-600">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </div>
            <h1 class="text-xl font-bold text-brand-900">Payment failed</h1>
            <p class="mt-2 text-sm text-brand-500">Order <span class="font-semibold fa-num" dir="ltr">{{ $order->number }}</span> was not paid. No amount has been charged to your account.</p>

            <div class="mt-8 flex justify-center gap-3">
                <a href="{{ route('cart.index') }}" class="rounded-full bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Back to Bag</a>
                <a href="{{ route('home') }}" class="rounded-full px-6 py-2.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50">Home</a>
            </div>
        </div>
    </div>
@endsection

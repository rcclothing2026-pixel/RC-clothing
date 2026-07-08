@extends('layouts.app')

@section('title', 'Terms | Racket Club')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">TERMS &amp; CONDITIONS</h1>
        <div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
            <p>By placing an order with Racket Club, you agree to the terms below. They exist for clarity and to protect both sides.</p>
            <h2 class="pt-2 font-bold text-brand-900">1. Placing an Order</h2>
            <p>Prices are shown in Toman and include applicable tax. Once payment succeeds, your order is confirmed and sent for processing.</p>
            <h2 class="pt-2 font-bold text-brand-900">2. Pricing &amp; Availability</h2>
            <p>Prices and stock may change; the details at the moment you order are what apply. If an item sells out after payment, we will refund you in full.</p>
            <h2 class="pt-2 font-bold text-brand-900">3. Shipping</h2>
            <p>Delivery time and cost depend on the method you choose. Full details are on our shipping and returns page.</p>
            <h2 class="pt-2 font-bold text-brand-900">4. Privacy</h2>
            <p>Your information is protected in line with our <a href="{{ route('page', 'privacy') }}" class="text-accent-600 hover:underline">privacy policy</a>.</p>
        </div>
    </div>
@endsection

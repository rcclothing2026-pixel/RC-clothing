@extends('layouts.app')

@section('title', __('FAQ').' | Racket Club')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">{{ __('FREQUENTLY ASKED') }}</h1>
        <div class="mt-8 space-y-4">
            @foreach ([
                [__('How do I place an order?'), __('Add the piece you love to your cart, choose your size and colour, then enter your address and preferred delivery method at checkout.')],
                [__('How does payment work?'), __('Payment is handled securely online through trusted banking gateways. Your card details are never stored on our servers.')],
                [__('How long will delivery take?'), __('Depending on the method you choose, orders typically arrive within 1 to 4 working days.')],
                [__('What if the size is not right?'), __('You can return or exchange any unworn item within 7 days. Full details are on our shipping and returns page.')],
                [__('Is the stock shown accurate?'), __('Yes. Availability syncs live with our warehouse, and sizes that are out of stock appear disabled.')],
            ] as [$q, $a])
                <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
                    <summary class="cursor-pointer text-sm font-semibold text-brand-800">{{ $q }}</summary>
                    <p class="mt-3 text-sm leading-7 text-brand-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
@endsection

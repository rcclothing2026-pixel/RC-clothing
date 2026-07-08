@extends('layouts.app')

@section('title', 'FAQ | Racket Club')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">FREQUENTLY ASKED</h1>
        <div class="mt-8 space-y-4">
            @foreach ([
                ['How do I place an order?', 'Add the piece you love to your cart, choose your size and colour, then enter your address and preferred delivery method at checkout.'],
                ['How does payment work?', 'Payment is handled securely online through trusted banking gateways. Your card details are never stored on our servers.'],
                ['How long will delivery take?', 'Depending on the method you choose, orders typically arrive within 1 to 4 working days.'],
                ['What if the size is not right?', 'You can return or exchange any unworn item within 7 days. Full details are on our shipping and returns page.'],
                ['Is the stock shown accurate?', 'Yes. Availability syncs live with our warehouse, and sizes that are out of stock appear disabled.'],
            ] as [$q, $a])
                <details class="rounded-card bg-white p-5 ring-1 ring-brand-100">
                    <summary class="cursor-pointer text-sm font-semibold text-brand-800">{{ $q }}</summary>
                    <p class="mt-3 text-sm leading-7 text-brand-600">{{ $a }}</p>
                </details>
            @endforeach
        </div>
    </div>
@endsection

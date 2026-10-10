@extends('layouts.app')

@section('title', __('Shipping & Returns').' | Racket Club')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">{{ __('SHIPPING & RETURNS') }}</h1>
        <div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
            <h2 class="pt-2 font-bold text-brand-900">{{ __('Shipping') }}</h2>
            <p>{{ __('Orders are prepared and dispatched once payment is confirmed. You can choose your delivery method and see its cost at checkout, and shipping is complimentary on orders above a set threshold.') }}</p>
            <h2 class="pt-2 font-bold text-brand-900">{{ __('Returns & Exchanges') }}</h2>
            <p>{{ __('You may return or exchange any item within 7 days of delivery, provided it is unworn and still carries its original tags. To begin, let us know via the') }} <a href="{{ route('contact') }}" class="text-accent-600 hover:underline">{{ __('contact page') }}</a>.</p>
            <h2 class="pt-2 font-bold text-brand-900">{{ __('Return Conditions') }}</h2>
            <ul class="list-inside list-disc space-y-1">
                <li>{{ __('Items must be unworn and returned with their original packaging and tags.') }}</li>
                <li>{{ __('Where the fault is ours, we cover the cost of return.') }}</li>
                <li>{{ __('Refunds are issued within 72 working hours once the item has been inspected.') }}</li>
            </ul>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', __('Privacy').' | Racket Club')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">{{ __('PRIVACY') }}</h1>
        <div class="mt-6 space-y-4 text-sm leading-8 text-brand-700">
            <p>{{ __('Your privacy matters to us. This page explains what we collect, and why.') }}</p>
            <h2 class="pt-2 font-bold text-brand-900">{{ __('What We Collect') }}</h2>
            <p>{{ __('Your name, contact number and address, so we can process and deliver your order. Payment details are handled solely by the banking gateway and are never stored on our servers.') }}</p>
            <h2 class="pt-2 font-bold text-brand-900">{{ __('How We Use It') }}</h2>
            <p>{{ __('Only to fulfil your order, provide support and — if you choose to hear from us — share news of new pieces. We never sell your information to third parties.') }}</p>
            <h2 class="pt-2 font-bold text-brand-900">{{ __('Security') }}</h2>
            <p>{{ __('Our connection is encrypted (HTTPS), and access to your data is limited and carefully controlled.') }}</p>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', __('This purchase link has expired') . ' | Racket Club')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center sm:px-6">
        <div class="rounded-card bg-white p-8 ring-1 ring-brand-100">
            <h1 class="text-xl font-bold text-brand-900">{{ __('This purchase link has expired') }}</h1>
            <p class="mt-2 text-sm text-brand-600">{{ __('Message us in the DM for a new link, or browse the shop.') }}</p>
            <a href="{{ route('home') }}" class="mt-6 inline-flex h-12 items-center rounded-full bg-brand-900 px-6 text-sm font-bold text-white">{{ __('Browse products') }}</a>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', __('Unsubscribed').' | Racket Club')

@section('content')
    <div class="mx-auto max-w-lg px-4 py-20 text-center">
        <div class="rounded-card bg-white p-10 ring-1 ring-brand-100">
            <h1 class="text-xl font-bold uppercase tracking-wide text-brand-900">{{ __("YOU'RE UNSUBSCRIBED") }}</h1>
            <p class="mt-3 text-sm text-brand-500">{{ __("You've been removed from the Racket Club newsletter. If you have any thoughts to share, we'd love to hear them — and you're always welcome back.") }}</p>
            <a href="{{ route('shop.index') }}" class="mt-6 inline-block rounded-lg bg-brand-900 px-6 py-2 text-sm font-semibold text-white">{{ __('Back to the Shop') }}</a>
        </div>
    </div>
@endsection

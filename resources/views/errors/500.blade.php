@extends('layouts.app')

@section('title', __('Server Error').' | Racket Club')

@section('content')
    <div class="mx-auto flex max-w-md flex-col items-center px-4 py-24 text-center">
        <x-brand-pattern class="mx-auto mb-4 h-20 w-20 text-brand-100" />
        <p class="text-6xl font-black text-brand-200 fa-num">500</p>
        <h1 class="mt-4 text-xl font-bold uppercase tracking-wide text-brand-900">{{ __('SOMETHING WENT WRONG') }}</h1>
        <p class="mt-2 text-sm text-brand-500">{{ __('Please try again in a moment. If the problem persists, get in touch with our team.') }}</p>
        <div class="mt-6 flex gap-3">
            <a href="{{ route('home') }}" class="rounded-full bg-brand-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">{{ __('Back to Home') }}</a>
            <a href="{{ route('page', 'contact') }}" class="rounded-full border border-brand-200 px-5 py-2.5 text-sm font-semibold text-brand-700 transition hover:bg-brand-50">{{ __('Contact Support') }}</a>
        </div>
    </div>
@endsection

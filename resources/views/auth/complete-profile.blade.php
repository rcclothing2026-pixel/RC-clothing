@extends('layouts.app')

@section('title', 'Complete Your Profile | Racket Club')

@section('content')
    <div class="mx-auto flex max-w-md flex-col justify-center px-4 py-16">
        <div class="rounded-card bg-white p-8 text-center ring-1 ring-brand-100">
            <h1 class="text-xl font-bold uppercase tracking-wide text-brand-900">WELCOME TO THE CLUB</h1>
            <p class="mt-2 text-sm leading-7 text-brand-500">Tell us your name to complete your profile and make every visit your own.</p>

            <form method="POST" action="{{ route('profile.complete.store') }}" class="mt-6 space-y-4 text-left">
                @csrf
                <div>
                    <label class="mb-1 block text-sm text-brand-600">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required autofocus
                           class="w-full rounded-lg border border-brand-200 px-3 py-2.5 text-sm outline-none focus:border-brand-400">
                </div>
                <div>
                    <label class="mb-1 block text-sm text-brand-600">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                           class="w-full rounded-lg border border-brand-200 px-3 py-2.5 text-sm outline-none focus:border-brand-400">
                </div>
                @error('first_name')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                @error('last_name')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                <button class="w-full rounded-lg bg-brand-900 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">Continue</button>
            </form>
        </div>
    </div>
@endsection

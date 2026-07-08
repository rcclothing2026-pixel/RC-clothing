@extends('layouts.app')

@section('title', __('Sign In') . ' | Racket Club')

@section('content')
    <div class="flex min-h-[80vh] items-center justify-center px-4 py-16 sm:px-6">
        <div class="w-full max-w-sm">

            {{-- Brand header --}}
            <div class="mb-8 text-center">
                <a href="{{ route('home') }}" class="inline-block">
                    <x-brand-logo class="mx-auto text-brand-900" />
                </a>
                <h1 class="mt-4 text-xl font-bold uppercase tracking-wide text-brand-900">{{ __('WELCOME BACK') }}</h1>
                <p class="mt-1.5 text-sm text-brand-500">{{ __("Enter your mobile number and we'll send a verification code.") }}</p>
            </div>

            <div class="rounded-2xl bg-white p-7 shadow-sm ring-1 ring-brand-100">
                @if (session('status'))
                    <div class="mb-5 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200">{{ session('status') }}</div>
                @endif

                <form action="{{ route('login.otp') }}" method="POST" class="space-y-5">
                    @csrf
                    <div>
                        <label for="phone" class="mb-1.5 block text-sm font-medium text-brand-700">{{ __('Mobile Number') }}</label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="0912 *** ****" dir="ltr"
                               inputmode="numeric"
                               class="w-full rounded-xl border border-brand-200 py-3 text-center text-base tracking-widest outline-none transition placeholder:tracking-normal placeholder:text-brand-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-100 @error('phone') border-red-300 bg-red-50 @enderror">
                        @error('phone')
                            <p class="mt-1.5 flex items-center gap-1 text-xs text-red-500">
                                <svg aria-hidden="true" class="h-3.5 w-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" clip-rule="evenodd"/></svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                    <button type="submit"
                            class="w-full rounded-xl bg-brand-900 py-3.5 text-sm font-bold text-white transition hover:bg-brand-800 active:scale-[0.98]">
                        {{ __('Send Code') }}
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-brand-400 leading-6">
                    {{ __("By signing in, you agree to Racket Club's") }} <a href="{{ route('page', 'terms') }}" class="text-accent-600 hover:underline">{{ __('Terms & Conditions') }}</a>.
                </p>
            </div>

            <div class="mt-4 text-center">
                <a href="{{ route('shop.index') }}" class="text-sm text-brand-400 transition hover:text-brand-600">{{ __('Continue as guest — browse the shop') }} &rarr;</a>
            </div>

            {{-- Trust row --}}
            <div class="mt-6 flex items-center justify-center gap-5 text-xs text-brand-400">
                <span class="flex items-center gap-1.5">
                    <svg aria-hidden="true" class="h-4 w-4 text-brand-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    {{ __('Secure & private') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <svg aria-hidden="true" class="h-4 w-4 text-brand-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                    {{ __('No password') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <svg aria-hidden="true" class="h-4 w-4 text-brand-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0zM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    {{ __('Quick sign-in') }}
                </span>
            </div>
        </div>
    </div>
@endsection

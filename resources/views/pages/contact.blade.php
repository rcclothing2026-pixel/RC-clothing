@extends('layouts.app')

@section('title', __('Contact').' | Racket Club')

@section('content')
    @php
        $bot = app(\App\Services\Telegram\TelegramNotifier::class)->botUsername();
        $phone = \App\Models\Setting::get('site.contact_phone');
        $email = \App\Models\Setting::get('site.contact_email');
        $address = \App\Models\Setting::get('site.address');
        $ig = \App\Models\Setting::get('site.instagram');
        $wa = \App\Models\Setting::get('site.whatsapp');
        // Normalize Iranian mobile for wa.me (mirrors the admin inbox helper).
        $waLink = null;
        if ($wa) {
            $d = preg_replace('/\D+/', '', (string) $wa);
            if (str_starts_with($d, '0098')) { $d = '98'.substr($d, 4); }
            elseif (str_starts_with($d, '09') && strlen($d) === 11) { $d = '98'.substr($d, 1); }
            $waLink = $d ? 'https://wa.me/'.$d : null;
        }
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-2xl font-bold uppercase tracking-wide text-brand-900">{{ __('GET IN TOUCH') }}</h1>
        <p class="mt-2 text-sm leading-7 text-brand-600">
            {{ __('The Racket Club team is on hand for questions, order updates and anything else you need. Send us a message here — we usually reply within a few hours.') }}
        </p>

        {{-- Primary CTA: open the bot in Telegram with a contact-flow start payload. --}}
        @if ($bot)
            <a href="https://t.me/{{ $bot }}?start=contact" target="_blank" rel="noopener"
               class="mt-8 flex items-center justify-center gap-2 rounded-2xl bg-[#229ED9] px-6 py-4 text-base font-bold text-white shadow-sm transition hover:bg-[#1f8fc4]">
                <svg aria-hidden="true" class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                <span>{{ __('Chat with us on Telegram') }}</span>
            </a>
            <p class="mt-2 text-center text-xs text-brand-400">{{ __('Tap the button, then press "Start" in Telegram to begin.') }}</p>
        @endif

        {{-- Fallback channels. Customers who don't use Telegram still have a way through. --}}
        @if ($waLink || $phone || $email || $ig)
            <div class="mt-10">
                <p class="mb-3 text-center text-xs text-brand-400">{{ __('Or reach us through any of these:') }}</p>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    @if ($waLink)
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-green-50 px-4 py-2 text-sm font-medium text-green-700 ring-1 ring-green-200 hover:bg-green-100">
                            WhatsApp
                        </a>
                    @endif
                    @if ($phone)
                        <a href="tel:{{ $phone }}" class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-100" dir="ltr">
                            {{ $phone }}
                        </a>
                    @endif
                    @if ($email)
                        <a href="mailto:{{ $email }}" class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-100" dir="ltr">
                            {{ $email }}
                        </a>
                    @endif
                    @if ($ig)
                        <a href="https://instagram.com/{{ ltrim((string) $ig, '@') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full bg-gradient-to-tr from-purple-50 to-pink-50 px-4 py-2 text-sm font-medium text-pink-700 ring-1 ring-pink-200 hover:from-purple-100 hover:to-pink-100">
                            Instagram
                        </a>
                    @endif
                </div>
            </div>
        @endif

        @if ($address)
            <div class="mt-10 rounded-card bg-brand-50 p-5 text-center text-sm text-brand-700">
                <span class="text-xs text-brand-400">{{ __('Address:') }}</span>
                <p class="mt-1">{{ $address }}</p>
            </div>
        @endif
    </div>
@endsection

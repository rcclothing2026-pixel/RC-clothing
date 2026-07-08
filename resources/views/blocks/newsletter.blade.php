@php($channel = $data['channel'] ?? 'telegram')
@php($containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-5xl', 'full' => 'max-w-none', default => 'max-w-3xl' })
@php($padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-6', 'lg' => 'py-20 sm:py-28', default => 'py-12' })
<section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
    <div class="rounded-card bg-brand-900 px-6 py-12 text-center">
        <h2 class="text-2xl font-bold text-white">{{ ($data['heading'] ?? null) ?: 'Join the Club' }}</h2>
        @if (!empty($data['text']))
            <p class="mx-auto mt-3 max-w-md text-sm leading-7 text-white/80">{{ $data['text'] }}</p>
        @endif

        @if ($channel === 'email')
            <form action="{{ route('subscriber.store') }}" method="POST" class="mx-auto mt-6 flex max-w-sm gap-2">
                @csrf
                <input type="hidden" name="name" value="">
                <input type="email" name="email" required placeholder="Your email"
                       class="flex-1 rounded-full border-0 px-4 py-3 text-sm text-brand-900" dir="ltr">
                <button class="rounded-full bg-accent-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-accent-700">Subscribe</button>
            </form>
        @elseif ($tg = ($site['site.telegram'] ?? null))
            {{-- channel = telegram OR sms (sms fallback) — both lead to the Telegram channel
                 now that the contact-form endpoint is retired. Customers register via Telegram. --}}
            <a href="https://t.me/{{ ltrim($tg, '@') }}" target="_blank" rel="noopener"
               class="mt-6 inline-block rounded-full bg-white px-7 py-3 text-sm font-semibold text-brand-900 transition hover:bg-brand-100">Join our Telegram channel</a>
        @endif
    </div>
</section>

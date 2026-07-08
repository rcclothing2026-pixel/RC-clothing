{{--
    Countdown banner — live timer to a specified end-date (Tehran timezone).
    Silently hides itself once the deadline passes so admins don't ship a
    "00:00:00" promo when they forget to update it.
--}}
@php
    $endsRaw = trim((string) ($data['ends_at'] ?? ''));
    $endsAt = null;
    if ($endsRaw !== '') {
        try {
            $endsAt = \Carbon\Carbon::parse($endsRaw, 'Asia/Tehran');
        } catch (\Throwable $e) {
            $endsAt = null;
        }
    }
@endphp

@if ($endsAt && $endsAt->isFuture())
    @php
        $style = $data['style'] ?? 'grad-dark-red';
        $bg = match ($style) {
            'grad-red-light' => 'bg-accent-600 text-white',
            'dark' => 'bg-brand-900 text-white',
            'amber' => 'bg-amber-100 text-amber-900',
            default => 'bg-brand-900 text-white',
        };
        $chipBg = $style === 'amber' ? 'bg-white/80 text-amber-900' : 'bg-white/15 text-white';
        $subText = $style === 'amber' ? 'text-amber-800/80' : 'text-white/80';
    @endphp
    <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
        <div class="rounded-card {{ $bg }} px-6 py-8 sm:px-10"
             x-data="{
                end: new Date('{{ $endsAt->toIso8601String() }}').getTime(),
                d:0, h:0, m:0, s:0, visible:true,
                tick() {
                    const diff = this.end - Date.now();
                    if (diff <= 0) { this.visible = false; return }
                    this.d = Math.floor(diff / 86400000);
                    this.h = Math.floor(diff % 86400000 / 3600000);
                    this.m = Math.floor(diff % 3600000 / 60000);
                    this.s = Math.floor(diff % 60000 / 1000);
                },
                fa(n) { return String(n).padStart(2,'0') },
             }"
             x-init="tick(); setInterval(() => tick(), 1000)"
             x-show="visible">
            <div class="flex flex-col items-center gap-5 text-center sm:flex-row sm:justify-between sm:text-start">
                <div>
                    @if ($heading = ($data['heading'] ?? null))
                        <h2 class="text-xl font-bold sm:text-2xl">{{ $heading }}</h2>
                    @endif
                    @if ($sub = ($data['subtitle'] ?? null))
                        <p class="mt-1 text-sm {{ $subText }}">{{ $sub }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-2 fa-num" dir="ltr">
                    @foreach (['d' => 'Days', 'h' => 'Hours', 'm' => 'Mins', 's' => 'Secs'] as $key => $label)
                        <div class="flex min-w-[3.25rem] flex-col items-center rounded-xl {{ $chipBg }} px-3 py-2">
                            <span class="text-2xl font-bold leading-none" x-text="fa({{ $key }})">00</span>
                            <span class="mt-1 text-[10px] {{ $subText }}">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>

                @if (($ctaText = ($data['cta_text'] ?? null)) && ($ctaLink = ($data['cta_link'] ?? null)))
                    <a href="{{ $ctaLink }}"
                       class="rounded-full {{ $style === 'amber' ? 'bg-amber-900 text-white hover:bg-amber-950' : 'bg-white text-brand-900 hover:bg-brand-50' }} px-6 py-2.5 text-sm font-semibold transition">
                        {{ $ctaText }}
                    </a>
                @endif
            </div>
        </div>
    </section>
@endif

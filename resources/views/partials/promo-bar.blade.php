{{--
    Site-wide promo bar — thin black strip above the header. Slides are
    configured in Admin → Settings → site, stored as JSON in
    site.promo_slides. Auto-rotates every 5 s; prev/next arrows for
    manual nav; hover pauses; renders nothing when no slides exist.
--}}
@php
    $raw = $site['site.promo_slides'] ?? '';
    $slides = $raw ? (json_decode($raw, true) ?: []) : [];
    $slides = array_values(array_filter($slides, fn ($s) => is_array($s) && trim((string) ($s['text'] ?? '')) !== ''));
@endphp
@if ($slides)
    <div x-data="{
            i: 0,
            slides: @js($slides),
            timer: null,
            start() { if (this.slides.length > 1) this.timer = setInterval(() => this.next(), 5000) },
            stop()  { clearInterval(this.timer); this.timer = null },
            next()  { this.i = (this.i + 1) % this.slides.length },
            prev()  { this.i = (this.i - 1 + this.slides.length) % this.slides.length },
         }"
         x-init="start()"
         @mouseenter="stop()" @mouseleave="start()"
         class="relative z-[60] flex h-9 items-center justify-center gap-3 bg-brand-900 px-10 text-center text-white">

        {{-- Prev (RTL: shows on the right) — hidden when only one slide --}}
        <template x-if="slides.length > 1">
            <button type="button" @click.stop="prev(); stop()" aria-label="{{ __('Previous') }}"
                    class="absolute end-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full text-white/70 transition hover:bg-white/10 hover:text-white">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
            </button>
        </template>

        <template x-for="(s, idx) in slides" :key="idx">
            <a :href="s.url || '#'"
               x-show="i === idx"
               x-transition.opacity.duration.300ms
               class="line-clamp-1 text-xs font-medium tracking-wide text-white hover:underline sm:text-sm">
                <span x-text="s.text"></span>
            </a>
        </template>

        {{-- Next (RTL: shows on the left) --}}
        <template x-if="slides.length > 1">
            <button type="button" @click.stop="next(); stop()" aria-label="{{ __('Next') }}"
                    class="absolute start-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full text-white/70 transition hover:bg-white/10 hover:text-white">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </template>
    </div>
@endif

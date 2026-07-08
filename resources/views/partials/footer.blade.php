{{--
    Editorial dark footer. Two admin-editable link columns
    (MenuItem::for('footer_1' | 'footer_2')), social icons (site.instagram /
    telegram / whatsapp settings), trust badges (site.enamad_html /
    samandehi_html), and an editorial closing statement (site.footer_statement,
    set in Admin → تنظیمات سایت). Closes with the oversized CHIACO display
    type — kept as the signature brand move — and a small copyright line.
--}}
<footer class="mt-24 bg-brand-900 text-brand-100">
    <div class="mx-auto max-w-7xl px-6 pt-16 pb-10 sm:px-10 sm:pt-20">

        {{-- 4-column layout (1 col on mobile, 4 on lg). Brand block spans 2 cols on lg. --}}
        <div class="grid gap-12 lg:grid-cols-4">

            {{-- Brand + tagline + statement + social --}}
            <div class="lg:col-span-2">
                <x-brand-logo class="text-white" />

                @if ($statement = trim((string) ($site['site.footer_statement'] ?? '')))
                    <p class="mt-6 max-w-md text-sm leading-8 text-brand-200">{{ $statement }}</p>
                @elseif ($tagline = trim((string) ($site['site.tagline'] ?? '')))
                    <p class="mt-6 max-w-md text-sm leading-8 text-brand-200">{{ $tagline }}</p>
                @endif

                {{-- Social --}}
                <div class="mt-7 flex gap-2">
                    @if ($ig = ($site['site.instagram'] ?? null))
                    <a href="https://instagram.com/{{ ltrim($ig, '@') }}" target="_blank" rel="noopener"
                       aria-label="اینستاگرام"
                       class="grid h-10 w-10 place-items-center rounded-full text-white/80 ring-1 ring-white/15 transition hover:bg-white hover:text-brand-900">
                        <svg aria-hidden="true" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zm0 10.162a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/></svg>
                    </a>
                    @endif
                    @if ($tg = ($site['site.telegram'] ?? null))
                    <a href="https://t.me/{{ ltrim($tg, '@') }}" target="_blank" rel="noopener"
                       aria-label="تلگرام"
                       class="grid h-10 w-10 place-items-center rounded-full text-white/80 ring-1 ring-white/15 transition hover:bg-white hover:text-brand-900">
                        <svg aria-hidden="true" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    </a>
                    @endif
                    @if ($wa = ($site['site.whatsapp'] ?? null))
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" target="_blank" rel="noopener"
                       aria-label="واتساپ"
                       class="grid h-10 w-10 place-items-center rounded-full text-white/80 ring-1 ring-white/15 transition hover:bg-white hover:text-brand-900">
                        <svg aria-hidden="true" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                    </a>
                    @endif
                </div>

                {{-- Trust badges (kept here so the dark bg lets them pop on white) --}}
                @php($enamad = $site['site.enamad_html'] ?? null)
                @php($samandehi = $site['site.samandehi_html'] ?? null)
                @if ($enamad || $samandehi)
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        @if ($enamad)<div class="inline-block rounded-lg bg-white p-2">{!! $enamad !!}</div>@endif
                        @if ($samandehi)<div class="inline-block rounded-lg bg-white p-2">{!! $samandehi !!}</div>@endif
                    </div>
                @endif
            </div>

            {{-- Quick links column (admin-editable via MenuItem footer_1) --}}
            @php($footer1 = \App\Models\MenuItem::for('footer_1'))
            <div>
                <h4 class="mb-5 font-display text-xs font-semibold uppercase tracking-[0.22em] text-white/60">دسترسی سریع</h4>
                <ul class="space-y-3 text-sm text-brand-200">
                    @forelse ($footer1 as $item)
                        <li><a href="{{ $item->url }}" class="transition hover:text-white">{{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ route('shop.index') }}" class="transition hover:text-white">فروشگاه</a></li>
                        <li><a href="{{ route('page', 'about') }}" class="transition hover:text-white">درباره ما</a></li>
                        <li><a href="{{ route('contact') }}" class="transition hover:text-white">تماس با ما</a></li>
                        <li><a href="{{ route('page', 'faq') }}" class="transition hover:text-white">سوالات متداول</a></li>
                    @endforelse
                </ul>
            </div>

            {{-- Customer service column (admin-editable via MenuItem footer_2) --}}
            @php($footer2 = \App\Models\MenuItem::for('footer_2'))
            <div>
                <h4 class="mb-5 font-display text-xs font-semibold uppercase tracking-[0.22em] text-white/60">خدمات مشتریان</h4>
                <ul class="space-y-3 text-sm text-brand-200">
                    @forelse ($footer2 as $item)
                        <li><a href="{{ $item->url }}" class="transition hover:text-white">{{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ route('page', 'size-guide') }}" class="transition hover:text-white">راهنمای سایز</a></li>
                        <li><a href="{{ route('page', 'shipping-returns') }}" class="transition hover:text-white">شیوه‌های ارسال و بازگشت</a></li>
                        <li><a href="{{ route('page', 'terms') }}" class="transition hover:text-white">قوانین و مقررات</a></li>
                        <li><a href="{{ route('page', 'privacy') }}" class="transition hover:text-white">حریم خصوصی</a></li>
                    @endforelse
                </ul>
                @if ($phone = ($site['site.contact_phone'] ?? null))
                    <p class="mt-6 text-sm text-brand-200">
                        <span class="block font-display text-xs font-semibold uppercase tracking-[0.22em] text-white/60">پشتیبانی</span>
                        <a href="tel:{{ $phone }}" class="mt-1 inline-block text-white hover:underline" dir="ltr">{{ $phone }}</a>
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Oversized CHIACO display type — signature brand move, kept --}}
    <div class="relative overflow-hidden border-t border-white/10">
        <x-brand-pattern class="pointer-events-none absolute inset-0 h-full w-full text-white" :opacity="'0.06'" />
        <div class="relative mx-auto flex max-w-7xl items-center justify-center px-4 py-10 sm:px-6 sm:py-14">
            <x-chiaco-type text="CHIACO" class="text-6xl text-white sm:text-8xl lg:text-9xl" />
        </div>
    </div>

    {{-- Copyright --}}
    <div class="border-t border-white/10 py-6">
        <p class="text-center text-xs text-white/45 fa-num">© {{ \App\Support\Money::toPersianDigits((string) now()->year) }} {{ ($site['site.store_name'] ?? null) ?: 'چیاکو' }} — تمام حقوق محفوظ است.</p>
    </div>
</footer>

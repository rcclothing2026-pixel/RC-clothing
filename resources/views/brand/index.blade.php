@extends('layouts.app')

@section('title', 'سیستم طراحی چیاکو | Brand Kit')
@section('meta_description', 'مرجع داخلی هویت بصری چیاکو — Change in Aesthetics.')

@push('head')
<meta name="robots" content="noindex, nofollow">
<style>
    /* Numbered Persian section eyebrow with red bullseye, matches the
       handoff's editorial rhythm. */
    .ds-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-family: 'Jost', ui-sans-serif, sans-serif;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.22em;
        color: #9c9296;
        text-transform: uppercase;
    }
    .ds-num {
        font-family: 'Jost', ui-sans-serif, sans-serif;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.2em;
        color: #9c9296;
    }
    /* Sticky TOC sidebar on desktop; flattens to wrapped chips on tablet. */
    @media (max-width: 1024px) {
        .ds-shell { grid-template-columns: 1fr !important; }
        .ds-nav   { position: static !important; height: auto !important;
                    border-inline-end: none !important;
                    border-block-end: 1px solid #f1ecee !important;
                    flex-direction: row !important; flex-wrap: wrap !important;
                    gap: 6px 16px !important; padding: 18px 4px !important; }
    }
</style>
@endpush

@section('content')
<div class="mx-auto max-w-6xl">

    {{-- Masthead: dark, pattern at 12%, CHANGE · IN · AESTHETICS eyebrow, big title --}}
    <header class="relative overflow-hidden bg-brand-900 px-6 py-14 text-white sm:px-12 sm:py-20">
        <div class="pointer-events-none absolute inset-0 opacity-[0.12]"
             style="background-image:url('/img/brand/pattern-gate.svg');background-size:104px 104px;background-repeat:repeat;"></div>
        <div class="relative">
            <span class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.25em] text-white/70" style="font-family:'Jost',sans-serif">
                <x-brand-dot class="h-3 w-3 text-accent-600" />
                CHANGE&nbsp;·&nbsp;IN&nbsp;·&nbsp;AESTHETICS
            </span>
            <h1 class="mt-6 text-4xl font-bold leading-[1.05] tracking-tight sm:text-6xl">سیستمِ طراحی چیاکو</h1>
            <p class="mt-5 max-w-xl text-sm leading-[2] text-white/60">
                مرجعِ داخلیِ هویتِ بصری بر اساسِ BrandBook نسخه ۰۱. این صفحه مستقیماً به مؤلفه‌ها و توکن‌های پروژه
                (<code class="rounded bg-white/10 px-1.5 py-0.5 text-[12px]" dir="ltr">resources/views/components</code>) نگاشته شده است.
            </p>
        </div>
    </header>

    <div class="ds-shell grid items-start lg:grid-cols-[210px_1fr]">
        {{-- Sticky table-of-contents nav --}}
        <nav class="ds-nav top-0 flex gap-[2px] border-l border-brand-100 px-5 py-9 lg:sticky lg:h-screen lg:flex-col">
            <div class="ds-num mb-2 hidden lg:block">CONTENTS</div>
            @foreach ([
                ['#color',     '۰۱ — پالتِ رنگی'],
                ['#identity',  '۰۲ — نشان و لوگو'],
                ['#tagline',   '۰۳ — شعار برند'],
                ['#pattern',   '۰۴ — الگوها'],
                ['#editorial', '۰۵ — برشِ مورب'],
                ['#type',      '۰۶ — تایپِ نمایشی'],
                ['#headings',  '۰۷ — تیتر و نشانه'],
                ['#buttons',   '۰۸ — دکمه‌ها'],
                ['#card',      '۰۹ — کارتِ محصول'],
                ['#tokens',    '۱۰ — توکن‌ها (کد)'],
            ] as [$href, $label])
                <a href="{{ $href }}" class="block py-1.5 text-[13px] text-brand-900 transition hover:text-accent-600">{{ $label }}</a>
            @endforeach
        </nav>

        <main class="min-w-0">

        {{-- 01 — COLOR ----------------------------------------------------- --}}
        <section id="color" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Color Palette</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">پالتِ رنگی</h2>
            <p class="mt-3 max-w-xl text-sm leading-[1.95] text-brand-500">
                پنج رنگِ رسمیِ برند؛ هیچ رنگِ خارج از پالت مجاز نیست. هر رنگ به یک توکنِ
                <code class="rounded bg-brand-100 px-1.5 py-0.5 text-[12px]" dir="ltr">@theme</code> نگاشته شده است.
            </p>

            <div class="mt-7 grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    ['Persian Red', '#CC3333', '--color-persian-red',  'bg-accent-600', 'text-white', ''],
                    ['Chiaco Dark', '#282828', '--color-chiaco-dark',  'bg-brand-900',  'text-white', ''],
                    ['Chiaco Light','#E0D5D9', '--color-chiaco-light', 'bg-brand-200',  'text-brand-900', ''],
                    ['White',       '#FFFFFF', '--color-white',        'bg-white',      'text-brand-900', 'ring-1 ring-brand-100'],
                    ['Black',       '#000000', '--color-black',        'bg-black',      'text-white', ''],
                ] as [$name, $hex, $token, $bg, $fg, $ring])
                    <div class="overflow-hidden rounded-2xl ring-1 ring-brand-100">
                        <div class="flex h-24 items-end {{ $bg }} p-3 {{ $fg }} {{ $ring }}">
                            <span class="text-[12px] font-bold" style="font-family:'Jost',sans-serif">{{ $name }}</span>
                        </div>
                        <div class="flex items-center justify-between bg-white px-3 py-2">
                            <span class="text-[11px] text-brand-500" style="font-family:'JetBrains Mono',monospace" dir="ltr">{{ $hex }}</span>
                            <span class="text-[10px] text-brand-400" style="font-family:'JetBrains Mono',monospace" dir="ltr">{{ $token }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="ds-num mt-8 mb-3 uppercase">Sanctioned Gradients</p>
            <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                @foreach ([
                    ['bg-grad-red-light',  '--grad-red-light'],
                    ['bg-grad-dark-light', '--grad-dark-light'],
                    ['bg-grad-dark-red',   '--grad-dark-red'],
                    ['bg-grad-bw',         '--grad-bw'],
                ] as [$cls, $token])
                    <div>
                        <div class="h-16 rounded-2xl {{ $cls }}"></div>
                        <div class="mt-2 text-[10.5px] text-brand-400" style="font-family:'JetBrains Mono',monospace" dir="ltr">{{ $token }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 02 — IDENTITY -------------------------------------------------- --}}
        <section id="identity" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Identity</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">نشان و لوگو</h2>
            <p class="mt-3 max-w-xl text-sm leading-[1.95] text-brand-500">
                نشانِ «دروازه» (Gate) برگرفته از هندسه‌ی فرشِ ایرانی است؛ ترکیبی از کوه/پله و طاقِ مرکزی. با
                <code class="rounded bg-brand-100 px-1.5 py-0.5 text-[12px]" dir="ltr">currentColor</code> در هر زمینه بازرنگ می‌شود.
            </p>

            {{-- Gate Sign — three backgrounds --}}
            <div class="mt-7 grid gap-3.5 sm:grid-cols-3">
                <div class="flex aspect-[4/3] flex-col items-center justify-center gap-3 rounded-2xl bg-brand-50 ring-1 ring-brand-100">
                    <x-brand-sign class="h-[62px] w-auto text-brand-900" />
                    <span class="text-[11px] text-brand-400" style="font-family:'Jost',sans-serif">Gate Sign — dark</span>
                </div>
                <div class="flex aspect-[4/3] flex-col items-center justify-center gap-3 rounded-2xl bg-brand-900">
                    <x-brand-sign class="h-[62px] w-auto text-brand-200" />
                    <span class="text-[11px] text-white/40" style="font-family:'Jost',sans-serif">Gate Sign — on dark</span>
                </div>
                <div class="flex aspect-[4/3] flex-col items-center justify-center gap-3 rounded-2xl bg-accent-600">
                    <x-brand-sign class="h-[62px] w-auto text-white" />
                    <span class="text-[11px] text-white/70" style="font-family:'Jost',sans-serif">Gate Sign — on red</span>
                </div>
            </div>

            {{-- Lockups + 3D --}}
            <div class="mt-3.5 grid gap-3.5 sm:grid-cols-3">
                <div class="flex flex-col items-center justify-center gap-3.5 rounded-2xl bg-white p-8 ring-1 ring-brand-100">
                    <x-brand-logo :latin="true" class="text-brand-900" />
                    <span class="text-[11px] text-brand-400" style="font-family:'Jost',sans-serif">Lockup — Latin</span>
                </div>
                <div class="flex flex-col items-center justify-center gap-3.5 rounded-2xl bg-brand-900 p-8">
                    <x-brand-logo class="text-white" />
                    <span class="text-[11px] text-white/40" style="font-family:'Jost',sans-serif">Lockup — Persian (on dark)</span>
                </div>
                <div class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-white p-5 ring-1 ring-brand-100">
                    <x-brand-mark-3d class="h-[92px] w-auto" />
                    <span class="text-[11px] text-brand-400" style="font-family:'Jost',sans-serif">3D Extruded Gate</span>
                </div>
            </div>

            {{-- Real logotype extracted from brand book — confirmation gate before global rollout --}}
            <p class="ds-num mt-8 mb-3 uppercase text-accent-600">Real logotype — recolorable mask</p>
            <div class="grid gap-3.5 sm:grid-cols-3">
                @php($logoMask = "background: currentColor; -webkit-mask: url('/img/brand/chiaco-logo.svg') no-repeat center/contain; mask: url('/img/brand/chiaco-logo.svg') no-repeat center/contain;")
                <div class="grid h-40 place-items-center rounded-2xl bg-white text-brand-900 ring-1 ring-brand-100">
                    <div class="h-24 w-48" style="{{ $logoMask }}"></div>
                </div>
                <div class="grid h-40 place-items-center rounded-2xl bg-brand-900 text-white">
                    <div class="h-24 w-48" style="{{ $logoMask }}"></div>
                </div>
                <div class="grid h-40 place-items-center rounded-2xl bg-accent-600 text-white">
                    <div class="h-24 w-48" style="{{ $logoMask }}"></div>
                </div>
            </div>
            <p class="mt-3 text-xs text-brand-400">
                این لوگوتایپ واقعی از کتاب برند استخراج شده و با
                <code class="rounded bg-brand-100 px-1 text-[11px]" dir="ltr">currentColor</code>
                در هر سه زمینه بازرنگ می‌شود. پس از تأیید، جایگزین لوگوی متنی فعلی در هدر/فوتر می‌شود.
            </p>
        </section>

        {{-- 03 — TAGLINE --------------------------------------------------- --}}
        <section id="tagline" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Tagline</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">شعار برند</h2>
            <div class="mt-7 grid gap-3.5 sm:grid-cols-3">
                <div class="flex items-center justify-center rounded-2xl bg-white p-10 ring-1 ring-brand-100">
                    <span class="inline-flex items-center gap-2 text-[13px] font-semibold uppercase tracking-[0.25em] text-brand-900" style="font-family:'Jost',sans-serif">
                        CHANGE <span class="text-accent-600">·</span> IN <span class="text-accent-600">·</span> AESTHETICS
                    </span>
                </div>
                <div class="flex items-center justify-center rounded-2xl bg-brand-200 p-8">
                    <x-brand-tagline variant="badge" class="h-28 w-28 text-brand-900" />
                </div>
                <div class="flex items-center justify-center rounded-2xl bg-accent-600 p-8">
                    <x-brand-tagline variant="badge" class="h-28 w-28 text-white" />
                </div>
            </div>
        </section>

        {{-- 04 — PATTERNS -------------------------------------------------- --}}
        <section id="pattern" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Patterns</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">الگوها</h2>
            <p class="mt-3 max-w-xl text-sm leading-[1.95] text-brand-500">
                نقش‌مایه‌ی «دروازه» دقیقاً از وکتورِ کتابِ برند
                (<code class="rounded bg-brand-100 px-1.5 py-0.5 text-[12px]" dir="ltr">pattern-gate.svg</code>) —
                کاشیِ بدونِ درز که روی هر سه زمینه بازرنگ‌پذیر است.
            </p>

            <div class="mt-7 grid gap-3.5 sm:grid-cols-3">
                <div class="relative h-[200px] overflow-hidden rounded-2xl bg-white ring-1 ring-brand-100">
                    <x-brand-pattern-gate class="absolute inset-0 h-full w-full text-brand-900" opacity="0.9" />
                    <span class="absolute bottom-3 end-3.5 text-[11px] text-brand-400" style="font-family:'Jost',sans-serif">Exact swatch — on paper</span>
                </div>
                <div class="relative h-[200px] overflow-hidden rounded-2xl bg-brand-900">
                    <x-brand-pattern-gate class="absolute inset-0 h-full w-full text-white" opacity="0.45" />
                    <span class="absolute bottom-3 end-3.5 text-[11px] text-white/45" style="font-family:'Jost',sans-serif">On Chiaco Dark</span>
                </div>
                <div class="relative h-[200px] overflow-hidden rounded-2xl bg-accent-600">
                    <x-brand-pattern-gate class="absolute inset-0 h-full w-full text-white" opacity="0.7" />
                    <span class="absolute bottom-3 end-3.5 text-[11px] text-white/70" style="font-family:'Jost',sans-serif">On Persian Red</span>
                </div>
            </div>
        </section>

        {{-- 05 — EDITORIAL DIAGONAL CUT ----------------------------------- --}}
        <section id="editorial" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Editorial</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">برشِ مورب</h2>
            <p class="mt-3 max-w-xl text-sm leading-[1.95] text-brand-500">
                پردازشِ امضاییِ عکس: یک برشِ موربِ قرمزِ ایرانی یا تیره روی یک گوشه، با امکانِ درجِ واژه‌نشان.
            </p>
            <div class="mt-7 grid gap-3.5 sm:grid-cols-3">
                <x-diagonal-cut src="/placeholder?w=600&h=750&seed=br1&label={{ urlencode('چیاکو') }}"
                                alt="نمونه" label="CHIACO" />
                <x-diagonal-cut src="/placeholder?w=600&h=750&seed=br2&label={{ urlencode('چیاکو') }}"
                                alt="نمونه" tone="dark" corner="top" label="CHIACO" />
                <x-diagonal-cut src="/placeholder?w=600&h=750&seed=br3&label={{ urlencode('چیاکو') }}"
                                alt="نمونه" />
            </div>
        </section>

        {{-- 06 — DISPLAY TYPE --------------------------------------------- --}}
        <section id="type" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Display Type</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">تایپوگرافی نمایشی</h2>
            <div class="mt-7 space-y-6 rounded-2xl bg-white p-8 ring-1 ring-brand-100">
                <x-chiaco-type tag="div" text="ONLINE SHOPPING"      class="text-3xl text-brand-900 sm:text-5xl" />
                <x-chiaco-type tag="div" text="END OF SEASON OFF"    class="text-3xl text-accent-600 sm:text-5xl" />
                <x-chiaco-type tag="div" text="CHANGE IN AESTHETICS" class="text-2xl text-brand-900 sm:text-4xl" />
            </div>
            <div class="mt-3.5 rounded-2xl bg-brand-900 p-8">
                <x-chiaco-type tag="div" text="CHIACO" class="justify-center text-5xl text-white sm:text-7xl" />
            </div>
            <p class="mt-3 text-xs text-brand-400">
                هر «O» با نشانهٔ هدف ⊙ برند جایگزین می‌شود. این جلوهٔ CSS است؛ با رسیدن فونت اختصاصی چیاکو، فقط فونت‌استک جایگزین می‌شود.
            </p>
        </section>

        {{-- 07 — HEADINGS & BULLET ---------------------------------------- --}}
        <section id="headings" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Headings &amp; Markers</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">تیتر و نشانه</h2>
            <div class="mt-7 grid gap-3.5 lg:grid-cols-2">
                <div class="rounded-2xl bg-white p-8 ring-1 ring-brand-100">
                    <x-brand-heading kicker="Editorial Heading">عنوانِ ادیتوریال</x-brand-heading>
                    <p class="mt-3 text-sm leading-7 text-brand-700">پراگراف زیرتیتر با ریتم تایپوگرافی اصلی فروشگاه — همان چیزی که در صفحه‌های واقعی استفاده می‌شود.</p>
                </div>
                <div class="rounded-2xl bg-white p-8 ring-1 ring-brand-100">
                    <ul class="space-y-3">
                        @foreach (['دوخت تمیز و باکیفیت', 'ارسال سریع به سراسر ایران', 'ضمانت اصالت کالا'] as $item)
                            <li class="flex items-center gap-2.5 text-sm text-brand-700">
                                <x-brand-dot class="h-3 w-3 shrink-0 text-accent-600" />
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        {{-- 08 — BUTTONS (NEW) -------------------------------------------- --}}
        <section id="buttons" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Buttons</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">دکمه‌ها</h2>
            <p class="mt-3 max-w-xl text-sm leading-[1.95] text-brand-500">
                سه سطح: اولیه (تأیید نهایی)، ثانویه (عملِ حاشیه‌ای)، شبح/متنی (لینک‌مانند). همگی به شکلِ
                <code class="rounded bg-brand-100 px-1.5 py-0.5 text-[12px]" dir="ltr">rounded-full</code>
                هستند تا با ریتم بصری برند هم‌خوانی داشته باشند.
            </p>

            <div class="mt-7 grid gap-3.5 lg:grid-cols-3">
                {{-- Primary --}}
                <div class="rounded-2xl bg-white p-6 ring-1 ring-brand-100">
                    <p class="ds-num mb-4 uppercase">Primary</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="rounded-full bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[0.98]">افزودن به سبد</button>
                        <button type="button" class="rounded-full bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white opacity-50" disabled>غیرفعال</button>
                    </div>
                    <p class="mt-3 text-[11px] text-brand-400">برای CTA اصلی هر صفحه. حداکثر یکی در هر بخش.</p>
                </div>

                {{-- Secondary (red accent) --}}
                <div class="rounded-2xl bg-white p-6 ring-1 ring-brand-100">
                    <p class="ds-num mb-4 uppercase text-accent-600">Accent (red)</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="rounded-full bg-accent-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-700 active:scale-[0.98]">خرید فوری</button>
                        <button type="button" class="rounded-full bg-accent-600 px-6 py-2.5 text-sm font-semibold text-white opacity-50" disabled>غیرفعال</button>
                    </div>
                    <p class="mt-3 text-[11px] text-brand-400">برای فروش/تخفیف؛ کمیاب نگه دارید تا اثرش حفظ شود.</p>
                </div>

                {{-- Ghost --}}
                <div class="rounded-2xl bg-white p-6 ring-1 ring-brand-100">
                    <p class="ds-num mb-4 uppercase">Ghost / Outline</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="rounded-full px-6 py-2.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50">ادامه خرید</button>
                        <a href="#" class="text-sm font-semibold text-accent-600 hover:underline">مشاهده همه ←</a>
                    </div>
                    <p class="mt-3 text-[11px] text-brand-400">عمل ثانویه/خنثی؛ کنار یک Primary استفاده می‌شود.</p>
                </div>
            </div>

            {{-- On dark --}}
            <div class="mt-3.5 rounded-2xl bg-brand-900 p-6">
                <p class="ds-num mb-4 uppercase text-white/50">On Chiaco Dark</p>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" class="rounded-full bg-white px-6 py-2.5 text-sm font-semibold text-brand-900 transition hover:bg-brand-50">ادامه</button>
                    <button type="button" class="rounded-full bg-accent-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-700">خرید فوری</button>
                    <button type="button" class="rounded-full px-6 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/10">بیشتر</button>
                </div>
            </div>
        </section>

        {{-- 09 — PRODUCT CARD (NEW) --------------------------------------- --}}
        <section id="card" class="border-b border-brand-100 px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Commerce</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">کارتِ محصول</h2>
            <p class="mt-3 max-w-xl text-sm leading-[1.95] text-brand-500">
                مؤلفه‌ی واقعیِ
                <code class="rounded bg-brand-100 px-1.5 py-0.5 text-[12px]" dir="ltr">&lt;x-product-card&gt;</code>
                است — همان چیزی که در صفحه‌ی فروشگاه و دسته‌بندی‌ها رندر می‌شود.
            </p>

            {{-- Latest live products; falls back to an in-memory mock so the
                 brand page never breaks on a fresh install. --}}
            @php($sampleProducts = \App\Models\Product::active()->with(['images','variants','category'])->latest()->take(4)->get())
            @if ($sampleProducts->isEmpty())
                @php($mock = tap(new \App\Models\Product(['name' => 'مانتو کتانی چیاکو', 'slug' => 'demo', 'price' => 1450000, 'compare_at_price' => 1800000, 'is_active' => true]), function ($m) { $m->setRelation('images', collect()); $m->setRelation('variants', collect()); $m->setRelation('category', null); }))
                @php($sampleProducts = collect([$mock, $mock, $mock, $mock]))
            @endif
            <div class="mt-7 grid gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($sampleProducts as $p)
                    <x-product-card :product="$p" />
                @endforeach
            </div>
        </section>

        {{-- 10 — TOKENS (CODE) (NEW) -------------------------------------- --}}
        <section id="tokens" class="px-6 py-14 sm:px-10">
            <span class="ds-eyebrow"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />Tokens</span>
            <h2 class="mt-2.5 text-3xl font-bold tracking-tight sm:text-4xl">توکن‌ها (کد)</h2>
            <p class="mt-3 max-w-xl text-sm leading-[1.95] text-brand-500">
                همه‌ی مقادیر بالا از این توکن‌ها می‌آیند — یک منبعِ یکتای حقیقت برای رنگ، تایپ، شعاع و موشن. هرجا
                در کد عدد یا کدِ هگز دیدید، یعنی باید جایگزینِ توکن شود.
            </p>

            <div class="mt-7 grid gap-3.5 lg:grid-cols-2">
                {{-- Colors --}}
                <pre dir="ltr" class="overflow-x-auto rounded-2xl bg-brand-900 p-5 text-[12.5px] leading-7 text-brand-200" style="font-family:'JetBrains Mono',monospace"><code>/* tokens/colors.css */
--color-persian-red : #CC3333;
--color-chiaco-dark : #282828;
--color-chiaco-light: #E0D5D9;
--color-white       : #FFFFFF;
--color-black       : #000000;

/* Sanctioned gradients */
--grad-red-light  : linear-gradient(120deg, #CC3333, #E0D5D9);
--grad-dark-light : linear-gradient(120deg, #282828, #E0D5D9);
--grad-dark-red   : linear-gradient(120deg, #282828, #CC3333);
--grad-bw         : linear-gradient(120deg, #000000, #FFFFFF);</code></pre>

                {{-- Type --}}
                <pre dir="ltr" class="overflow-x-auto rounded-2xl bg-brand-900 p-5 text-[12.5px] leading-7 text-brand-200" style="font-family:'JetBrains Mono',monospace"><code>/* tokens/typography.css */
--font-display   : 'Jost', sans-serif;       /* Latin display */
--font-sans      : 'Vazirmatn', sans-serif;  /* Persian / UI  */
--font-mono      : 'JetBrains Mono', mono;

--text-display   : clamp(40px, 6vw, 82px);
--text-h1        : clamp(30px, 4vw, 52px);
--text-h2        : clamp(24px, 3vw, 38px);
--text-body      : 15px;

--tracking-label : 0.22em;   /* eyebrow */
--tracking-word  : 0.28em;   /* CHIACO  */
--leading-body   : 1.95;</code></pre>

                {{-- Radii + motion --}}
                <pre dir="ltr" class="overflow-x-auto rounded-2xl bg-brand-900 p-5 text-[12.5px] leading-7 text-brand-200 lg:col-span-2" style="font-family:'JetBrains Mono',monospace"><code>/* tokens/effects.css */
--radius-card   : 1rem;    /* product cards, panels       */
--radius-button : 10px;
--radius-pill   : 999px;

--shadow-sm     : 0 1px 3px rgba(40, 31, 29, .08);
--shadow-card   : 0 18px 40px -24px rgba(40, 31, 29, .35);
--shadow-lifted : 0 40px 90px -30px rgba(0, 0, 0, .45);

--ease-out      : cubic-bezier(.16, 1, .3, 1);
--dur-fast      : .2s;
--dur-med       : .6s;
--dur-slow      : 1.1s;</code></pre>
            </div>

            <p class="mt-4 text-xs text-brand-400">
                مسیرها در ریپو: <code class="rounded bg-brand-100 px-1.5 py-0.5 text-[12px]" dir="ltr">resources/css/app.css</code> و
                <code class="rounded bg-brand-100 px-1.5 py-0.5 text-[12px]" dir="ltr">tailwind.config.js</code>.
            </p>
        </section>

        </main>
    </div>
</div>
@endsection

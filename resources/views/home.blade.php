@extends('layouts.app')

@section('title', 'چیاکو | پوشاک ایرانی')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-bl from-brand-100 via-paper to-brand-50">
        <div class="pointer-events-none absolute -top-24 -start-24 h-80 w-80 rounded-full bg-brand-200/40 blur-3xl animate-float-slow"></div>
        <div class="pointer-events-none absolute -bottom-24 -end-24 h-96 w-96 rounded-full bg-accent-400/20 blur-3xl animate-float-slow" style="animation-delay:-2s"></div>
        <x-brand-pattern class="pointer-events-none absolute inset-0 h-full w-full text-brand-900" :opacity="'0.08'" />

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-28 sm:px-6 md:grid-cols-2 md:py-40">
            <div class="reveal">
                <x-brand-tagline class="text-brand-400" />
                <h1 class="mt-5 text-5xl font-bold leading-[1.1] text-brand-900 sm:text-6xl md:text-7xl">
                    {{ ($site['home.hero_title'] ?? null) ?: 'استایلِ امروزِ تو،' }}<br><span class="text-accent-600">{{ ($site['home.hero_title_accent'] ?? null) ?: 'با کیفیت ایرانی' }}</span>
                </h1>
                <p class="mt-8 max-w-md text-base leading-8 text-brand-500">
                    {{ ($site['home.hero_subtitle'] ?? null) ?: 'مجموعه‌ای از مانتو، پیراهن و پوشاک روزمره با دوختی تمیز و پارچه‌ای باکیفیت. همین حالا انتخاب کن.' }}
                </p>
                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="{{ route('shop.index') }}" class="rounded-full bg-brand-900 px-8 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[0.98]">{{ ($site['home.hero_cta_text'] ?? null) ?: 'مشاهده فروشگاه' }}</a>
                    <a href="{{ route('shop.index', ['sort' => 'price_asc']) }}" class="rounded-full bg-white px-8 py-3.5 text-sm font-semibold text-brand-800 ring-1 ring-brand-200 transition hover:ring-brand-400 active:scale-[0.98]">پیشنهاد شگفت‌انگیز</a>
                </div>
            </div>

            <div class="reveal relative" style="transition-delay:120ms">
                <div class="aspect-[3/4] overflow-hidden rounded-[2.5rem] bg-brand-100 shadow-2xl ring-1 ring-white/50 sm:aspect-[4/5]">
                    <img src="{{ ($site['home.hero_image'] ?? null) ?: '/placeholder?w=900&h=1100&seed=hero&label='.urlencode('کالکشن چیاکو') }}" alt="کالکشن چیاکو" class="h-full w-full object-cover object-top">
                </div>
                {{-- Brand tagline stamp --}}
                <x-brand-tagline variant="badge" class="absolute -top-5 -end-3 h-20 w-20 text-brand-900 drop-shadow-sm sm:h-24 sm:w-24" />
                <div class="absolute -bottom-5 -start-5 rounded-2xl bg-white p-4 shadow-lg ring-1 ring-brand-100">
                    <p class="text-xs text-brand-400">ارسال سریع</p>
                    <p class="text-sm font-bold text-brand-800">به سراسر ایران</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Social proof --}}
    <section class="mx-auto -mt-6 max-w-5xl px-4 sm:px-6">
        <div class="reveal grid grid-cols-3 divide-x divide-brand-200 rounded-2xl bg-white py-7 shadow-sm ring-1 ring-brand-100 rtl:divide-x-reverse">
            @foreach ([
                ['۱۵۰۰+', 'خرید موفق'],
                ['۹۸٪', 'رضایت مشتریان'],
                ['۷ روز', 'ضمانت بازگشت'],
            ] as [$n, $l])
                <div class="flex flex-col items-center gap-1 text-center">
                    <span class="text-2xl font-bold text-brand-900 fa-num">{{ \App\Support\Money::toPersianDigits((string) $n) }}</span>
                    <span class="text-xs text-brand-500">{{ $l }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Categories --}}
    <section class="mx-auto max-w-7xl px-4 py-24 sm:px-6">
        <div class="reveal mb-12 flex items-end justify-between">
            <div>
                <p class="mb-2 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-brand-400"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />کالکشن</p>
                <h2 class="text-3xl font-bold text-brand-900 sm:text-4xl">دسته‌بندی‌ها</h2>
            </div>
            <a href="{{ route('shop.index') }}" class="text-sm font-medium text-accent-600 hover:underline">مشاهده همه</a>
        </div>
        <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($categories as $category)
                <a href="{{ route('shop.index', ['category' => $category->slug]) }}"
                   class="reveal group flex flex-col items-center gap-4 rounded-card bg-white p-6 text-center ring-1 ring-brand-100 transition hover:-translate-y-1.5 hover:shadow-lg hover:ring-brand-200">
                    <div class="h-16 w-16 overflow-hidden rounded-full bg-brand-50 ring-1 ring-brand-100 sm:h-20 sm:w-20">
                        <img src="{{ $category->image_path }}" alt="{{ $category->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-110">
                    </div>
                    <span class="text-sm font-semibold text-brand-800">{{ $category->name }}</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Featured --}}
    @if ($featured->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div class="reveal mb-12 flex items-end justify-between">
                <div>
                    <p class="mb-2 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-brand-400"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />ویژه‌ها</p>
                    <h2 class="text-3xl font-bold text-brand-900 sm:text-4xl">منتخب چیاکو</h2>
                </div>
                <a href="{{ route('shop.index') }}" class="text-sm font-medium text-accent-600 hover:underline">مشاهده همه</a>
            </div>
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($featured as $product)
                    <div class="reveal"><x-product-card :product="$product" /></div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Lookbook / Editorial --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
        <div class="reveal mb-14 text-center">
            <p class="mb-3 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-brand-400"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />{{ ($site['home.lookbook_label'] ?? null) ?: 'لوک‌بوک' }}</p>
            <h2 class="text-3xl font-bold text-brand-900 sm:text-4xl">{{ ($site['home.lookbook_title'] ?? null) ?: 'الهام بگیر، بپوش، بدرخش' }}</h2>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Card 1 — tall, spans 2 rows on desktop --}}
            <a href="{{ ($site['home.lookbook_1_link'] ?? null) ?: route('shop.index') }}"
               class="reveal group relative overflow-hidden rounded-[2rem] bg-brand-100 lg:row-span-2">
                <div class="aspect-[3/4] lg:aspect-auto lg:h-full lg:min-h-[640px]">
                    <img src="{{ ($site['home.lookbook_1_image'] ?? null) ?: '/placeholder?w=700&h=900&seed=look1&label='.urlencode('لوک ۱') }}"
                         alt="{{ ($site['home.lookbook_1_title'] ?? null) ?: 'ست اول' }}"
                         loading="lazy"
                         class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105">
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-brand-950/70 via-transparent to-transparent"></div>
                <div class="absolute inset-x-6 bottom-6">
                    <p class="text-xs font-semibold uppercase tracking-widest text-white/70">{{ ($site['home.lookbook_1_label'] ?? null) ?: 'اسپرت' }}</p>
                    <h3 class="mt-1 text-xl font-bold text-white">{{ ($site['home.lookbook_1_title'] ?? null) ?: 'ست روزمره' }}</h3>
                    <span class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-white/80 transition group-hover:text-white">
                        مشاهده کالکشن
                        <svg class="h-3.5 w-3.5 -scale-x-100" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                    </span>
                </div>
            </a>

            {{-- Card 2 --}}
            <a href="{{ ($site['home.lookbook_2_link'] ?? null) ?: route('shop.index') }}"
               class="reveal group relative overflow-hidden rounded-[2rem] bg-brand-100">
                <div class="aspect-[4/3]">
                    <img src="{{ ($site['home.lookbook_2_image'] ?? null) ?: '/placeholder?w=600&h=450&seed=look2&label='.urlencode('لوک ۲') }}"
                         alt="{{ ($site['home.lookbook_2_title'] ?? null) ?: 'ست دوم' }}"
                         loading="lazy"
                         class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105">
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-brand-950/70 via-transparent to-transparent"></div>
                <div class="absolute inset-x-5 bottom-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-white/70">{{ ($site['home.lookbook_2_label'] ?? null) ?: 'مجلسی' }}</p>
                    <h3 class="mt-1 text-lg font-bold text-white">{{ ($site['home.lookbook_2_title'] ?? null) ?: 'ست رسمی' }}</h3>
                </div>
            </a>

            {{-- Card 3 --}}
            <a href="{{ ($site['home.lookbook_3_link'] ?? null) ?: route('shop.index') }}"
               class="reveal group relative overflow-hidden rounded-[2rem] bg-brand-100">
                <div class="aspect-[4/3]">
                    <img src="{{ ($site['home.lookbook_3_image'] ?? null) ?: '/placeholder?w=600&h=450&seed=look3&label='.urlencode('لوک ۳') }}"
                         alt="{{ ($site['home.lookbook_3_title'] ?? null) ?: 'ست سوم' }}"
                         loading="lazy"
                         class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105">
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-brand-950/70 via-transparent to-transparent"></div>
                <div class="absolute inset-x-5 bottom-5">
                    <p class="text-xs font-semibold uppercase tracking-widest text-white/70">{{ ($site['home.lookbook_3_label'] ?? null) ?: 'کژوال' }}</p>
                    <h3 class="mt-1 text-lg font-bold text-white">{{ ($site['home.lookbook_3_title'] ?? null) ?: 'ست خانگی' }}</h3>
                </div>
            </a>
        </div>
    </section>

    {{-- Promo marquee — brand tagline rhythm (CHANGE · IN · AESTHETICS) --}}
    <section class="relative my-20 overflow-hidden bg-brand-900 py-5">
        <x-brand-pattern class="pointer-events-none absolute inset-0 h-full w-full text-white" :opacity="'0.10'" />
        <div class="relative flex w-max items-center gap-8 whitespace-nowrap text-brand-100 animate-marquee">
            @for ($i = 0; $i < 2; $i++)
                <span class="text-sm">ارسال رایگان برای خرید بالای ۱٬۰۰۰٬۰۰۰ تومان</span>
                <span class="text-accent-500" aria-hidden="true">·</span>
                <x-chiaco-type text="Change in Aesthetics" class="text-xs text-white/80" />
                <span class="text-accent-500" aria-hidden="true">·</span>
                <span class="text-sm">۷ روز ضمانت بازگشت کالا</span>
                <span class="text-accent-500" aria-hidden="true">·</span>
                <span class="text-sm">پرداخت امن از طریق زرین‌پال</span>
                <span class="text-accent-500" aria-hidden="true">·</span>
            @endfor
        </div>
    </section>

    {{-- New arrivals --}}
    @if ($newArrivals->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div class="reveal mb-12 flex items-end justify-between">
                <div>
                    <p class="mb-2 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-brand-400"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />تازه‌واردها</p>
                    <h2 class="text-3xl font-bold text-brand-900 sm:text-4xl">جدیدترین‌ها</h2>
                </div>
                <a href="{{ route('shop.index', ['sort' => 'newest']) }}" class="text-sm font-medium text-accent-600 hover:underline">مشاهده همه</a>
            </div>
            <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($newArrivals as $product)
                    <div class="reveal"><x-product-card :product="$product" /></div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Brand values --}}
    <section class="mx-auto max-w-7xl px-4 py-24 sm:px-6">
        <div class="reveal mb-14 text-center">
            <p class="mb-3 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-brand-400"><x-brand-dot class="h-2.5 w-2.5 text-accent-600" />چرا چیاکو</p>
            <h2 class="text-3xl font-bold text-brand-900 sm:text-4xl">تجربه‌ای متفاوت از خرید آنلاین</h2>
        </div>
        <div class="grid gap-6 sm:grid-cols-3">
            @php($icons = [
                ['ارسال سریع', 'بسته‌بندی و ارسال در کوتاه‌ترین زمان به سراسر کشور', '<path d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 1-.987-1.106v4.964m11 8.7H8.25"/><path d="M6.75 7.5l3 2.25-3 2.25"/>'],
                ['پرداخت امن', 'پرداخت آنلاین مطمئن از طریق درگاه زرین‌پال', '<path d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>'],
                ['ضمانت کیفیت', 'تضمین کیفیت پارچه و دوخت، با امکان بازگشت کالا', '<path d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/>'],
            ])
            @foreach ($icons as [$t, $d, $paths])
                <div class="reveal rounded-card bg-white p-8 ring-1 ring-brand-100 transition hover:shadow-md">
                    <div class="mb-5 grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-700">
                        <svg aria-hidden="true" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">{!! $paths !!}</svg>
                    </div>
                    <h3 class="text-lg font-bold text-brand-900">{{ $t }}</h3>
                    <p class="mt-3 text-sm leading-7 text-brand-500">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endsection

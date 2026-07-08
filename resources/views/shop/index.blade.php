@extends('layouts.app')

@section('title', ($activeCollection->name ?? $activeCategory->name ?? null) ? (($activeCollection->name ?? $activeCategory->name).' | چیاکو') : 'فروشگاه | چیاکو')

@section('content')

    {{-- Collection / category hero banner — shown only when a collection or category is active --}}
    @if ($activeCollection)
        <section class="relative overflow-hidden bg-brand-900">
            @if ($activeCollection->image_path)
                <img src="{{ $activeCollection->image_path }}" alt="{{ $activeCollection->name }}"
                     class="absolute inset-0 h-full w-full object-cover object-center opacity-30">
            @endif
            <x-brand-pattern-2 class="pointer-events-none absolute inset-0 h-full w-full text-white" :opacity="'0.07'" />
            {{-- Signature diagonal red cut --}}
            <div class="pointer-events-none absolute inset-y-0 end-0 w-1/3 bg-accent-600/90" style="clip-path: polygon(100% 0, 100% 100%, 30% 100%);"></div>
            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-white/60"><x-brand-dot class="h-2.5 w-2.5 text-accent-500" />کالکشن</p>
                <h1 class="mt-2 text-4xl font-bold text-white sm:text-5xl">{{ $activeCollection->name }}</h1>
                @if ($activeCollection->description)
                    <p class="mt-4 max-w-xl text-base leading-7 text-white/70">{{ $activeCollection->description }}</p>
                @endif
            </div>
        </section>
    @elseif ($activeCategory && $activeCategory->image_path)
        <section class="relative overflow-hidden bg-brand-900">
            <img src="{{ $activeCategory->image_path }}" alt="{{ $activeCategory->name }}"
                 class="absolute inset-0 h-full w-full object-cover object-center opacity-25">
            <x-brand-pattern-2 class="pointer-events-none absolute inset-0 h-full w-full text-white" :opacity="'0.07'" />
            <div class="relative mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-18">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-widest text-white/60"><x-brand-dot class="h-2.5 w-2.5 text-accent-500" />دسته‌بندی</p>
                <h1 class="mt-2 text-4xl font-bold text-white sm:text-5xl">{{ $activeCategory->name }}</h1>
            </div>
        </section>
    @endif

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">

      {{-- AJAX swap target. Filter changes / sort changes / pagination
           clicks all stay inside this frame — the script at the bottom of
           this file fetches the next URL, extracts #shop-frame from the
           response, and replaces it in place. URL stays in sync via
           history.pushState. No full-page reload. --}}
      <div id="shop-frame">

        {{-- Page header + mobile filter toggle --}}
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-900">
                    {{ $activeCollection?->name ?? $activeCategory?->name ?? 'فروشگاه' }}
                </h1>
                <p class="mt-0.5 text-sm text-brand-500 fa-num">{{ \App\Support\Money::toPersianDigits((string) $products->total()) }} محصول</p>
            </div>
            <button type="button" id="filter-toggle"
                    class="flex items-center gap-2 rounded-xl border border-brand-200 bg-white px-4 py-2.5 text-sm font-medium text-brand-700 shadow-sm transition hover:bg-brand-50 lg:hidden">
                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4.5h14.25M3 9h9.75M3 13.5h5.25m5.25-.75L17.25 9m0 0L21 12.75M17.25 9v12"/></svg>
                فیلتر
                @php($activeFiltersCount = collect(['category','size','q','min','max','in_stock'])->filter(fn($k) => request($k))->count())
                @if ($activeFiltersCount > 0)
                    <span class="grid h-5 w-5 place-items-center rounded-full bg-accent-600 text-[10px] font-bold text-white fa-num">{{ \App\Support\Money::toPersianDigits((string) $activeFiltersCount) }}</span>
                @endif
            </button>
        </div>

        {{-- Active filter chips --}}
        @php($hasFilters = request('category') || request('size') || request('q') || request('min') || request('max') || request()->boolean('in_stock'))
        @if ($hasFilters)
            <div class="mb-5 flex flex-wrap gap-2">
                @if (request('q'))
                    <a href="{{ request()->fullUrlWithQuery(['q' => null, 'page' => null]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-200">
                        جستجو: {{ request('q') }}
                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if (request('category'))
                    <a href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-200">
                        دسته: {{ $categories->firstWhere('slug', request('category'))?->name ?? request('category') }}
                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if (request('size'))
                    <a href="{{ request()->fullUrlWithQuery(['size' => null, 'page' => null]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-200">
                        سایز: {{ request('size') }}
                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if (request('min') || request('max'))
                    <a href="{{ request()->fullUrlWithQuery(['min' => null, 'max' => null, 'page' => null]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-200">
                        قیمت: {{ request('min') ? \App\Support\Money::toman((int)request('min')) : '' }}{{ request('min') && request('max') ? ' — ' : '' }}{{ request('max') ? \App\Support\Money::toman((int)request('max')) : '' }}
                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if (request()->boolean('in_stock'))
                    <a href="{{ request()->fullUrlWithQuery(['in_stock' => null, 'page' => null]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-200">
                        فقط موجود
                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                <a href="{{ route('shop.index') }}"
                   class="flex items-center gap-1 rounded-full px-3 py-1.5 text-xs text-brand-400 transition hover:text-brand-700">
                    حذف همه فیلترها
                </a>
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-[260px_1fr]">

            {{-- Mobile filter backdrop --}}
            <div id="filter-backdrop" class="fixed inset-0 z-40 hidden bg-brand-900/40 lg:hidden"></div>

            {{-- Filter: bottom-sheet on mobile, static sidebar on desktop --}}
            <aside id="filter-drawer"
                   class="fixed inset-x-0 bottom-0 z-50 max-h-[85dvh] translate-y-full overflow-y-auto overscroll-contain rounded-t-2xl bg-white shadow-2xl transition-transform duration-300 ease-out lg:static lg:max-h-none lg:translate-y-0 lg:rounded-none lg:shadow-none">

                {{-- Bottom-sheet drag handle (mobile only) --}}
                <div class="sticky top-0 z-10 flex items-center justify-center bg-white pb-2 pt-3 lg:hidden">
                    <div class="h-1 w-10 rounded-full bg-brand-200"></div>
                </div>

                <form action="{{ route('shop.index') }}" method="GET"
                      class="space-y-6 p-5 lg:rounded-2xl lg:bg-white lg:ring-1 lg:ring-brand-100">

                    {{-- Mobile drawer header --}}
                    <div class="flex items-center justify-between lg:hidden">
                        <span class="font-bold text-brand-900">فیلترها</span>
                        <button type="button" data-filter-close class="grid h-8 w-8 place-items-center rounded-lg text-brand-500 hover:bg-brand-50">
                            <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <input type="hidden" name="sort" value="{{ request('sort') }}">

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-brand-800">جستجو</label>
                        <div class="relative" data-search-autocomplete>
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="نام محصول..."
                                   autocomplete="off"
                                   class="w-full rounded-xl border border-brand-200 py-2.5 ps-9 pe-3 text-sm outline-none transition placeholder:text-brand-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-100">
                            <svg aria-hidden="true" class="pointer-events-none absolute start-3 top-3 h-4 w-4 text-brand-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                            <ul id="search-suggestions" class="absolute start-0 end-0 top-full z-50 mt-1 hidden overflow-hidden rounded-xl border border-brand-200 bg-white shadow-lg"></ul>
                        </div>
                    </div>

                    <div>
                        <span class="mb-2 block text-sm font-semibold text-brand-800">دسته‌بندی</span>
                        <div class="space-y-1.5">
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-brand-600 transition hover:bg-brand-50">
                                <input type="radio" name="category" value="" @checked(! request('category')) onchange="this.form.requestSubmit()" class="accent-brand-900"> همه
                            </label>
                            @foreach ($categories as $category)
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-brand-600 transition hover:bg-brand-50">
                                    <input type="radio" name="category" value="{{ $category->slug }}" @checked(request('category') === $category->slug) onchange="this.form.requestSubmit()" class="accent-brand-900">
                                    {{ $category->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <span class="mb-2 block text-sm font-semibold text-brand-800">سایز</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($sizes as $size)
                                <label class="cursor-pointer">
                                    <input type="radio" name="size" value="{{ $size }}" class="peer sr-only" @checked(request('size') === $size) onchange="this.form.requestSubmit()">
                                    <span class="block rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-brand-700 transition peer-checked:border-brand-900 peer-checked:bg-brand-900 peer-checked:text-white hover:border-brand-400">{{ $size }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @php($_maxRange = (int) ($maxPrice ?? 5000000))
                    <div x-data="{
                        min: {{ request('min') ?: 0 }},
                        max: {{ request('max') ?: $_maxRange }},
                        minRange: 0,
                        maxRange: {{ $_maxRange }},
                        step: 10000,
                        format(n) { return n.toLocaleString('fa-IR'); }
                    }">
                        <span class="mb-2 block text-sm font-semibold text-brand-800">محدوده قیمت (تومان)</span>
                        <div class="relative h-2 rounded-full bg-brand-100 mt-6 mb-3">
                            <div class="absolute h-full rounded-full bg-brand-900"
                                :style="'right:' + ((min - minRange) / (maxRange - minRange) * 100) + '%' +
                                        'left:' + ((maxRange - max) / (maxRange - minRange) * 100) + '%'">
                            </div>
                            <input type="range" x-model="min" :min="minRange" :max="maxRange" :step="step"
                                   class="absolute inset-0 w-full appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white [&::-webkit-slider-thumb]:ring-2 [&::-webkit-slider-thumb]:ring-brand-900 [&::-webkit-slider-thumb]:shadow-md [&::-webkit-slider-thumb]:cursor-grab">
                            <input type="range" x-model="max" :min="minRange" :max="maxRange" :step="step"
                                   class="absolute inset-0 w-full appearance-none bg-transparent pointer-events-none [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:h-5 [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white [&::-webkit-slider-thumb]:ring-2 [&::-webkit-slider-thumb]:ring-brand-900 [&::-webkit-slider-thumb]:shadow-md [&::-webkit-slider-thumb]:cursor-grab">
                        </div>
                        <div class="flex items-center justify-between text-xs text-brand-500">
                            <span x-text="format(min)"></span>
                            <span>—</span>
                            <span x-text="format(max)"></span>
                        </div>
                        {{-- Submit min/max only when narrowed from the full range,
                             so an untouched slider doesn't add a phantom price filter. --}}
                        <input type="hidden" name="min" :value="min > minRange ? min : ''">
                        <input type="hidden" name="max" :value="max < maxRange ? max : ''">
                    </div>

                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-brand-700 transition hover:bg-brand-50">
                        <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock')) onchange="this.form.requestSubmit()" class="accent-brand-900">
                        فقط کالاهای موجود
                    </label>

                    <div class="flex gap-2">
                        <button type="submit"
                                class="flex-1 rounded-xl bg-brand-900 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800 active:scale-[0.98]">اعمال فیلتر</button>
                        <a href="{{ route('shop.index') }}"
                           class="grid place-items-center rounded-xl px-3 text-sm text-brand-500 ring-1 ring-brand-200 transition hover:bg-brand-50">پاک</a>
                    </div>
                </form>
            </aside>

            {{-- Results --}}
            <div>
                <div x-data="{ view: localStorage.getItem('shop_view') || 'grid', loading: false }"
                     x-on:shop-loading.window="loading = true">
                {{-- Sort bar --}}
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-brand-400">مرتب‌سازی:</span>
                        <form action="{{ route('shop.index') }}" method="GET" id="sort-form">
                            @foreach (request()->except('sort', 'page') as $k => $v)
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endforeach
                            <select name="sort" onchange="document.getElementById('sort-form').requestSubmit()"
                                    class="cursor-pointer rounded-xl border border-brand-200 bg-white px-3 py-2 text-sm outline-none transition focus:border-brand-400">
                                <option value="newest" @selected(request('sort') === 'newest' || ! request('sort'))>جدیدترین</option>
                                <option value="price_asc" @selected(request('sort') === 'price_asc')>ارزان‌ترین</option>
                                <option value="price_desc" @selected(request('sort') === 'price_desc')>گران‌ترین</option>
                                <option value="popular" @selected(request('sort') === 'popular')>پربازدیدترین</option>
                                <option value="bestseller" @selected(request('sort') === 'bestseller')>پرفروش‌ترین</option>
                            </select>
                        </form>
                    </div>
                    <div class="flex items-center gap-1 rounded-xl border border-brand-200 p-0.5">
                        <button @click="view = 'grid'; localStorage.setItem('shop_view', 'grid')"
                                :class="view === 'grid' ? 'bg-brand-900 text-white' : 'text-brand-400 hover:text-brand-600'"
                                class="rounded-lg p-1.5 transition" aria-label="نمایش گرید">
                            <svg aria-hidden="true" class="h-4 w-4" fill="currentColor" viewBox="0 0 16 16"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5z"/></svg>
                        </button>
                        <button @click="view = 'list'; localStorage.setItem('shop_view', 'list')"
                                :class="view === 'list' ? 'bg-brand-900 text-white' : 'text-brand-400 hover:text-brand-600'"
                                class="rounded-lg p-1.5 transition" aria-label="نمایش لیست">
                            <svg aria-hidden="true" class="h-4 w-4" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Skeleton overlay while navigating --}}
                <div x-show="loading" x-cloak>
                    <div :class="view === 'list' ? 'space-y-4' : 'grid grid-cols-2 gap-4 sm:grid-cols-3'">
                        <template x-if="view === 'grid'">
                            <template x-for="i in 6">
                                <div class="animate-pulse rounded-2xl bg-white ring-1 ring-brand-100 overflow-hidden">
                                    <div class="aspect-[3/4] bg-brand-100"></div>
                                    <div class="p-3 space-y-2">
                                        <div class="h-3 w-3/4 rounded bg-brand-100"></div>
                                        <div class="h-3 w-1/2 rounded bg-brand-100"></div>
                                        <div class="h-4 w-1/3 rounded bg-brand-100 mt-2"></div>
                                    </div>
                                </div>
                            </template>
                        </template>
                        <template x-if="view === 'list'">
                            <template x-for="i in 4">
                                <div class="animate-pulse flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-brand-100">
                                    <div class="aspect-[3/4] w-24 shrink-0 rounded-xl bg-brand-100"></div>
                                    <div class="flex-1 space-y-2 py-1">
                                        <div class="h-3 w-3/4 rounded bg-brand-100"></div>
                                        <div class="h-3 w-1/3 rounded bg-brand-100"></div>
                                        <div class="h-4 w-1/4 rounded bg-brand-100 mt-4"></div>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </div>
                </div>

                <div x-show="!loading">
                @if ($products->isEmpty())
                    <x-empty-state
                        icon="search"
                        title="نتیجه‌ای پیدا نشد"
                        caption="با این فیلترها محصولی موجود نیست. می‌توانید فیلترها را پاک کنید یا یک عبارت دیگر را امتحان کنید."
                        :cta="['label' => 'حذف فیلترها', 'href' => route('shop.index')]" />
                @else
                    <div :class="view === 'list' ? 'space-y-4' : 'grid grid-cols-2 gap-4 sm:grid-cols-3'">
                        @foreach ($products as $product)
                            <template x-if="view === 'list'">
                                <div class="group flex gap-4 rounded-2xl bg-white p-4 ring-1 ring-brand-100">
                                    @php($_secondaryImg = $product->images->skip(1)->first()?->path)
                                    <a href="{{ route('product.show', $product) }}" class="relative aspect-[3/4] w-24 shrink-0 overflow-hidden rounded-xl bg-brand-50">
                                        <img src="{{ $product->primary_image_url ?? '/placeholder?w=200&h=250&label='.urlencode($product->name) }}" alt="{{ $product->name }}" loading="lazy"
                                             class="h-full w-full object-cover transition-opacity duration-500 {{ $_secondaryImg ? '[@media(hover:hover)]:group-hover:opacity-0' : '' }}">
                                        @if ($_secondaryImg)
                                            <img src="{{ $_secondaryImg }}" alt="" aria-hidden="true" loading="lazy"
                                                 class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500 pointer-events-none [@media(hover:hover)]:group-hover:opacity-100">
                                        @endif
                                    </a>
                                    <div class="flex min-w-0 flex-1 flex-col">
                                        <a href="{{ route('product.show', $product) }}" class="truncate text-sm font-semibold text-brand-800">{{ $product->name }}</a>
                                        <p class="mt-1 text-xs text-brand-400">{{ $product->category?->name }}</p>
                                        <div class="mt-auto flex items-center justify-between">
                                            <span class="text-sm font-bold text-brand-900 fa-num">{{ \App\Support\Money::toman($product->price) }}</span>
                                            <form action="{{ route('cart.add') }}" method="POST" class="m-0">
                                                @csrf
                                                <input type="hidden" name="variant_id" value="{{ $product->variants->first()?->id }}">
                                                <input type="hidden" name="quantity" value="1">
                                                <button class="rounded-full bg-brand-900 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-800">افزودن</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="view === 'grid'">
                                <x-product-card :product="$product" />
                            </template>
                        @endforeach
                    </div>
                    <div class="mt-10">{{ $products->links('vendor.pagination.chiaco') }}</div>
                @endif
                </div>{{-- end x-show="!loading" --}}
            </div>
        </div>
        </div>{{-- end x-data --}}

      </div>{{-- /shop-frame (AJAX swap target) --}}

        {{-- Scroll-to-top --}}
        <button x-data="{ visible: false }" x-init="window.addEventListener('scroll', () => visible = window.scrollY > 600)" x-show="visible" x-cloak
                @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                class="fixed bottom-6 start-6 z-40 grid h-11 w-11 place-items-center rounded-full bg-brand-900 text-white shadow-lg transition hover:bg-brand-800 active:scale-95" aria-label="رفتن به بالا">
            <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 15l7-7 7 7"/></svg>
        </button>
    </div>
@endsection

@push('head')
<script defer>
    // AJAX live filter for /shop (and /gift, which reuses the same #shop-frame
    // contract). On any in-frame form submit / select change / link click that
    // would normally reload the page, we fetch the target URL and swap just
    // the #shop-frame content. URL stays in sync via history.pushState. If
    // anything goes wrong we fall through to a real navigation.
    (function () {
        const FRAME = 'shop-frame';

        function triggerLoading() { window.dispatchEvent(new CustomEvent('shop-loading')); }

        async function swapTo(url) {
            triggerLoading();
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                if (! res.ok) throw new Error('http ' + res.status);
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const next = doc.getElementById(FRAME);
                const current = document.getElementById(FRAME);
                if (! next || ! current) throw new Error('no frame in response');
                current.replaceWith(next);
                history.pushState({}, '', url);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                // Fallback: a real navigation. Better than a stuck screen.
                window.location.href = url;
            }
        }

        function inFrame(el) {
            const frame = document.getElementById(FRAME);
            return frame && frame.contains(el);
        }

        // Form submits (filter rail, sort form) — read action + serialise GET params.
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('form');
            if (! form || ! inFrame(form)) return;
            if ((form.method || 'get').toLowerCase() !== 'get') return;
            e.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            const action = form.getAttribute('action') || window.location.pathname;
            // Strip empty values so the URL stays clean.
            for (const [k, v] of [...params]) if (v === '' || v === null) params.delete(k);
            const qs = params.toString();
            swapTo(qs ? `${action}?${qs}` : action);
        }, true);

        // Link clicks that stay within the same path (pagination, active-pill ×,
        // «clear all» button) — preventDefault and swap.
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a[href]');
            if (! link || ! inFrame(link)) return;
            if (link.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey) return;
            const href = link.getAttribute('href');
            // Only intercept same-host links to /shop or /gift (or whatever path
            // the current frame lives on).
            try {
                const url = new URL(href, window.location.href);
                if (url.host !== window.location.host) return;
                if (! url.pathname.startsWith(window.location.pathname.split('?')[0]) &&
                    url.pathname !== window.location.pathname) {
                    // Different path entirely — let the browser handle it.
                    return;
                }
            } catch (_) { return; }
            e.preventDefault();
            swapTo(href);
        });

        // Back/forward — re-fetch and swap.
        window.addEventListener('popstate', function () {
            swapTo(window.location.href);
        });
    })();
</script>
@endpush

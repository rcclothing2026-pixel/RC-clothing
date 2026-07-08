<header data-header
        x-data="{ navOpen: false }"
        x-effect="document.documentElement.style.overflow = navOpen ? 'hidden' : ''"
        @keydown.escape.window="navOpen = false"
        class="sticky top-0 z-50 border-b border-brand-100 bg-paper/85 backdrop-blur transition-colors duration-300">
    {{-- Layout: mobile = flex row (hamburger · logo · search · actions). On lg+
         switches to a 3-column grid (nav · CENTERED wordmark · search+actions)
         so the wordmark sits dead-centre regardless of nav width. --}}
    {{-- Desktop centring uses pure flex (NO grid, no display-switching): the
         nav and the search/actions groups each take `lg:flex-1`, and the logo
         sits between them at natural width → dead-centre, always on one row.
         Explicit `lg:order-*` fixes the visual order regardless of DOM order. --}}
    <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-2.5 sm:px-6 lg:gap-6 lg:py-2.5">
        {{-- Mobile menu toggle (mobile only; sits before logo in flex order) --}}
        <button type="button" @click="navOpen = true" :aria-expanded="navOpen" aria-label="Menu" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-brand-700 ring-1 ring-brand-200 lg:hidden">
            <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        {{-- Logo — inline on mobile; centred between the two flex-1 groups on lg+ --}}
        <a href="{{ route('home') }}" class="shrink-0 lg:order-2">
            <x-brand-logo class="text-brand-900" />
        </a>

        {{-- Primary nav (admin-managed menu, else default: shop + categories).
             Items with children render as a desktop mega-panel: a wide image
             grid where each card pulls its image from the matching Category by
             URL slug (no schema change). Non-category children show as labels. --}}
        @php($headerMenu = \App\Models\MenuItem::for('header'))
        <nav class="hidden items-center gap-1 lg:order-1 lg:flex lg:flex-1 lg:justify-start">
            @if ($headerMenu->isNotEmpty())
                @foreach ($headerMenu as $item)
                    @php($__active = trim(request()->path(), '/') === trim((string) parse_url($item->url, PHP_URL_PATH), '/'))
                    @if ($item->children->isNotEmpty())
                        <div class="group static">
                            <a href="{{ $item->url }}" @if($__active) aria-current="page" @endif class="relative inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-brand-100 {{ $__active ? 'text-accent-600' : 'text-brand-700' }}">
                                {{ $item->label }}
                                <svg aria-hidden="true" class="h-3.5 w-3.5 text-brand-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                                {{-- Invisible hover-bridge spanning the gap between the trigger
                                     and the mega panel, so moving the pointer down to a sub-item
                                     doesn't drop group-hover and close the panel. --}}
                                <span aria-hidden="true" class="absolute inset-x-0 top-full h-5"></span>
                            </a>
                            {{-- Mega panel: full-width below the header bar --}}
                            <div class="invisible absolute inset-x-0 top-full z-40 translate-y-1 border-t border-brand-100 bg-white opacity-0 shadow-xl transition group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                <div class="mx-auto max-w-7xl px-6 py-8 sm:px-10">
                                    {{-- Resolve each child's image: its own uploaded image wins,
                                         else the matching shop category's photo (by URL slug), else
                                         none. The picture is OPTIONAL — items without one are plain
                                         text links, and images only reveal on hover (no empty boxes). --}}
                                    @php($kids = $item->children->map(function ($child) use ($navCategories) {
                                        $url = (string) $child->url;
                                        $slug = preg_match('/category=([^&#]+)/', $url, $mm)
                                            ? urldecode($mm[1])
                                            : (preg_match('~/shop/(?:category/)?([^/?#]+)~', $url, $mm) ? $mm[1] : null);
                                        $img = ($child->image ?: ($slug ? optional($navCategories->firstWhere('slug', $slug))->image_path : null)) ?: '';
                                        return ['url' => $child->url, 'label' => $child->label, 'img' => $img];
                                    }))
                                    @php($firstImg = optional($kids->firstWhere('img', '!=', ''))['img'] ?? '')
                                    @if ($firstImg !== '')
                                        {{-- At least one sub-item has a picture → label list + a large
                                             preview pane that swaps to the hovered item's image. --}}
                                        {{-- Compact cluster (w-max) so the panel doesn't stretch the
                                             preview across the full bar — list + a modest portrait image. --}}
                                        <div x-data="{ img: @js($firstImg), label: '' }" class="flex w-max items-center gap-6">
                                            <ul class="w-56 shrink-0 space-y-0.5">
                                                @foreach ($kids as $kid)
                                                    <li>
                                                        <a href="{{ $kid['url'] }}"
                                                           @if ($kid['img'] !== '') @mouseenter="img = @js($kid['img']); label = @js($kid['label'])" @endif
                                                           class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-50 hover:text-accent-600">
                                                            <span>{{ $kid['label'] }}</span>
                                                            @if ($kid['img'] !== '')
                                                                <svg aria-hidden="true" class="h-3.5 w-3.5 -scale-x-100 text-brand-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                                                            @endif
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <div class="relative hidden w-64 shrink-0 overflow-hidden rounded-2xl bg-brand-50 ring-1 ring-brand-100 md:block" style="height:20rem">
                                                <img :src="img" alt="" x-show="img"
                                                     class="absolute inset-0 h-full w-full object-cover transition-opacity duration-300">
                                                <span x-show="label" x-text="label"
                                                      class="pointer-events-none absolute bottom-3 start-3 rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white backdrop-blur"></span>
                                            </div>
                                        </div>
                                    @else
                                        {{-- No images set anywhere → tidy text-link list, no empty boxes. --}}
                                        <ul class="flex flex-wrap gap-x-8 gap-y-1">
                                            @foreach ($kids as $kid)
                                                <li>
                                                    <a href="{{ $kid['url'] }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-50 hover:text-accent-600">{{ $kid['label'] }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->url }}" @if($__active) aria-current="page" @endif class="rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-brand-100 {{ $__active ? 'text-accent-600' : 'text-brand-700' }}">{{ $item->label }}</a>
                    @endif
                @endforeach
            @else
                <a href="{{ route('shop.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100">Shop</a>
                @foreach ($navCategories->take(5) as $category)
                    <a href="{{ route('shop.index', ['category' => $category->slug]) }}"
                       class="rounded-lg px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100">{{ $category->name }}</a>
                @endforeach
            @endif
        </nav>

        {{-- Right group: search + actions — flex-1 on the left side (RTL) on
             desktop, inline via `contents` on mobile. --}}
        <div class="contents lg:order-3 lg:flex lg:flex-1 lg:items-center lg:justify-end lg:gap-2">
        {{-- Search — live typeahead (Alpine) backed by /search/suggest; Enter
             still submits the full search page. --}}
        <form action="{{ route('shop.index') }}" method="GET"
              x-data="{ q: @js(request('q') ?? ''), results: [], open: false,
                        fetchResults() {
                            if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                            fetch('{{ route('shop.suggest') }}?q=' + encodeURIComponent(this.q))
                                .then(r => r.json())
                                .then(d => { this.results = Array.isArray(d) ? d : []; this.open = this.results.length > 0; })
                                .catch(() => { this.results = []; this.open = false; });
                        } }"
              @click.outside="open = false"
              class="relative flex flex-1 max-w-xs ms-auto md:ms-0 lg:flex-initial lg:w-56 lg:ms-0">
            <div class="relative w-full">
                <input type="search" name="q" x-model="q" autocomplete="off"
                       @input.debounce.250ms="fetchResults()" @focus="results.length && (open = true)"
                       placeholder="Search"
                       class="w-full rounded-full border border-brand-200 bg-brand-50/60 py-2 ps-9 pe-3 text-sm outline-none transition placeholder:text-brand-300 focus:border-brand-400 focus:bg-white focus:shadow-sm focus:ring-2 focus:ring-brand-100 md:ps-10 md:pe-4">
                <svg aria-hidden="true" class="pointer-events-none absolute top-2.5 start-3 h-4 w-4 text-brand-300 md:start-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            </div>

            {{-- Results dropdown --}}
            <div x-show="open && results.length" x-cloak x-transition.opacity
                 class="absolute end-0 start-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-brand-100 bg-white shadow-xl">
                <template x-for="r in results" :key="r.url">
                    <a :href="r.url" class="flex items-center gap-3 px-3 py-2 transition hover:bg-brand-50">
                        <img :src="r.image" x-show="r.image" alt="" loading="lazy"
                             class="h-11 w-9 shrink-0 rounded-md bg-brand-50 object-cover ring-1 ring-brand-100">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-brand-800" x-text="r.name"></span>
                            <span class="block text-xs font-medium text-accent-600 fa-num" x-text="r.price"></span>
                        </span>
                    </a>
                </template>
                <button type="submit"
                        class="block w-full border-t border-brand-100 px-3 py-2.5 text-center text-xs font-medium text-brand-600 transition hover:bg-brand-50">
                    View all results
                </button>
            </div>
        </form>

        {{-- Actions --}}
        <div class="flex items-center gap-0.5 ms-auto md:ms-0 lg:ms-0">
            {{-- Language toggle (EN / FA) --}}
            @php($__loc = app()->getLocale())
            <div class="me-1 flex items-center gap-1 text-xs font-semibold tracking-wide" aria-label="Language">
                <a href="{{ route('locale.switch', 'en') }}" hreflang="en"
                   class="{{ $__loc === 'en' ? 'text-accent-600' : 'text-brand-400 hover:text-brand-900' }}">EN</a>
                <span class="text-brand-300" aria-hidden="true">/</span>
                <a href="{{ route('locale.switch', 'fa') }}" hreflang="fa"
                   class="{{ $__loc === 'fa' ? 'text-accent-600' : 'text-brand-400 hover:text-brand-900' }}">فا</a>
            </div>
            <a href="{{ auth()->check() ? route('account.index') : route('login') }}"
               class="rounded-lg p-2 text-brand-600 hover:bg-brand-100 hover:text-brand-900" aria-label="Account">
                <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            </a>
            @auth
            <a href="{{ route('wishlist.index') }}"
               class="rounded-lg p-2 text-brand-600 hover:bg-brand-100 hover:text-brand-900" aria-label="Wishlist">
                <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-9.5-8.5C.5 9 2 5.5 5.5 5.5 7.5 5.5 9 7 12 9c3-2 4.5-3.5 6.5-3.5 3.5 0 5 3.5 3 7C19 16.5 12 21 12 21z"/></svg>
            </a>
            @endauth
            <a href="{{ route('cart.index') }}" class="relative rounded-lg p-2 text-brand-600 hover:bg-brand-100 hover:text-brand-900" aria-label="Cart">
                <svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6 5 3H3"/></svg>
                <span data-cart-count
                      class="absolute -top-0.5 -end-0.5 grid h-4 w-4 place-items-center rounded-full bg-accent-600 text-[10px] font-bold text-white fa-num {{ ($cartCount ?? 0) > 0 ? '' : 'hidden' }}">{{ (string) ($cartCount ?? 0) }}</span>
            </a>
        </div>
        </div>{{-- /right group --}}
    </div>

    {{-- Mobile menu drawer (off-canvas). Parent items with children collapse
         into tap-to-expand accordions instead of dumping every sub-item flat,
         so a big catalogue stays scannable. --}}
    <div class="lg:hidden">
        {{-- Scrim --}}
        <div x-show="navOpen" x-cloak x-transition.opacity @click="navOpen = false"
             class="fixed inset-0 z-[55] bg-brand-950/40"></div>

        {{-- Panel (slides from the start side = right in RTL) --}}
        <div x-show="navOpen" x-cloak
             x-transition:enter="transition transform ease-out duration-300"
             x-transition:enter-start="translate-x-full rtl:-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition transform ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full rtl:-translate-x-full"
             class="fixed inset-y-0 start-0 z-[56] flex w-[86%] max-w-sm flex-col bg-paper shadow-2xl"
             role="dialog" aria-modal="true" aria-label="Mobile menu">

            <div class="flex items-center justify-between border-b border-brand-100 px-4 py-3">
                <a href="{{ route('home') }}" @click="navOpen = false"><x-brand-logo class="text-brand-900" /></a>
                <button type="button" @click="navOpen = false" aria-label="Close"
                        class="grid h-9 w-9 place-items-center rounded-full text-brand-500 ring-1 ring-brand-200 transition hover:bg-brand-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-3 py-3">
                @if ($headerMenu->isNotEmpty())
                    @foreach ($headerMenu as $item)
                        @if ($item->children->isNotEmpty())
                            <div x-data="{ open: false }" class="border-b border-brand-50">
                                <div class="flex items-center">
                                    <a href="{{ $item->url }}" @click="navOpen = false"
                                       class="flex-1 rounded-lg px-3 py-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-50">{{ $item->label }}</a>
                                    <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Submenu"
                                            class="grid h-11 w-11 shrink-0 place-items-center rounded-lg text-brand-400 transition hover:bg-brand-50">
                                        <svg class="h-4 w-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                                    </button>
                                </div>
                                <div x-show="open" x-collapse x-cloak>
                                    <div class="pb-2">
                                        @foreach ($item->children as $child)
                                            <a href="{{ $child->url }}" @click="navOpen = false"
                                               class="block rounded-lg py-2.5 pe-3 ps-6 text-sm text-brand-500 transition hover:bg-brand-50 hover:text-accent-600">{{ $child->label }}</a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <a href="{{ $item->url }}" @click="navOpen = false"
                               class="block border-b border-brand-50 rounded-lg px-3 py-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-50">{{ $item->label }}</a>
                        @endif
                    @endforeach
                @else
                    <a href="{{ route('shop.index') }}" @click="navOpen = false" class="block rounded-lg px-3 py-3 text-sm font-semibold text-brand-800 transition hover:bg-brand-50">Shop</a>
                    @foreach ($navCategories->take(8) as $category)
                        <a href="{{ route('shop.index', ['category' => $category->slug]) }}" @click="navOpen = false"
                           class="block rounded-lg px-3 py-2.5 text-sm text-brand-600 transition hover:bg-brand-50">{{ $category->name }}</a>
                    @endforeach
                @endif
            </nav>

            {{-- Quick actions --}}
            <div class="flex items-center justify-around border-t border-brand-100 px-3 py-3 text-xs text-brand-600">
                <a href="{{ auth()->check() ? route('account.index') : route('login') }}" @click="navOpen = false" class="flex flex-col items-center gap-1 rounded-lg px-4 py-2 transition hover:bg-brand-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    Account
                </a>
                @auth
                <a href="{{ route('wishlist.index') }}" @click="navOpen = false" class="flex flex-col items-center gap-1 rounded-lg px-4 py-2 transition hover:bg-brand-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 21s-7-4.5-9.5-8.5C.5 9 2 5.5 5.5 5.5 7.5 5.5 9 7 12 9c3-2 4.5-3.5 6.5-3.5 3.5 0 5 3.5 3 7C19 16.5 12 21 12 21z"/></svg>
                    Wishlist
                </a>
                @endauth
                <a href="{{ route('cart.index') }}" @click="navOpen = false" class="flex flex-col items-center gap-1 rounded-lg px-4 py-2 transition hover:bg-brand-50">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6 5 3H3"/></svg>
                    Cart
                </a>
            </div>
        </div>
    </div>
</header>

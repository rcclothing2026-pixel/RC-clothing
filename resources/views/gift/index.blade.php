@extends('layouts.app')

@section('title', 'Gifts | Racket Club')
@section('meta_description', 'Find the perfect gift for any occasion — filter, sort, or let Surprise Me choose for you.')

@section('content')
    {{-- TOP: editable page-builder hero (slug=`gift`). Admin can change the
         hero / banners / copy in the page builder without touching code. --}}
    @if ($page)
        @foreach ($page->blockList() as $block)
            @include('blocks.render', ['block' => $block])
        @endforeach
    @endif

    {{-- INTERACTIVE GRID — wrapped in #shop-frame so the AJAX swap script
         from shop/index works here too (same script, copied inline below). --}}
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
      <div id="shop-frame">

        {{-- Header: count + «Surprise me» CTA --}}
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-brand-900">Find the Perfect Gift</h2>
                <p class="mt-1 text-sm text-brand-500 fa-num">{{ $products->total() }} options</p>
            </div>
            <a href="{{ route('gift.surprise') }}"
               class="inline-flex items-center gap-2 rounded-full bg-accent-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-accent-700">
                Surprise Me (under 500,000 Toman)
            </a>
        </div>

        {{-- Facet chips (collection-driven) --}}
        @if ($facets->isNotEmpty())
            <div class="mb-5 flex flex-wrap gap-2">
                <a href="{{ route('gift.index') }}"
                   class="rounded-full px-4 py-1.5 text-xs font-medium transition {{ ! $activeFacet ? 'bg-brand-900 text-white' : 'bg-brand-100 text-brand-700 hover:bg-brand-200' }}">
                    All
                </a>
                @foreach ($facets as $facet)
                    <a href="{{ request()->fullUrlWithQuery(['collection' => $facet->slug, 'page' => null]) }}"
                       class="rounded-full px-4 py-1.5 text-xs font-medium transition {{ $activeFacet?->id === $facet->id ? 'bg-brand-900 text-white' : 'bg-brand-100 text-brand-700 hover:bg-brand-200' }}">
                        {{ $facet->name }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Active filter chips (price + collection × buttons) --}}
        @php($hasFilters = $activeFacet || request('min') || request('max'))
        @if ($hasFilters)
            <div class="mb-5 flex flex-wrap gap-2">
                @if ($activeFacet)
                    <a href="{{ request()->fullUrlWithQuery(['collection' => null, 'page' => null]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-100">
                        {{ $activeFacet->name }}
                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if (request('min') || request('max'))
                    <a href="{{ request()->fullUrlWithQuery(['min' => null, 'max' => null, 'page' => null]) }}"
                       class="flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-100">
                        Price: {{ request('min') ? \App\Support\Money::toman((int)request('min')) : '' }}{{ request('min') && request('max') ? ' — ' : '' }}{{ request('max') ? \App\Support\Money::toman((int)request('max')) : '' }}
                        <svg aria-hidden="true" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                <a href="{{ route('gift.index') }}"
                   class="rounded-full px-3 py-1.5 text-xs text-brand-400 transition hover:text-brand-700">Clear filters</a>
            </div>
        @endif

        {{-- Compact filter / sort bar --}}
        <form action="{{ route('gift.index') }}" method="GET" class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-brand-100">
            @if ($activeFacet)
                <input type="hidden" name="collection" value="{{ $activeFacet->slug }}">
            @endif
            <label class="flex items-center gap-2 text-xs text-brand-500">
                Max price:
                <select name="max" onchange="this.form.requestSubmit()" class="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs">
                    <option value="">Any price</option>
                    @foreach ([200_000, 500_000, 1_000_000, 2_000_000, 5_000_000] as $bp)
                        <option value="{{ $bp }}" @selected((int) request('max') === $bp)>Up to {{ \App\Support\Money::toman($bp) }}</option>
                    @endforeach
                </select>
            </label>
            <span class="hidden h-5 w-px bg-brand-100 sm:block"></span>
            <label class="flex items-center gap-2 text-xs text-brand-500">
                Sort:
                <select name="sort" onchange="this.form.requestSubmit()" class="rounded-lg border border-brand-200 bg-white px-3 py-1.5 text-xs">
                    <option value="newest"     @selected(request('sort') === 'newest' || ! request('sort'))>Newest</option>
                    <option value="price_asc"  @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                </select>
            </label>
        </form>

        {{-- Grid --}}
        @if ($products->isEmpty())
            <x-empty-state
                icon="search"
                title="Nothing found"
                caption="Try loosening your filters, or let Surprise Me pick something for you."
                :cta="['label' => 'Clear filters', 'href' => route('gift.index')]" />
        @else
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
            <div class="mt-10">{{ $products->links('vendor.pagination.chiaco') }}</div>
        @endif

      </div>{{-- /shop-frame --}}
    </div>
@endsection

@push('head')
{{-- AJAX-swap script — identical contract to /shop. Submitting a form,
     changing a select, clicking a facet/pill/pagination link inside
     #shop-frame fetches the URL and swaps the frame, no full reload. --}}
<script defer>
    (function () {
        const FRAME = 'shop-frame';
        async function swapTo(url) {
            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                if (! res.ok) throw new Error('http ' + res.status);
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const next = doc.getElementById(FRAME);
                const current = document.getElementById(FRAME);
                if (! next || ! current) throw new Error('no frame');
                current.replaceWith(next);
                history.pushState({}, '', url);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (_) { window.location.href = url; }
        }
        function inFrame(el) {
            const frame = document.getElementById(FRAME);
            return frame && frame.contains(el);
        }
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('form');
            if (! form || ! inFrame(form)) return;
            if ((form.method || 'get').toLowerCase() !== 'get') return;
            e.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            for (const [k, v] of [...params]) if (v === '' || v === null) params.delete(k);
            const action = form.getAttribute('action') || window.location.pathname;
            const qs = params.toString();
            swapTo(qs ? `${action}?${qs}` : action);
        }, true);
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a[href]');
            if (! link || ! inFrame(link)) return;
            if (link.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey) return;
            try {
                const url = new URL(link.getAttribute('href'), window.location.href);
                if (url.host !== window.location.host) return;
                if (url.pathname !== window.location.pathname &&
                    ! url.pathname.startsWith(window.location.pathname.split('?')[0])) return;
            } catch (_) { return; }
            e.preventDefault();
            swapTo(link.getAttribute('href'));
        });
        window.addEventListener('popstate', () => swapTo(window.location.href));
    })();
</script>
@endpush

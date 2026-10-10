{{--
    Mobile bottom tab bar (mobile/tablet only; lg+ uses the top nav). Fixed to the
    viewport bottom with iOS safe-area padding. Cart count reuses the global
    $cartCount composer + the shared [data-cart-count] hook, so AJAX add-to-cart
    updates it live everywhere. The Search tab opens the header's inline search
    via a window event (partials/header listens with @open-search.window).

    z-30 sits *below* the product page's sticky add-to-cart bar (z-40) so that bar
    covers the nav while you're buying, and below the drawers (z-50+). Body gets a
    matching padding-bottom (layouts/app) so the footer clears the bar.
--}}
@php($tab = 'flex flex-col items-center justify-center gap-1 py-2 text-[10px] font-medium leading-none transition')
<nav aria-label="{{ __('Primary') }}"
     class="fixed inset-x-0 bottom-0 z-30 border-t border-brand-100 bg-paper/95 backdrop-blur lg:hidden"
     style="padding-bottom: env(safe-area-inset-bottom, 0px)">
    <div class="mx-auto grid max-w-lg grid-cols-5">
        {{-- Home --}}
        <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif
           class="{{ $tab }} {{ request()->routeIs('home') ? 'text-accent-600' : 'text-brand-500' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg>
            {{ __('Home') }}
        </a>
        {{-- Shop --}}
        <a href="{{ route('shop.index') }}" @if(request()->routeIs('shop.*') || request()->routeIs('product.*')) aria-current="page" @endif
           class="{{ $tab }} {{ request()->routeIs('shop.*') || request()->routeIs('product.*') ? 'text-accent-600' : 'text-brand-500' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M6 2 4 6v13a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V6l-2-4z"/><path d="M4 6h16"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            {{ __('Shop') }}
        </a>
        {{-- Search — opens the header's inline search --}}
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-search')); window.scrollTo({top:0,behavior:'smooth'}); setTimeout(function(){document.getElementById('site-search-input')?.focus()},80)"
                class="{{ $tab }} text-brand-500" aria-label="{{ __('Search') }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            {{ __('Search') }}
        </button>
        {{-- Cart --}}
        <a href="{{ route('cart.index') }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif
           class="{{ $tab }} {{ request()->routeIs('cart.*') ? 'text-accent-600' : 'text-brand-500' }}">
            <span class="relative">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M6 6 5 3H3"/></svg>
                <span data-cart-count
                      class="absolute -top-2 -end-2 grid h-4 w-4 place-items-center rounded-full bg-accent-600 text-[9px] font-bold text-white fa-num {{ ($cartCount ?? 0) > 0 ? '' : 'hidden' }}">{{ (string) ($cartCount ?? 0) }}</span>
            </span>
            {{ __('Cart') }}
        </a>
        {{-- Account --}}
        <a href="{{ auth()->check() ? route('account.index') : route('login') }}" @if(request()->routeIs('account.*') || request()->routeIs('login')) aria-current="page" @endif
           class="{{ $tab }} {{ request()->routeIs('account.*') || request()->routeIs('login') ? 'text-accent-600' : 'text-brand-500' }}">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
            {{ __('Account') }}
        </a>
    </div>
</nav>

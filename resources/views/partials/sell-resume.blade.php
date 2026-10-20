{{--
    «Your cart» pill for a customer who opened an Instagram purchase link and then
    browsed the site: one tap back to their cart → checkout. Only while that link
    is in their session and the cart has items; hidden where a page has its own
    bottom bar (product page, cart, checkout, the link page itself).
    Mobile: floats above the bottom nav and the free-delivery pill (bottom-20).
    Desktop: bottom-start corner, lifted over the cookie bar until it's accepted.
--}}
@if (session('sell_link_id') && ! request()->routeIs('sell.show', 'cart.*', 'checkout.*', 'product.show', 'login*', 'profile.*'))
    @php $__sellCart = app(\App\Services\Cart\Cart::class); @endphp
    @if (! $__sellCart->isEmpty())
        <a href="{{ route('checkout.index') }}"
           x-data="{ lift: false, check() { try { this.lift = ! localStorage.getItem('cookie_consent') } catch (e) {} } }"
           x-init="check()" @click.window="setTimeout(() => check(), 0)"
           :class="lift ? 'lg:bottom-24' : 'lg:bottom-4'"
           class="fixed inset-x-4 bottom-40 z-[45] flex h-16 items-center justify-between rounded-lg bg-brand-900 px-4 text-white shadow-xl ring-1 ring-white/20 lg:inset-x-auto lg:start-4 lg:w-96">
            <span class="flex flex-col leading-tight">
                <span class="text-xs text-brand-200">{{ __('Your cart') }} · <span class="fa-num">{{ \App\Support\Money::toPersianDigits((string) $__sellCart->count()) }}</span> {{ __('items') }}</span>
                <span class="text-[15px] font-bold">{{ \App\Support\Money::toman($__sellCart->total()) }}</span>
            </span>
            <span class="flex h-10 items-center rounded-lg bg-accent-600 px-4 text-sm font-bold">{{ __('Continue to payment') }}</span>
        </a>
    @endif
@endif

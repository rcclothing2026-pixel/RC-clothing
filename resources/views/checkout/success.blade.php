@extends('layouts.app')

@section('title', 'پرداخت موفق | چیاکو')

@push('analytics')
{{-- GA4 e-commerce purchase. Fires through whichever Google stack the site
     uses: GA4 direct (when site.ga4_id is set, gtag is on the page) AND/OR
     GTM (the dataLayer.push is what a GA4 Event tag listens to). Shape follows
     the official GA4 'purchase' event spec so GTM picks it up with no custom
     variables. Currency is reported as IRR (Toman × 10) since GA4 has no IRT
     code; the multiplication keeps revenue numbers comparable to receipts.
     transaction_id = order number gives GA4 built-in dedup against retries. --}}
<script>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ ecommerce: null }); // clear any prior ecommerce payload
    window.dataLayer.push({
        event: 'purchase',
        ecommerce: {
            transaction_id: @json((string) $order->number),
            value: {{ (int) $order->total * 10 }},
            tax: 0,
            shipping: {{ (int) ($order->shipping_cost ?? 0) * 10 }},
            currency: 'IRR',
            coupon: @json((string) ($order->coupon_code ?? '')),
            items: [
                @foreach ($order->items as $i)
                {
                    item_id: @json((string) ($i->sku ?: $i->product_variant_id)),
                    item_name: @json((string) $i->name),
                    item_variant: @json(trim(($i->size ?? '').' '.($i->color ?? ''))),
                    price: {{ (int) $i->unit_price * 10 }},
                    quantity: {{ (int) $i->quantity }},
                },
                @endforeach
            ],
        },
    });
    // Mirror to gtag when the GA4-ID-only path is configured (no GTM tag) — the
    // dataLayer.push is a no-op for direct GA4, gtag is a no-op when only GTM is used.
    if (typeof gtag === 'function') {
        gtag('event', 'purchase', window.dataLayer[window.dataLayer.length - 1].ecommerce);
    }
</script>
@endpush

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center sm:px-6">
        {{-- Confetti particles --}}
        <div class="pointer-events-none fixed inset-0 z-50 overflow-hidden" aria-hidden="true">
            @for ($i = 0; $i < 20; $i++)
                <div class="absolute h-2 w-2 animate-float-slow rounded-full opacity-70"
                     style="left: {{ rand(5, 95) }}%; top: -5%; background: {{ ['#CC3333','#282828','#F5A623','#34D399','#60A5FA'][rand(0,4)] }}; animation-delay: {{ rand(0, 3) }}s; animation-duration: {{ 3 + rand(0, 3) }}s;"></div>
            @endfor
        </div>

        <div class="relative rounded-card bg-white p-10 ring-1 ring-brand-100" x-data="{ showItems: false }">
            <div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-full bg-green-100 text-green-600 animate-bounce">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
            </div>
            <h1 class="text-xl font-bold text-brand-900">سفارش شما با موفقیت ثبت شد</h1>
            <p class="mt-2 text-sm text-brand-500">شماره سفارش: <span class="font-semibold text-brand-800 fa-num" dir="ltr">{{ $order->number }}</span></p>
            @if ($order->payment?->ref_id)
                <p class="mt-1 text-xs text-brand-400">کد پیگیری پرداخت: <span class="fa-num" dir="ltr">{{ \App\Support\Money::toPersianDigits($order->payment->ref_id) }}</span></p>
            @endif
            <p class="mt-4 text-lg font-bold text-brand-900">{{ $order->formattedTotal() }}</p>
            @if ($order->gift_wrap)
                <p class="mt-2 text-xs text-amber-700">🎁 سفارش شما با بسته‌بندی کادویی ارسال می‌شود.</p>
            @endif
            @if ($order->shipping_cost_on_delivery)
                <p class="mt-2 text-xs text-amber-700">📦 هزینهٔ ارسال به‌صورت <b>پس‌کرایه</b> هنگام تحویل به مأمور پرداخت می‌شود.</p>
            @endif

            {{-- Collapsible order summary --}}
            <button @click="showItems = !showItems" class="mt-4 flex w-full items-center justify-center gap-1 text-xs text-brand-400 transition hover:text-brand-600">
                <span x-text="showItems ? 'بستن جزئیات' : 'مشاهده جزئیات سفارش'"></span>
                <svg :class="showItems ? 'rotate-180' : ''" class="h-3.5 w-3.5 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="showItems" x-collapse x-cloak class="mt-3 border-t border-brand-100 pt-3 text-right">
                <div class="divide-y divide-brand-50 text-sm">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-brand-800">{{ $item->name }} ×<span class="fa-num">{{ \App\Support\Money::toPersianDigits((string) $item->quantity) }}</span></span>
                            <span class="text-brand-600">{{ $item->formattedLineTotal() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Social share --}}
            <div class="mt-6 border-t border-brand-100 pt-4">
                <p class="mb-2 text-xs text-brand-400">خریدت رو با دوستات به اشتراک بذار</p>
                <div class="flex items-center justify-center gap-2">
                    <a href="https://wa.me/?text={{ urlencode('سفارش '.$order->number.' از چیاکو') }}" target="_blank" class="rounded-full bg-green-100 p-2 text-green-600 transition hover:bg-green-200" aria-label="اشتراک در واتساپ">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.117.554 4.1 1.523 5.823L0 24l6.335-1.509A11.945 11.945 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.885 0-3.655-.502-5.193-1.38l-.371-.213-3.762.895.952-3.648-.233-.384A9.96 9.96 0 0 1 2 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
                    </a>
                    <a href="https://t.me/share/url?url={{ urlencode(url()->current()) }}&text={{ urlencode('سفارش '.$order->number.' از چیاکو') }}" target="_blank" class="rounded-full bg-blue-100 p-2 text-blue-600 transition hover:bg-blue-200" aria-label="اشتراک در تلگرام">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    </a>
                    <button @click="navigator.clipboard.writeText(window.location.href); $el.querySelector('span').textContent='کپی شد'" class="rounded-full bg-brand-100 p-2 text-brand-600 transition hover:bg-brand-200" aria-label="کپی لینک">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2m-6 12h8a2 2 0 0 1 2-2v-8a2 2 0 0 1-2-2h-8a2 2 0 0 1-2 2v8a2 2 0 0 1 2 2z"/></svg>
                    </button>
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-3">
                <a href="{{ route('account.orders.show', $order) }}" class="rounded-full bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-800">مشاهده سفارش</a>
                <a href="{{ route('shop.index') }}" class="rounded-full px-6 py-2.5 text-sm font-semibold text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50">ادامه خرید</a>
            </div>
        </div>
    </div>
@endsection

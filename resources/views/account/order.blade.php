@extends('layouts.app')

@section('title', __('Order Details') . ' | Racket Club')

@php
$allStatuses = ['paid', 'processing', 'shipped', 'delivered'];
$currentIndex = array_search($order->status, $allStatuses);
$timelineLabels = [
    'paid' => __('Paid'),
    'processing' => __('Preparing'),
    'shipped' => __('Shipped'),
    'delivered' => __('Delivered'),
];
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <div class="flex items-center justify-between">
            <a href="{{ route('account.orders') }}" class="text-sm text-accent-600 hover:underline">&larr; {{ __('Back to orders') }}</a>
            <div class="flex gap-2">
                @if ($order->isPaid())
                    <a href="{{ route('account.orders.return', $order) }}" class="rounded-lg border border-brand-200 px-3 py-1.5 text-sm text-brand-700 transition hover:bg-brand-50">{{ __('Request a Return') }}</a>
                @endif
                <a href="{{ route('account.orders.invoice', $order) }}" target="_blank" class="rounded-lg bg-brand-100 px-3 py-1.5 text-sm font-medium text-brand-700 transition hover:bg-brand-200">{{ __('Invoice') }}</a>
            </div>
        </div>

        <div class="mt-4 rounded-card bg-white p-6 ring-1 ring-brand-100">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-100 pb-4">
                <div>
                    <h1 class="text-lg font-bold text-brand-900 fa-num" dir="ltr">{{ $order->number }}</h1>
                    <p class="mt-1 text-xs text-brand-400 fa-num">{{ \App\Support\Jalali::format($order->created_at, true) }}</p>
                </div>
                <span class="rounded-full px-3 py-1.5 text-sm font-medium ring-1
                    {{ match($order->status) { 'pending' => 'bg-amber-50 text-amber-700 ring-amber-200', 'paid', 'processing' => 'bg-blue-50 text-blue-700 ring-blue-200', 'shipped' => 'bg-purple-50 text-purple-700 ring-purple-200', 'delivered' => 'bg-green-50 text-green-700 ring-green-200', 'failed' => 'bg-red-50 text-red-700 ring-red-200', 'canceled' => 'bg-gray-50 text-gray-500 ring-gray-200', default => 'bg-brand-50 text-brand-600 ring-brand-200' } }}">
                    {{ $order->statusLabel() }}
                </span>
            </div>

            {{-- Status timeline --}}
            @if ($currentIndex !== false)
                <div class="my-8 px-2">
                    <div class="relative flex items-start justify-between">
                        {{-- Background connector line (full width, grey) --}}
                        <div class="pointer-events-none absolute start-4 end-4 top-4 h-0.5 -translate-y-1/2 bg-brand-200" style="top:16px"></div>
                        {{-- Filled connector line (progress, anchored to start = right in RTL) --}}
                        @if ($currentIndex > 0)
                            <div class="pointer-events-none absolute start-4 h-0.5 bg-brand-900 transition-all duration-500"
                                 style="top:16px; width: calc({{ $currentIndex }} / {{ count($allStatuses) - 1 }} * (100% - 2rem))"></div>
                        @endif

                        @foreach ($allStatuses as $i => $status)
                            @php
                                $done    = $i < $currentIndex;
                                $current = $i === $currentIndex;
                                $future  = $i > $currentIndex;
                            @endphp
                            <div class="relative z-10 flex flex-col items-center gap-2" style="width: {{ 100 / count($allStatuses) }}%">
                                {{-- Circle --}}
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-2 transition-all duration-300
                                    {{ $done    ? 'bg-brand-900 ring-brand-900' : '' }}
                                    {{ $current ? 'bg-white ring-brand-900' : '' }}
                                    {{ $future  ? 'bg-white ring-brand-200' : '' }}">
                                    @if ($done)
                                        {{-- Checkmark --}}
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    @elseif ($current)
                                        {{-- Filled dot --}}
                                        <div class="h-3 w-3 rounded-full bg-brand-900"></div>
                                    @else
                                        {{-- Empty dot --}}
                                        <div class="h-2.5 w-2.5 rounded-full bg-brand-200"></div>
                                    @endif
                                </div>
                                {{-- Label --}}
                                <p class="text-center text-[11px] leading-tight
                                    {{ $done || $current ? 'font-semibold text-brand-800' : 'text-brand-400' }}">
                                    {{ $timelineLabels[$status] }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="divide-y divide-brand-50 py-2">
                @foreach ($order->items as $item)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="font-medium text-brand-800">{{ $item->name }}</p>
                            <p class="mt-0.5 text-xs text-brand-400">
                                @if ($item->color) {{ $item->color }} @endif
                                @if ($item->size) — {{ $item->size }} @endif
                                — {{ __('Qty:') }} <span class="fa-num">{{ $item->quantity }}</span>
                            </p>
                        </div>
                        <span class="font-semibold text-brand-900">{{ $item->formattedLineTotal() }}</span>
                    </div>
                @endforeach
            </div>

            <div class="space-y-2 border-t border-brand-100 pt-4 text-sm">
                <div class="flex justify-between"><span class="text-brand-500">{{ __('Subtotal') }}</span><span>{{ \App\Support\Money::toman($order->subtotal) }}</span></div>
                <div class="flex justify-between"><span class="text-brand-500">{{ __('Shipping') }} ({{ $order->shipping_method_name }})</span><span>{{ $order->shipping_cost_on_delivery ? __('Cash on delivery') : ($order->shipping_cost > 0 ? \App\Support\Money::toman($order->shipping_cost) : __('Free')) }}</span></div>
                @if ($order->gift_wrap)
                    <div class="flex justify-between"><span class="text-brand-500">{{ __('Gift wrapping') }}</span><span>{{ $order->gift_wrap_price > 0 ? \App\Support\Money::toman($order->gift_wrap_price) : __('Free') }}</span></div>
                @endif
                <div class="flex justify-between border-t border-brand-100 pt-2 text-base font-bold"><span>{{ __('Total') }}</span><span class="text-brand-900">{{ $order->formattedTotal() }}</span></div>
            </div>

            @if ($order->gift_wrap && filled($order->gift_message))
                <div class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
                    <p class="mb-1 text-xs font-semibold text-amber-700">{{ __('Gift message') }}</p>
                    <p class="whitespace-pre-line leading-7">{{ $order->gift_message }}</p>
                </div>
            @endif

            @if ($order->fulfillmentLocationLabel())
                <div class="mt-4 flex justify-between text-sm">
                    <span class="text-brand-500">{{ __('Fulfilled from') }}</span>
                    <span class="text-brand-800">{{ $order->fulfillmentLocationLabel() }}</span>
                </div>
            @endif

            @if ($order->shipping_address)
                <div class="mt-5 rounded-lg bg-brand-50 p-4 text-xs text-brand-600">
                    <p class="mb-1 font-semibold text-brand-800">{{ __('Delivery address') }}</p>
                    {{ $order->shipping_address['province'] ?? '' }}، {{ $order->shipping_address['city'] ?? '' }} — {{ $order->shipping_address['line'] ?? '' }}
                </div>
            @endif

            {{-- Per-order support CTA: pre-loads the bot with this order's context so
                 the admin alert is labelled by order number, not generic «contact». --}}
            @php($bot = app(\App\Services\Telegram\TelegramNotifier::class)->botUsername())
            @if ($bot)
                <a href="https://t.me/{{ $bot }}?start=order_{{ $order->number }}" target="_blank" rel="noopener"
                   class="mt-5 inline-flex items-center justify-center gap-2 rounded-full bg-[#229ED9] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#1f8fc4]">
                    <svg aria-hidden="true" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                    {{ __('Get help with this order on Telegram') }}
                </a>
            @endif
        </div>
    </div>
@endsection

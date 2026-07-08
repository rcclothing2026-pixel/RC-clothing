@extends('layouts.app')

@section('title', __('My Orders') . ' | Racket Club')

@php
$badgeColors = [
    'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
    'paid' => 'bg-blue-50 text-blue-700 ring-blue-200',
    'processing' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    'shipped' => 'bg-purple-50 text-purple-700 ring-purple-200',
    'delivered' => 'bg-green-50 text-green-700 ring-green-200',
    'failed' => 'bg-red-50 text-red-700 ring-red-200',
    'canceled' => 'bg-gray-50 text-gray-500 ring-gray-200',
];
@endphp

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="mb-8 text-2xl font-bold uppercase tracking-wide text-brand-900">{{ __('MY ORDERS') }}</h1>

        @if ($orders->isEmpty())
            <x-empty-state
                icon="box"
                title="{{ __('No orders yet') }}"
                caption="{{ __('Once you place your first order, you can follow its progress right here.') }}"
                :cta="['label' => __('Start Shopping'), 'href' => route('shop.index')]" />
        @else
            <div class="space-y-3">
                @foreach ($orders as $order)
                    <a href="{{ route('account.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-white p-5 ring-1 ring-brand-100 transition hover:ring-brand-300 hover:shadow-sm">
                        <div>
                            <p class="font-semibold text-brand-800 fa-num" dir="ltr">{{ $order->number }}</p>
                            <p class="mt-1 text-xs text-brand-400 fa-num">{{ \App\Support\Jalali::format($order->created_at) }} — {{ $order->items->count() }} {{ __('items') }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-medium ring-1 {{ $badgeColors[$order->status] ?? 'bg-brand-50 text-brand-600 ring-brand-200' }}">{{ $order->statusLabel() }}</span>
                        <span class="font-bold text-brand-900">{{ $order->formattedTotal() }}</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-6 fa-num">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection

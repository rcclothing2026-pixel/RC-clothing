@extends('layouts.app')

@section('title', __('Request a Return') . ' | Racket Club')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <a href="{{ route('account.orders.show', $order) }}" class="text-sm text-accent-600 hover:underline">&larr; {{ __('Back to order') }}</a>
        <h1 class="mt-3 text-xl font-bold uppercase tracking-wide text-brand-900">{{ __('REQUEST A RETURN — ORDER') }} <span class="fa-num" dir="ltr">{{ $order->number }}</span></h1>
        <p class="mt-1 text-sm text-brand-500">{{ __("Select the items you'd like to return and the quantity of each.") }}</p>

        <form method="POST" action="{{ route('account.orders.return.store', $order) }}" class="mt-6 space-y-4 rounded-card bg-white p-6 ring-1 ring-brand-100">
            @csrf
            <div class="divide-y divide-brand-50">
                @foreach ($order->items as $item)
                    <div class="flex items-center justify-between gap-3 py-3">
                        <div class="text-sm">
                            <span class="font-medium text-brand-800">{{ $item->name }}</span>
                            @if ($item->size)<span class="text-brand-400">({{ $item->size }}@if($item->color) / {{ $item->color }}@endif)</span>@endif
                            <div class="text-xs text-brand-400 fa-num">{{ __('Purchased:') }} {{ $item->quantity }}</div>
                        </div>
                        <input type="number" name="items[{{ $item->id }}]" min="0" max="{{ $item->quantity }}" value="0"
                               class="w-20 rounded-lg border border-brand-200 px-2 py-1.5 text-center text-sm fa-num">
                    </div>
                @endforeach
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">{{ __('Reason for return') }} *</label>
                <textarea name="reason" rows="3" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">{{ old('reason') }}</textarea>
            </div>

            <button class="rounded-lg bg-brand-900 px-6 py-2.5 text-sm font-semibold text-white">{{ __('Submit Return Request') }}</button>
        </form>
    </div>
@endsection

@props(['product' => null, 'variant' => null])
@php($__promo = \App\Services\StockKeeping\StoqsPromotions::promoText($variant ?? $product))
@if ($__promo['label'] !== '' || $__promo['note'] !== '')
    <div {{ $attributes->class(['mt-1 text-[11px] leading-5']) }}>
        @if ($__promo['label'] !== '')
            <span class="block font-medium text-accent-700">{{ $__promo['label'] }}</span>
        @endif
        @if ($__promo['note'] !== '')
            <span class="block text-brand-500">{{ $__promo['note'] }}</span>
        @endif
    </div>
@endif

{{--
    Corner tags from Campaigns::tagsFor(): the live campaign tag (بلک فرایدی, or the quieter
    «حساب در پله‌ها» for products the tiers count but do not discount) first, then «اوتلت».
    Stacked at the image's top-end corner (RTL: top-left), clear of the top-start discount /
    sold-out badge and the bottom-start «ست با سبد شما» hint. `inline` renders the bare stack
    for a parent that already positions it. Click-through, so it never blocks the product link.
--}}
@props(['product', 'inline' => false])
@php($tags = \App\Support\Campaigns::tagsFor($product))
@if ($tags)
    @php($tone = [
        'bf' => 'bg-accent-600 text-white',
        'quiet' => 'bg-white text-brand-900 ring-1 ring-brand-200',
        'outlet' => 'bg-forest text-white',
    ])
    <div class="pointer-events-none z-[2] flex flex-col gap-1 {{ $inline ? 'items-start' : 'absolute end-2 items-end '.(isset($product->badgeList()['te']) ? 'top-9' : 'top-2') }}" style="max-width: calc(100% - 1rem)">
        @foreach ($tags as $t)
            <span class="rounded-sm px-1.5 py-0.5 text-[10px] font-semibold leading-snug shadow-sm sm:text-[11px] {{ $tone[$t['tone']] ?? $tone['bf'] }}">{{ $t['text'] }}</span>
        @endforeach
    </div>
@endif

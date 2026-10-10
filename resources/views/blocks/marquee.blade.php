@php
    $items = $data['items'] ?? [];
    if (! is_array($items) || ! $items) {
        $items = ['Complimentary delivery on orders over 1,000,000 Toman', '7-day returns', 'Secure checkout', 'Support every day'];
    }
    $bg = ($data['style'] ?? 'dark') === 'red' ? 'bg-accent-600 text-white' : 'bg-brand-900 text-brand-100';
@endphp
<section class="my-12 overflow-hidden py-4 {{ $bg }}">
    <div class="flex w-max gap-12 whitespace-nowrap animate-marquee">
        @for ($i = 0; $i < 2; $i++)
            @foreach ($items as $item)
                <span class="text-sm">{{ $item }}</span>
            @endforeach
        @endfor
    </div>
</section>

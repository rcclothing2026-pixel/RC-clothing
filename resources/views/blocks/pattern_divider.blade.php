@php
    $h = ($data['height'] ?? 'sm') === 'md' ? 'h-28' : 'h-16';
    [$bg, $color] = match ($data['style'] ?? 'light') {
        'red' => ['bg-accent-600', '#ffffff'],
        'dark' => ['bg-brand-900', '#ffffff'],
        default => ['bg-brand-50', '#18234f'],
    };
@endphp
<section class="my-8 overflow-hidden {{ $bg }}">
    <x-brand-pattern class="{{ $h }} w-full" :color="$color" opacity="0.12" />
</section>

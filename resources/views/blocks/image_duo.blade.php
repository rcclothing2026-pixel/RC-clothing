@php
    $a = ($data['image_a'] ?? null) ?: '/placeholder?w=800&h=900&seed=duoa';
    $b = ($data['image_b'] ?? null) ?: '/placeholder?w=800&h=900&seed=duob';
    $stackMobile = ($data['stack_mobile'] ?? 'stack') === 'side';
    $aspectClass = match ($data['aspect'] ?? 'portrait') {
        'tall'   => 'aspect-[2/3]',
        'square' => 'aspect-square',
        'wide'   => 'aspect-[4/3]',
        default  => 'aspect-[4/5]',
    };
    $containerClass = match ($data['container'] ?? 'default') {
        'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-7xl',
    };
    $gapClass = match ($data['gap'] ?? 'normal') { 'tight' => 'gap-2', 'wide' => 'gap-8', default => 'gap-4' };
    $padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-4', 'lg' => 'py-16 sm:py-24', default => 'py-8 sm:py-12' };
    $gridClass = $stackMobile ? 'grid-cols-2' : 'grid-cols-1 sm:grid-cols-2';
@endphp
<section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
    <div class="grid {{ $gapClass }} {{ $gridClass }} sm:grid-cols-2">
        @foreach ([[$a, $data['link_a'] ?? null], [$b, $data['link_b'] ?? null]] as [$img, $link])
            <{{ $link ? 'a' : 'div' }} @if($link) href="{{ $link }}" @endif class="reveal block overflow-hidden rounded-card ring-1 ring-brand-100">
                <img src="{{ $img }}" alt="" class="{{ $aspectClass }} w-full object-cover transition duration-500 hover:scale-105">
            </{{ $link ? 'a' : 'div' }}>
        @endforeach
    </div>
</section>

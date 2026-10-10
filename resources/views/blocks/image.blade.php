@php
    $h = match ($data['height'] ?? 'md') { 'sm' => 'h-48 sm:h-64', 'lg' => 'h-80 sm:h-[32rem]', default => 'h-64 sm:h-96' };
    $img = ($data['image'] ?? null) ?: '/placeholder?w=1600&h=600&seed=banner';
    $link = $data['link'] ?? null;
@endphp
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <{{ $link ? 'a' : 'div' }} @if($link) href="{{ $link }}" @endif class="reveal block overflow-hidden rounded-card ring-1 ring-brand-100">
        <img src="{{ $img }}" alt="{{ $data['caption'] ?? '' }}" class="{{ $h }} w-full object-cover">
    </{{ $link ? 'a' : 'div' }}>
    @if (!empty($data['caption']))
        <p class="mt-3 text-center text-sm text-brand-500">{{ $data['caption'] }}</p>
    @endif
</section>

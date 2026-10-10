@php
    $style = $data['style'] ?? 'light';
    $dark = in_array($style, ['grad-red-light', 'grad-dark-red', 'dark'], true);
    $bg = match ($style) {
        'grad-red-light' => 'bg-grad-red-light',
        'grad-dark-red' => 'bg-grad-dark-red',
        'dark' => 'bg-brand-900',
        default => 'bg-paper',
    };
    $img = ($data['image'] ?? null) ?: '/placeholder?w=900&h=1100&seed=hero&label='.urlencode('Racket Club Collection');
    $ctaLink = ($data['cta_link'] ?? null) ?: route('shop.index');
@endphp
<section class="relative overflow-hidden {{ $bg }}">
    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-4 py-20 sm:px-6 md:grid-cols-2 md:py-28">
        <div class="reveal">
            @if (!empty($data['badge']))
                <span class="inline-block rounded-full px-4 py-1.5 text-xs font-medium ring-1 {{ $dark ? 'bg-white/15 text-white ring-white/30' : 'bg-white/70 text-brand-600 ring-brand-200' }}" data-edit="badge">{{ $data['badge'] }}</span>
            @endif
            <h1 class="mt-6 font-display text-4xl font-bold uppercase leading-tight sm:text-5xl md:text-6xl {{ $dark ? 'text-white' : 'text-brand-900' }}">
                <span data-edit="title">{{ ($data['title'] ?? null) ?: 'Style for your leisure moments' }}</span>
                @if (!empty($data['accent']))<br><span class="{{ $dark ? 'text-accent-400' : 'text-accent-600' }}" data-edit="accent">{{ $data['accent'] }}</span>@endif
            </h1>
            @if (!empty($data['subtitle']))
                <p class="mt-6 max-w-md text-base leading-8 {{ $dark ? 'text-white/85' : 'text-brand-600' }}" data-edit="subtitle">{{ $data['subtitle'] }}</p>
            @endif
            @if (!empty($data['cta_text']))
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ $ctaLink }}" class="rounded-full px-7 py-3 text-sm font-semibold transition {{ $dark ? 'bg-white text-brand-900 hover:bg-brand-100' : 'bg-brand-900 text-white hover:bg-brand-800' }}">{{ $data['cta_text'] }}</a>
                </div>
            @endif
        </div>
        <div class="reveal relative" style="transition-delay:120ms">
            <div class="aspect-[4/5] overflow-hidden rounded-[2rem] bg-brand-100 shadow-xl ring-1 ring-white/50">
                <img src="{{ $img }}" alt="{{ $data['title'] ?? 'Racket Club Collection' }}" class="h-full w-full object-cover">
            </div>
        </div>
    </div>
</section>

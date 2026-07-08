@php
    $style = $data['style'] ?? 'grad-red-light';
    $dark = in_array($style, ['grad-dark-red', 'grad-dark-light', 'dark'], true);
    $bg = match ($style) {
        'grad-red-light' => 'bg-grad-red-light',
        'grad-dark-red' => 'bg-grad-dark-red',
        'grad-dark-light' => 'bg-grad-dark-light',
        'dark' => 'bg-brand-900',
        default => 'bg-brand-50',
    };
    $containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-7xl' };
    $padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-6', 'lg' => 'py-16 sm:py-24', default => 'py-10' };
@endphp
<section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
    <div class="relative overflow-hidden rounded-card {{ $bg }} px-6 py-12 text-center sm:px-12">
        <x-brand-pattern class="absolute inset-0 h-full w-full" :color="$dark ? '#ffffff' : '#282828'" opacity="0.05" />
        <div class="relative">
            <h2 class="text-2xl font-bold sm:text-3xl {{ $dark ? 'text-white' : 'text-brand-900' }}">{{ ($data['heading'] ?? null) ?: 'پیشنهاد ویژه' }}</h2>
            @if (!empty($data['text']))
                <p class="mx-auto mt-3 max-w-xl text-sm leading-7 {{ $dark ? 'text-white/85' : 'text-brand-600' }}">{{ $data['text'] }}</p>
            @endif
            @if (!empty($data['cta_text']))
                <a href="{{ ($data['cta_link'] ?? null) ?: route('shop.index') }}"
                   class="mt-6 inline-block rounded-full px-7 py-3 text-sm font-semibold transition {{ $dark ? 'bg-white text-brand-900 hover:bg-brand-100' : 'bg-brand-900 text-white hover:bg-brand-800' }}">{{ $data['cta_text'] }}</a>
            @endif
        </div>
    </div>
</section>

@php
    $align = ($data['align'] ?? 'right') === 'center' ? 'text-center mx-auto' : 'text-right';
    $widthClass = match ($data['width'] ?? 'narrow') {
        'default' => 'max-w-7xl',
        'wide'    => 'max-w-screen-2xl',
        'full'    => 'max-w-none',
        default   => 'max-w-3xl',
    };
    $padClass = match ($data['padding'] ?? 'md') {
        'none' => 'py-0',
        'sm'   => 'py-6',
        'lg'   => 'py-20 sm:py-28',
        default => 'py-12',
    };
@endphp
<section class="mx-auto {{ $widthClass }} {{ $padClass }} px-4 sm:px-6">
    <div class="reveal {{ $align }}">
        @if (!empty($data['heading']))
            <h2 class="mb-4 text-2xl font-bold text-brand-900" data-edit="heading">{{ $data['heading'] }}</h2>
        @endif
        @if (!empty($data['body']))
            <div class="prose-fa text-base leading-8 text-brand-700" data-edit="body" data-edit-html>{!! $data['body'] !!}</div>
        @endif
    </div>
</section>

@php
    $slides = $data['slides'] ?? [];
    if (! is_array($slides)) {
        $slides = [];
    }
    $slides = array_values(array_filter($slides, fn ($s) => is_array($s) && (! empty($s['title']) || ! empty($s['image']))));
    $interval = max(2, (int) ($data['interval'] ?? 5)) * 1000;
    $blob = [
        'purple' => ['#7c3aed', '#6366f1'],
        'amber' => ['#f59e0b', '#f97316'],
        'teal' => ['#2dd4bf', '#10b981'],
        'red' => ['#cc3333', '#e36a6a'],
        'dark' => ['#282828', '#524d4f'],
    ];
@endphp
@if ($slides)
    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <div class="motion-banner relative min-h-[380px] overflow-hidden rounded-[2rem] bg-brand-50 ring-1 ring-brand-100 sm:min-h-[480px]" data-interval="{{ $interval }}">
            @foreach ($slides as $k => $slide)
                @php([$c1, $c2] = $blob[$slide['color'] ?? 'red'] ?? $blob['red'])
                @php($img = ($slide['image'] ?? null) ?: '/placeholder?w=800&h=900&seed=mb'.$k)
                <div data-slide="{{ $k }}" class="absolute inset-0 transition-opacity duration-700 ease-out {{ $k === 0 ? 'opacity-100' : 'opacity-0' }}" @if($k) style="pointer-events:none" @endif>
                    <div class="grid h-full items-center gap-4 p-6 sm:p-12 md:grid-cols-2">
                        <div class="relative z-10 order-2 text-center md:order-1 md:text-left">
                            @if (!empty($slide['title']))
                                <h2 class="text-2xl font-bold leading-tight text-brand-900 sm:text-4xl md:text-5xl">{{ $slide['title'] }}</h2>
                            @endif
                            @if (!empty($slide['subtitle']))
                                <p class="mx-auto mt-4 max-w-sm text-sm leading-7 text-brand-600 md:mx-0">{{ $slide['subtitle'] }}</p>
                            @endif
                            @if (!empty($slide['cta_text']))
                                <a href="{{ ($slide['cta_link'] ?? null) ?: route('shop.index') }}" class="mt-6 inline-block rounded-full bg-brand-900 px-7 py-3 text-sm font-semibold text-white transition hover:bg-brand-800">{{ $slide['cta_text'] }}</a>
                            @endif
                        </div>
                        <div class="relative order-1 flex items-center justify-center md:order-2">
                            <div class="absolute h-48 w-48 animate-float-slow rounded-[46%_54%_60%_40%/50%_46%_54%_50%] blur-md sm:h-64 sm:w-64 md:h-72 md:w-72"
                                 style="background-image:linear-gradient(135deg,{{ $c1 }},{{ $c2 }})"></div>
                            <img src="{{ $img }}" alt="{{ $slide['title'] ?? '' }}" loading="lazy"
                                 class="relative z-10 h-40 w-40 animate-float-slow object-contain drop-shadow-2xl sm:h-64 sm:w-64 md:h-72 md:w-72" style="animation-delay:-1.5s">
                        </div>
                    </div>
                </div>
            @endforeach

            @if (count($slides) > 1)
                <div class="absolute inset-x-0 bottom-5 z-20 flex justify-center gap-2">
                    @foreach ($slides as $k => $slide)
                        <button type="button" data-dot="{{ $k }}" aria-label="Slide {{ $k + 1 }}"
                                class="h-2.5 w-2.5 rounded-full transition {{ $k === 0 ? 'bg-brand-900' : 'bg-brand-300' }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif

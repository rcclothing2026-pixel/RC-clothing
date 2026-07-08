@php
    $items = $data['items'] ?? [];
    if (! is_array($items) || ! $items) {
        $items = ['ارسال سریع :: بسته‌بندی و ارسال در کوتاه‌ترین زمان به سراسر کشور', 'پرداخت امن :: پرداخت آنلاین مطمئن از طریق درگاه معتبر', 'ضمانت کیفیت :: تضمین کیفیت پارچه و دوخت با امکان بازگشت کالا'];
    }
    $containerClass = match ($data['container'] ?? 'default') { 'wide' => 'max-w-screen-2xl', 'full' => 'max-w-none', default => 'max-w-7xl' };
    $padClass = match ($data['padding'] ?? 'md') { 'none' => 'py-0', 'sm' => 'py-8', 'lg' => 'py-24 sm:py-32', default => 'py-20 sm:py-24' };
@endphp
<section class="mx-auto {{ $containerClass }} {{ $padClass }} px-4 sm:px-6">
    @if (!empty($data['heading']))
        <h2 class="reveal mb-8 text-2xl font-bold text-brand-900">{{ $data['heading'] }}</h2>
    @endif
    <div class="grid gap-6 sm:grid-cols-3">
        @foreach ($items as $line)
            @php([$t, $d] = array_pad(array_map('trim', explode('::', $line, 2)), 2, ''))
            <div class="reveal flex flex-col items-center justify-center rounded-card bg-white p-6 text-center ring-1 ring-brand-100">
                <div class="mb-3 grid h-11 w-11 place-items-center rounded-xl bg-accent-600/10 text-accent-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m5 13 4 4L19 7"/></svg>
                </div>
                <h3 class="text-base font-semibold text-brand-900">{{ $t }}</h3>
                @if ($d !== '')<p class="mt-2 text-sm leading-7 text-brand-600">{{ $d }}</p>@endif
            </div>
        @endforeach
    </div>
</section>

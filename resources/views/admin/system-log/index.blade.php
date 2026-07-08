@extends('admin.layout')
@section('title', 'گزارش خطاها')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold text-brand-900">گزارش خطاها</h1>
        <p class="mt-1 text-xs text-brand-500 fa-num">
            @if ($exists)
                {{ number_format($size / 1024, 1) }} کیلوبایت
                @if ($truncated) — جدیدترین بخش نمایش داده شده @endif
            @else
                فایل گزارش وجود ندارد (هنوز خطایی ثبت نشده)
            @endif
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.system-log.index') }}" class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-800 transition hover:bg-brand-200">تازه‌سازی</a>
        @if ($exists)
            <a href="{{ route('admin.system-log.download') }}" class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-800 transition hover:bg-brand-200">دانلود کامل</a>
            <form method="POST" action="{{ route('admin.system-log.clear') }}" onsubmit="return confirm('کل گزارش خطاها پاک شود؟');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg bg-red-50 px-4 py-2 text-sm font-medium text-red-700 ring-1 ring-red-200 transition hover:bg-red-100">پاک کردن</button>
            </form>
        @endif
    </div>
</div>

@if (empty($entries))
    <div class="rounded-2xl border border-dashed border-brand-200 bg-white p-10 text-center text-sm text-brand-500">
        هیچ رکوردی برای نمایش نیست. 🎉
    </div>
@else
    <div class="space-y-3" dir="ltr">
        @foreach ($entries as $entry)
            @php($tone = $entry['level'] === 'error' ? 'border-red-200 bg-red-50' : ($entry['level'] === 'warning' ? 'border-amber-200 bg-amber-50' : 'border-brand-200 bg-white'))
            <details class="overflow-hidden rounded-xl border {{ $tone }}">
                <summary class="cursor-pointer select-none px-4 py-2.5 text-xs font-mono text-brand-800 hover:bg-black/5">
                    {{ \Illuminate\Support\Str::limit(strtok($entry['text'], "\n"), 200) }}
                </summary>
                <pre class="max-h-96 overflow-auto border-t border-black/5 bg-white/60 px-4 py-3 text-[11px] leading-relaxed text-brand-800 whitespace-pre-wrap break-words">{{ $entry['text'] }}</pre>
            </details>
        @endforeach
    </div>
    <p class="mt-4 text-center text-xs text-brand-400">۲۰۰ رکورد اخیر — برای سابقه کامل «دانلود کامل» را بزنید.</p>
@endif
@endsection

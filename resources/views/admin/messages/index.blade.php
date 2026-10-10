@extends('admin.layout')

@section('title', 'پیام‌ها')

@section('content')
    <h1 class="mb-6 text-xl font-bold text-brand-900">پیام‌های تماس</h1>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{{ session('error') }}</div>
    @endif

    <div class="space-y-3">
        @forelse ($threads as $t)
            @php($head = $t['head'])
            @php($isTelegram = $head->source === \App\Models\ContactMessage::SOURCE_TELEGRAM)
            <a href="{{ route('admin.messages.show', $head) }}" class="block rounded-card bg-white p-5 ring-1 ring-brand-100 transition hover:ring-brand-300">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-brand-800">{{ $head->senderLabel() }}</span>
                            @if ($isTelegram)
                                <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700 ring-1 ring-sky-200">📩 تلگرام</span>
                            @else
                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-bold text-brand-600 ring-1 ring-brand-200">📝 فرم</span>
                            @endif
                            @if ($t['unread'] > 0)
                                <span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white fa-num">{{ \App\Support\Money::toPersianDigits((string) $t['unread']) }}</span>
                            @endif
                        </div>
                        @if ($head->phone || $head->email)
                            <div class="mt-1 text-xs text-brand-400" dir="ltr">{{ $head->phone }}@if ($head->phone && $head->email) · @endif{{ $head->email }}</div>
                        @endif
                        <p class="mt-2 line-clamp-2 text-sm text-brand-600">{{ $head->message }}</p>
                    </div>
                    <div class="shrink-0 text-left">
                        <div class="text-xs text-brand-300">{{ $head->created_at->diffForHumans() }}</div>
                        <div class="mt-1 text-[10px] text-brand-400 fa-num">{{ \App\Support\Money::toPersianDigits((string) $t['count']) }} پیام</div>
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-card bg-white p-8 text-center text-brand-400 ring-1 ring-brand-100">پیامی وجود ندارد.</div>
        @endforelse
    </div>
@endsection

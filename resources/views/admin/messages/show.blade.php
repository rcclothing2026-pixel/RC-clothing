@extends('admin.layout')

@section('title', 'مکالمه — ' . $message->senderLabel())

@section('content')
    @php
        $head = $thread->first() ?? $message;
        $isTelegram = $head->source === \App\Models\ContactMessage::SOURCE_TELEGRAM;
        // Normalize Iranian mobile for wa.me — same logic as the inbox row chips.
        $waPhone = null;
        if ($head->phone) {
            $d = preg_replace('/\D+/', '', (string) $head->phone);
            if (str_starts_with($d, '0098')) { $d = '98'.substr($d, 4); }
            elseif (str_starts_with($d, '09') && strlen($d) === 11) { $d = '98'.substr($d, 1); }
            if (str_starts_with($d, '98') && strlen($d) === 12) { $waPhone = $d; }
        }
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">{{ $head->senderLabel() }}</h1>
        <a href="{{ route('admin.messages.index') }}" class="text-sm text-brand-500 hover:text-brand-700">← بازگشت به فهرست</a>
    </div>

    {{-- Contact strip: copy of the action chips from the inbox row, for fast reach-out. --}}
    <div class="mb-5 flex flex-wrap items-center gap-2 text-xs">
        @if ($isTelegram)
            <span class="rounded-full bg-sky-50 px-2.5 py-1 font-medium text-sky-700 ring-1 ring-sky-200">📩 تلگرام · چت <span class="fa-num" dir="ltr">{{ $head->telegram_chat_id }}</span></span>
        @else
            <span class="rounded-full bg-brand-50 px-2.5 py-1 font-medium text-brand-600 ring-1 ring-brand-200">📝 فرم تماس</span>
        @endif
        @if ($head->phone)
            <a href="tel:{{ $head->phone }}" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-100" dir="ltr">📞 {{ $head->phone }}</a>
            @if ($waPhone)
                <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2.5 py-1 font-medium text-green-700 ring-1 ring-green-200 hover:bg-green-100">💬 واتساپ</a>
            @endif
        @endif
        @if ($head->email)
            <a href="mailto:{{ $head->email }}?subject={{ urlencode('پاسخ به پیام شما — چیاکو') }}" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-100" dir="ltr">✉️ {{ $head->email }}</a>
        @endif
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 ring-1 ring-green-200">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{{ session('error') }}</div>
    @endif

    {{-- Thread — newest at the bottom, chat-style. --}}
    <div class="space-y-3 rounded-card bg-brand-50/30 p-4 ring-1 ring-brand-100">
        @foreach ($thread as $m)
            @php($isOut = $m->direction === \App\Models\ContactMessage::DIR_OUT)
            <div class="flex {{ $isOut ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-[80%] rounded-2xl px-4 py-3 text-sm ring-1
                            {{ $isOut ? 'bg-brand-900 text-white ring-brand-900' : 'bg-white text-brand-800 ring-brand-100' }}">
                    <p class="whitespace-pre-line leading-7">{{ $m->message }}</p>
                    <p class="mt-1 text-[10px] {{ $isOut ? 'text-white/60' : 'text-brand-400' }}">
                        {{ $isOut ? 'پاسخ پشتیبانی' : 'مشتری' }} · {{ $m->created_at->diffForHumans() }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Reply form — only meaningful when we have a chat_id to forward into. --}}
    @if ($head->telegram_chat_id)
        <form method="POST" action="{{ route('admin.messages.reply', $head) }}" class="mt-5">
            @csrf
            <label class="mb-1 block text-sm font-medium text-brand-700">پاسخ شما (در تلگرام مشتری ارسال می‌شود)</label>
            <textarea name="reply" rows="4" required maxlength="4000" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm" placeholder="پاسخ خود را اینجا بنویسید..."></textarea>
            @error('reply')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            <button class="mt-3 rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-800">ارسال پاسخ</button>
        </form>
    @else
        <p class="mt-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700 ring-1 ring-amber-200">
            این مکالمه از فرم تماس بوده — پاسخ تلگرامی ممکن نیست. از تلفن، واتساپ یا ایمیل بالا استفاده کنید.
        </p>
    @endif

    <form action="{{ route('admin.messages.destroy', $head) }}" method="POST" class="mt-8" onsubmit="return confirm('این مکالمه حذف شود؟ (همهٔ پیام‌های مرتبط هم حذف می‌شوند)')">
        @csrf @method('DELETE')
        <button class="text-xs text-red-400 hover:text-red-600">حذف مکالمه</button>
    </form>
@endsection

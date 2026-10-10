@extends('admin.layout')

@section('title', 'مشترکین خبرنامه')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">مشترکین خبرنامه</h1>
        <div class="flex items-center gap-3 text-sm">
            <span class="text-brand-500">{{ \App\Support\Money::toPersianDigits((string) $count) }} مشترک</span>
            <a href="{{ route('admin.subscribers.export') }}" class="rounded-lg bg-brand-100 px-3 py-1.5 text-sm font-medium text-brand-700 hover:bg-brand-200">خروجی CSV</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div>
            <form method="GET" class="mb-4">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="جستجوی ایمیل یا نام..." class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </form>

            <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-brand-100 text-right text-xs font-bold text-brand-500">
                            <th class="px-4 py-3">ایمیل</th>
                            <th class="px-4 py-3">نام</th>
                            <th class="px-4 py-3">تاریخ عضویت</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscribers as $sub)
                            <tr class="border-b border-brand-50">
                                <td class="px-4 py-3 font-medium text-brand-800" dir="ltr">{{ $sub->email }}</td>
                                <td class="px-4 py-3 text-brand-600">{{ $sub->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-brand-500 fa-num">{{ \App\Support\Jalali::format($sub->subscribed_at) }}</td>
                                <td class="px-4 py-3">
                                    <form action="{{ route('admin.subscribers.destroy', $sub) }}" method="POST" onsubmit="return confirm('حذف شود؟')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-500 hover:underline">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-brand-400">مشترکی یافت نشد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4"> {{ $subscribers->links() }} </div>
        </div>

        <div>
            <form method="POST" action="{{ route('admin.subscribers.broadcast') }}" class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                @csrf
                <h2 class="mb-4 text-sm font-bold text-brand-900">ارسال خبرنامه</h2>
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-600">عنوان</label>
                        <input type="text" name="subject" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-600">متن (HTML مجاز)</label>
                        <textarea name="body" rows="6" required class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm font-mono"></textarea>
                    </div>
                    <button class="w-full rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">ارسال برای همه مشترکین</button>
                </div>
            </form>
        </div>
    </div>
@endsection

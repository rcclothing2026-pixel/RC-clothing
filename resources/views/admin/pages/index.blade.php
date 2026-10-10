@extends('admin.layout')

@section('title', 'صفحه‌ساز')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">صفحه‌ساز</h1>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.pages.resetHome') }}"
                  onsubmit="return confirm('صفحه خانه به چیدمان ادیتوریال پیش‌فرض بازگردانده می‌شود. این کار تغییرات فعلی شما را پاک می‌کند. ادامه می‌دهید؟')">
                @csrf
                <button type="submit"
                        class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-200" title="چیدمان جدید (هیرو ادیتوریال + کاروسل + ...)">
                    ↺ بازگردانی خانه به چیدمان جدید
                </button>
            </form>
            <a href="{{ route('admin.pages.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">+ صفحه جدید</a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">عنوان</th>
                    <th class="p-3 text-right font-medium">آدرس</th>
                    <th class="p-3 text-right font-medium">بلاک‌ها</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($pages as $page)
                    <tr>
                        <td class="p-3 font-medium text-brand-800">
                            {{ $page->title }}
                            @if ($page->is_home)<span class="ms-1 rounded-full bg-accent-600/10 px-2 py-0.5 text-xs text-accent-600">صفحه اصلی</span>@endif
                        </td>
                        <td class="p-3 text-xs text-brand-400 fa-num" dir="ltr">{{ $page->is_home ? '/' : '/page/'.$page->slug }}</td>
                        <td class="p-3 text-brand-600 fa-num">{{ \App\Support\Money::toPersianDigits((string) count($page->blocks ?? [])) }}</td>
                        <td class="p-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $page->is_published ? 'bg-green-50 text-green-700' : 'bg-brand-100 text-brand-500' }}">{{ $page->is_published ? 'منتشر شده' : 'پیش‌نویس' }}</span>
                        </td>
                        <td class="p-3 text-left">
                            <a href="{{ $page->is_home ? url('/') : url('/page/'.$page->slug) }}" target="_blank" class="ms-2 text-brand-400 hover:text-brand-700">نمایش</a>
                            <a href="{{ route('admin.pages.edit', $page) }}" class="text-accent-600 hover:underline">ویرایش</a>
                            @unless ($page->is_home)
                                <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" class="ms-2 inline" onsubmit="return confirm('صفحه «{{ $page->title }}» حذف شود؟ این کار قابل بازگشت نیست.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:underline">حذف</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-brand-400">هنوز صفحه‌ای ساخته نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

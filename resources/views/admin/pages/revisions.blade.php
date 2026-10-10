@extends('admin.layout')

@section('title', 'تاریخچهٔ تغییرات — '.$page->title)

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-brand-900">تاریخچهٔ تغییرات</h1>
            <p class="mt-1 text-sm text-brand-500">«{{ $page->title }}» — ۲۰ نسخهٔ آخر. هر بار صفحه را ذخیره کنید یک نسخهٔ پشتیبان از حالت قبلی اینجا ثبت می‌شود.</p>
        </div>
        <a href="{{ route('admin.pages.edit', $page) }}" class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-700">← بازگشت به ویرایش</a>
    </div>

    @if ($revisions->isEmpty())
        <div class="rounded-card bg-white p-10 text-center ring-1 ring-brand-100">
            <p class="text-sm text-brand-400">هنوز هیچ نسخه‌ای ذخیره نشده. اولین ویرایش صفحه نسخهٔ اول را می‌سازد.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-brand-50 text-xs text-brand-500">
                    <tr>
                        <th class="p-3 text-right font-medium">تاریخ</th>
                        <th class="p-3 text-right font-medium">ویرایش‌کننده</th>
                        <th class="p-3 text-right font-medium">تعداد بلاک‌ها</th>
                        <th class="p-3 text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revisions as $rev)
                        <tr class="border-t border-brand-100">
                            <td class="p-3 fa-num text-brand-700" dir="ltr">{{ \App\Support\Jalali::format($rev->created_at, true) }}</td>
                            <td class="p-3 text-brand-700">{{ $rev->user?->name ?? '—' }}</td>
                            <td class="p-3 fa-num text-brand-500">{{ \App\Support\Money::toPersianDigits((string) count($rev->blocks ?? [])) }}</td>
                            <td class="p-3 text-end">
                                <form method="POST" action="{{ route('admin.pages.restoreRevision', [$page, $rev]) }}" class="inline"
                                      onsubmit="return confirm('این نسخه جایگزین چیدمان فعلی شود؟ نسخهٔ فعلی به‌عنوان آخرین رویداد در همین تاریخچه ذخیره می‌شود.')">
                                    @csrf
                                    <button class="rounded-md bg-brand-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-800">بازگردانی</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

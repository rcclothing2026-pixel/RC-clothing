@extends('admin.layout')

@section('title', 'الگوها (Patterns)')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">الگوهای ذخیره‌شده</h1>
        <p class="mt-1 text-sm text-brand-500">
            بلاک‌هایی که از صفحه‌ساز با دکمهٔ «💾 ذخیره به‌عنوان الگو» نگه داشته‌اید
            اینجا نمایش داده می‌شوند. در ویرایش‌گر صفحه‌ها از تری بلاک‌ها (با آیکن 💾)
            قابل کشیدن و افزودن‌اند.
        </p>
    </div>

    @if ($patterns->isEmpty())
        <div class="rounded-card bg-white p-10 text-center ring-1 ring-brand-100">
            <p class="text-sm text-brand-400">هنوز الگویی ذخیره نشده. روی هر بلاک در صفحه‌ساز، دکمهٔ «💾 الگو» را بزنید.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
            <table class="w-full min-w-[600px] text-sm">
                <thead class="bg-brand-50 text-xs text-brand-500">
                    <tr>
                        <th class="p-3 text-right font-medium">نام</th>
                        <th class="p-3 text-right font-medium">تعداد بلاک</th>
                        <th class="p-3 text-right font-medium">سازنده</th>
                        <th class="p-3 text-right font-medium">تاریخ</th>
                        <th class="p-3 text-right font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($patterns as $p)
                        <tr class="border-t border-brand-100">
                            <td class="p-3 font-semibold text-brand-800">{{ $p->icon }} {{ $p->name }}</td>
                            <td class="p-3 text-brand-500 fa-num">{{ \App\Support\Money::toPersianDigits((string) count($p->blocks ?? [])) }}</td>
                            <td class="p-3 text-brand-600">{{ $p->creator?->name ?? '—' }}</td>
                            <td class="p-3 fa-num text-brand-400" dir="ltr">{{ \App\Support\Jalali::format($p->created_at) }}</td>
                            <td class="p-3 text-end">
                                <form method="POST" action="{{ route('admin.patterns.destroy', $p) }}" onsubmit="return confirm('این الگو حذف شود؟')" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-500 hover:underline">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

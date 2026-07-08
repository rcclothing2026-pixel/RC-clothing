@extends('admin.layout')

@section('title', 'پاپ‌آپ‌ها')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">پاپ‌آپ‌ها</h1>
        <a href="{{ route('admin.popups.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">+ پاپ‌آپ جدید</a>
    </div>

    <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full min-w-[640px] text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">نام</th>
                    <th class="p-3 text-right font-medium">نمایش</th>
                    <th class="p-3 text-right font-medium">تکرار</th>
                    <th class="p-3 text-right font-medium">صفحات</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($popups as $popup)
                    <tr>
                        <td class="p-3 font-medium text-brand-800">{{ $popup->name }}</td>
                        <td class="p-3 text-brand-600">{{ \App\Models\Popup::TRIGGERS[$popup->trigger] ?? $popup->trigger }}</td>
                        <td class="p-3 text-brand-600">{{ \App\Models\Popup::FREQUENCIES[$popup->frequency] ?? $popup->frequency }}</td>
                        <td class="p-3 text-brand-600">{{ \App\Models\Popup::PAGES[$popup->pages] ?? $popup->pages }}</td>
                        <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $popup->is_active ? 'bg-green-50 text-green-700' : 'bg-brand-100 text-brand-500' }}">{{ $popup->is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                        <td class="p-3 text-left"><a href="{{ route('admin.popups.edit', $popup) }}" class="text-accent-600 hover:underline">ویرایش</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-brand-400">هنوز پاپ‌آپی ساخته نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

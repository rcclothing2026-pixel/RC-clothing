@extends('admin.layout')

@section('title', 'دسته‌بندی‌ها')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">دسته‌بندی‌ها</h1>
        <a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">+ دسته جدید</a>
    </div>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">نام</th>
                    <th class="p-3 text-right font-medium">والد</th>
                    <th class="p-3 text-right font-medium">تعداد محصول</th>
                    <th class="p-3 text-right font-medium">ترتیب</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($categories as $category)
                    <tr>
                        <td class="p-3 font-medium text-brand-800">{{ $category->name }}</td>
                        <td class="p-3 text-brand-500">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="p-3 fa-num text-brand-700">{{ \App\Support\Money::toPersianDigits((string) $category->products_count) }}</td>
                        <td class="p-3 fa-num text-brand-500">{{ \App\Support\Money::toPersianDigits((string) $category->position) }}</td>
                        <td class="p-3">
                            @if ($category->is_active)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-600">فعال</span>
                            @else
                                <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-500">غیرفعال</span>
                            @endif
                        </td>
                        <td class="p-3 text-left">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="text-accent-600 hover:underline">ویرایش</a>
                            <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="inline" onsubmit="return confirm('حذف این دسته؟')">
                                @csrf @method('DELETE')
                                <button class="ms-2 text-red-400 hover:text-red-600">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-brand-400">هنوز دسته‌ای ساخته نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

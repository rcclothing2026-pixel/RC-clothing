@extends('admin.layout')

@section('title', 'روش‌های ارسال')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">روش‌های ارسال</h1>
        <a href="{{ route('admin.shipping.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">+ روش جدید</a>
    </div>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">نام</th>
                    <th class="p-3 text-right font-medium">هزینه</th>
                    <th class="p-3 text-right font-medium">رایگان از</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($methods as $method)
                    <tr>
                        <td class="p-3 font-medium text-brand-800">{{ $method->name }}
                            @if ($method->description)<div class="text-xs text-brand-400">{{ $method->description }}</div>@endif
                        </td>
                        <td class="p-3 text-brand-700">{{ $method->formattedPrice() }}</td>
                        <td class="p-3 text-brand-500">{{ $method->free_over ? \App\Support\Money::toman($method->free_over) : '—' }}</td>
                        <td class="p-3">
                            @if ($method->is_active)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-600">فعال</span>
                            @else
                                <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-500">غیرفعال</span>
                            @endif
                        </td>
                        <td class="p-3 text-left">
                            <a href="{{ route('admin.shipping.edit', $method) }}" class="text-accent-600 hover:underline">ویرایش</a>
                            <form action="{{ route('admin.shipping.destroy', $method) }}" method="POST" class="inline" onsubmit="return confirm('حذف این روش ارسال؟')">
                                @csrf @method('DELETE')
                                <button class="ms-2 text-red-400 hover:text-red-600">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-brand-400">هنوز روش ارسالی تعریف نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

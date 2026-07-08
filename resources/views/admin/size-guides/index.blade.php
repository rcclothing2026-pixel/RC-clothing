@extends('admin.layout')

@section('title', 'راهنمای سایز')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">راهنمای سایز</h1>
        <a href="{{ route('admin.size-guides.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">+ راهنمای جدید</a>
    </div>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">نام</th>
                    <th class="p-3 text-right font-medium">محصولات</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($guides as $guide)
                    <tr class="border-t border-brand-50">
                        <td class="p-3 font-medium text-brand-800">{{ $guide->name }}</td>
                        <td class="p-3 text-brand-500 fa-num">{{ \App\Support\Money::toPersianDigits((string) $guide->products_count) }}</td>
                        <td class="p-3">
                            @if ($guide->is_active)<span class="text-green-600">فعال</span>@else<span class="text-brand-400">غیرفعال</span>@endif
                        </td>
                        <td class="p-3 text-left">
                            <a href="{{ route('admin.size-guides.edit', $guide) }}" class="text-accent-600 hover:underline">ویرایش</a>
                            <form action="{{ route('admin.size-guides.destroy', $guide) }}" method="POST" class="inline" onsubmit="return confirm('این راهنما حذف شود؟ از محصولات برداشته می‌شود.')">
                                @csrf @method('DELETE')
                                <button class="ms-3 text-red-500 hover:underline">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-8 text-center text-brand-400">هنوز راهنمای سایزی ساخته نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

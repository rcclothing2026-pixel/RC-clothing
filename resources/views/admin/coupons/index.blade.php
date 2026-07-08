@extends('admin.layout')

@section('title', 'کدهای تخفیف')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">کدهای تخفیف</h1>
        <a href="{{ route('admin.coupons.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">+ کد جدید</a>
    </div>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">کد</th>
                    <th class="p-3 text-right font-medium">نوع</th>
                    <th class="p-3 text-right font-medium">مقدار</th>
                    <th class="p-3 text-right font-medium">استفاده</th>
                    <th class="p-3 text-right font-medium">انقضا</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($coupons as $coupon)
                    <tr>
                        <td class="p-3 font-bold text-brand-800" dir="ltr">{{ $coupon->code }}</td>
                        <td class="p-3 text-brand-500">{{ $coupon->typeLabel() }}</td>
                        <td class="p-3 text-brand-700">
                            @if ($coupon->type === 'percent') {{ \App\Support\Money::toPersianDigits((string) $coupon->value) }}٪
                            @elseif ($coupon->type === 'fixed') {{ \App\Support\Money::toman($coupon->value) }}
                            @else — @endif
                        </td>
                        <td class="p-3 fa-num text-brand-500">{{ \App\Support\Money::toPersianDigits((string) $coupon->used_count) }}@if($coupon->usage_limit) / {{ \App\Support\Money::toPersianDigits((string) $coupon->usage_limit) }}@endif</td>
                        <td class="p-3 text-xs text-brand-400">{{ $coupon->expires_at ? \App\Support\Jalali::format($coupon->expires_at) : '—' }}</td>
                        <td class="p-3">
                            @if ($coupon->is_active)<span class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-600">فعال</span>
                            @else<span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs text-brand-500">غیرفعال</span>@endif
                        </td>
                        <td class="p-3 text-left">
                            <a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-accent-600 hover:underline">ویرایش</a>
                            <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST" class="inline" onsubmit="return confirm('حذف این کد؟')">
                                @csrf @method('DELETE')
                                <button class="ms-2 text-red-400 hover:text-red-600">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-brand-400">هنوز کد تخفیفی ساخته نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 fa-num">{{ $coupons->links() }}</div>
@endsection

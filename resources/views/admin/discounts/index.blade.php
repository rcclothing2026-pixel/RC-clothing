@extends('admin.layout')

@section('title', 'مدیریت تخفیف‌ها')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-xl font-bold text-brand-900">مدیریت تخفیف‌ها</h1>
    <div class="flex gap-2">
        <a href="{{ route('admin.coupons.create') }}"
           class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white">+ کد تخفیف</a>
        <a href="{{ route('admin.discount-rules.create') }}"
           class="rounded-lg bg-accent-600 px-4 py-2 text-sm font-semibold text-white">+ قانون هوشمند</a>
    </div>
</div>

{{-- Coupons --}}
<section class="mb-8">
    <h2 class="mb-3 text-base font-bold text-brand-800">کدهای تخفیف</h2>
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
    <div class="mt-2 fa-num">{{ $coupons->links() }}</div>
</section>

{{-- Discount Rules --}}
<section>
    <h2 class="mb-3 text-base font-bold text-brand-800">قوانین تخفیف هوشمند</h2>
    <div class="rounded-card bg-white ring-1 ring-brand-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-brand-500 text-xs">
                <tr>
                    <th class="text-right p-3">نام</th>
                    <th class="text-right p-3">اولویت</th>
                    <th class="text-right p-3">شرایط</th>
                    <th class="text-right p-3">عملیات</th>
                    <th class="text-right p-3">مصرف</th>
                    <th class="text-center p-3">فعال</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($rules as $rule)
                <tr class="hover:bg-brand-50 transition">
                    <td class="p-3">
                        <p class="font-medium text-brand-800">{{ $rule->name }}</p>
                        @if ($rule->description)<p class="text-xs text-brand-400 mt-0.5">{{ $rule->description }}</p>@endif
                    </td>
                    <td class="p-3 text-brand-500 fa-num">{{ $rule->priority }}</td>
                    <td class="p-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($rule->conditions as $cond)
                                <span class="inline-block rounded-full bg-brand-100 px-2 py-0.5 text-[10px] text-brand-600">
                                    {{ $rule->conditionLabel($cond['type']) }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="p-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($rule->actions as $act)
                                <span class="inline-block rounded-full bg-blue-50 px-2 py-0.5 text-[10px] text-blue-600">
                                    {{ $rule->actionLabel($act['type']) }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="p-3 text-xs text-brand-400 fa-num">
                        @if ($rule->max_uses)
                            {{ $rule->used_count }}/{{ $rule->max_uses }}
                        @else
                            {{ $rule->used_count }}
                        @endif
                    </td>
                    <td class="p-3 text-center">
                        @if ($rule->is_active)
                            <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                        @else
                            <span class="inline-block w-2 h-2 rounded-full bg-red-300"></span>
                        @endif
                    </td>
                    <td class="p-3">
                        <div class="flex gap-2">
                            <a href="{{ route('admin.discount-rules.edit', $rule) }}"
                               class="text-xs text-brand-500 hover:text-accent-600">ویرایش</a>
                            <form action="{{ route('admin.discount-rules.destroy', $rule) }}" method="POST"
                                  onsubmit="return confirm('حذف قانون «{{ $rule->name }}»؟')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-brand-400 hover:text-red-500">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="p-8 text-center text-brand-400">هیچ قانون تخفیفی تعریف نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2 fa-num">{{ $rules->links() }}</div>
</section>
@endsection

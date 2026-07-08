@extends('admin.layout')

@section('title', 'مشتریان')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-brand-900">مشتریان (CRM)</h1>
        <a href="{{ route('admin.customers.create') }}" class="rounded-lg bg-brand-900 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">+ افزودن کاربر / مدیر</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="جستجوی نام یا موبایل..." class="w-full max-w-xs rounded-lg border border-brand-200 px-3 py-2 text-sm">
        <select name="filter" class="rounded-lg border border-brand-200 px-3 py-2 text-sm">
            <option value="">همه</option>
            <option value="buyers" @selected(request('filter') === 'buyers')>فقط خریداران</option>
            <option value="admins" @selected(request('filter') === 'admins')>فقط مدیران</option>
        </select>
        <button class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-700">فیلتر</button>
    </form>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">نام</th>
                    <th class="p-3 text-right font-medium">موبایل</th>
                    <th class="p-3 text-right font-medium">سفارش‌ها</th>
                    <th class="p-3 text-right font-medium">مجموع خرید</th>
                    <th class="p-3 text-right font-medium">عضویت</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="p-3 font-medium text-brand-800">
                            {{ $customer->name ?: '—' }}
                            @if ($customer->is_admin)<span class="ms-1 rounded-full bg-accent-100 px-2 py-0.5 text-[11px] font-medium text-accent-700">مدیر</span>@endif
                        </td>
                        <td class="p-3 fa-num text-brand-600" dir="ltr">{{ $customer->phone }}</td>
                        <td class="p-3 fa-num text-brand-700">{{ \App\Support\Money::toPersianDigits((string) $customer->paid_orders_count) }}</td>
                        <td class="p-3 text-brand-700">{{ \App\Support\Money::toman((int) $customer->total_spent) }}</td>
                        <td class="p-3 text-xs text-brand-400 fa-num">{{ \App\Support\Jalali::format($customer->created_at) }}</td>
                        <td class="p-3 text-left">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="text-accent-600 hover:underline">پروفایل</a>
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="ms-2 text-brand-500 hover:underline">ویرایش</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-brand-400">مشتری‌ای یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 fa-num">{{ $customers->links() }}</div>
@endsection

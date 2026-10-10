@extends('admin.layout')

@section('title', 'پروفایل مشتری')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-brand-900">{{ $customer->name ?: 'مشتری' }}
            <span class="ms-2 text-sm font-normal text-brand-400 fa-num" dir="ltr">{{ $customer->phone }}</span>
            @if ($customer->is_admin)<span class="ms-1 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-medium text-accent-700 align-middle">مدیر</span>@endif
        </h1>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('admin.customers.edit', $customer) }}" class="rounded-lg bg-brand-100 px-3 py-1.5 font-medium text-brand-700 hover:bg-brand-200">ویرایش</a>
            <a href="{{ route('admin.customers.index') }}" class="text-brand-500 hover:underline">بازگشت</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- StoqS unified CRM --}}
        <div class="lg:col-span-1 space-y-4">
            @if ($crm && ($crm['ok'] ?? false))
                <div class="rounded-card bg-brand-900 p-5 text-white">
                    <p class="text-xs text-white/60">امتیاز باشگاه (StoqS)</p>
                    <p class="mt-1 text-3xl font-bold fa-num">{{ \App\Support\Money::toPersianDigits((string) ($crm['customer']['loyalty_points'] ?? 0)) }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-white/15 pt-4 text-sm">
                        <div><span class="block text-white/60 text-xs">خرید کل</span><span class="fa-num">{{ \App\Support\Money::toPersianDigits((string) ($crm['lifetime']['orders'] ?? 0)) }}</span></div>
                        <div><span class="block text-white/60 text-xs">مجموع</span>{{ \App\Support\Money::toman((int) ($crm['lifetime']['total_spent'] ?? 0)) }}</div>
                    </div>
                </div>
                @if (!empty($crm['recent_purchases']))
                    <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
                        <p class="mb-2 text-sm font-bold text-brand-800">خریدهای StoqS (فروشگاه و سایت)</p>
                        <ul class="space-y-1.5 text-sm">
                            @foreach ($crm['recent_purchases'] as $p)
                                <li class="flex justify-between text-brand-600">
                                    <span>{{ $p['product'] }} @if(!empty($p['size']))<span class="text-brand-400">({{ $p['size'] }})</span>@endif × {{ \App\Support\Money::toPersianDigits((string) $p['quantity']) }}</span>
                                    <span class="text-xs text-brand-400 fa-num" dir="ltr">{{ $p['date'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @else
                <div class="rounded-card bg-white p-5 text-sm text-brand-400 ring-1 ring-brand-100">
                    @if (config('stockkeeping.enabled')) این مشتری هنوز در CRM استوکس ثبت نشده است. @else اتصال StoqS غیرفعال است. @endif
                </div>
            @endif

            @if ($customer->email)
                <div class="rounded-card bg-white p-5 text-sm ring-1 ring-brand-100">
                    <span class="text-xs text-brand-400">ایمیل</span><div dir="ltr">{{ $customer->email }}</div>
                </div>
            @endif
        </div>

        {{-- Website orders --}}
        <div class="lg:col-span-2 rounded-card bg-white p-5 ring-1 ring-brand-100">
            <h2 class="mb-3 text-sm font-bold text-brand-800">سفارش‌های سایت</h2>
            <table class="w-full text-sm">
                <thead class="text-xs text-brand-400">
                    <tr><th class="py-2 text-right">شماره</th><th class="py-2 text-right">مبلغ</th><th class="py-2 text-right">وضعیت</th><th class="py-2 text-right">تاریخ</th></tr>
                </thead>
                <tbody class="divide-y divide-brand-50">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="py-2 fa-num" dir="ltr"><a href="{{ route('admin.orders.show', $order) }}" class="text-accent-600 hover:underline">{{ $order->number }}</a></td>
                            <td class="py-2 text-brand-700">{{ $order->formattedTotal() }}</td>
                            <td class="py-2"><span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-600">{{ $order->statusLabel() }}</span></td>
                            <td class="py-2 text-xs text-brand-400 fa-num">{{ \App\Support\Jalali::format($order->created_at) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-brand-400">بدون سفارش.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

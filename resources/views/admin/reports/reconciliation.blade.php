@extends('admin.layout')

@section('title', 'تسویه و مغایرت‌گیری')

@section('content')
    <h1 class="mb-6 text-xl font-bold text-brand-900">تسویه و مغایرت‌گیری درآمد</h1>

    {{-- Summary cards --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">درآمد ناخالص</p>
            <p class="mt-1 font-bold text-brand-900">{{ \App\Support\Money::toman($summary['gross']) }}</p>
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">بازپرداخت‌شده</p>
            <p class="mt-1 font-bold text-red-600">{{ \App\Support\Money::toman($summary['refunded']) }}</p>
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">خالص</p>
            <p class="mt-1 font-bold text-brand-900">{{ \App\Support\Money::toman($summary['net']) }}</p>
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">تسویه‌شده</p>
            <p class="mt-1 font-bold text-green-600">{{ \App\Support\Money::toman($summary['settled']) }}</p>
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">تسویه‌نشده</p>
            <p class="mt-1 font-bold text-amber-600">{{ \App\Support\Money::toman($summary['unsettled']) }}</p>
        </div>
        <div class="rounded-card p-4 ring-1 {{ $summary['notReported'] > 0 ? 'bg-red-50 ring-red-200' : 'bg-white ring-brand-100' }}">
            <p class="text-xs text-brand-400">گزارش‌نشده به StoqS</p>
            <p class="mt-1 font-bold fa-num {{ $summary['notReported'] > 0 ? 'text-red-600' : 'text-brand-900' }}">{{ \App\Support\Money::toPersianDigits((string) $summary['notReported']) }} سفارش</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <div>
            <label class="mb-1 block text-xs text-brand-400">از تاریخ</label>
            <input name="from" data-jdp dir="ltr" autocomplete="off" value="{{ $filters['from'] }}" placeholder="۱۴۰۴/۰۱/۰۱" class="w-36 rounded-lg border border-brand-200 px-3 py-2 text-center text-sm fa-num">
        </div>
        <div>
            <label class="mb-1 block text-xs text-brand-400">تا تاریخ</label>
            <input name="to" data-jdp dir="ltr" autocomplete="off" value="{{ $filters['to'] }}" placeholder="۱۴۰۴/۱۲/۲۹" class="w-36 rounded-lg border border-brand-200 px-3 py-2 text-center text-sm fa-num">
        </div>
        <select name="gateway" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
            <option value="">همه درگاه‌ها</option>
            @foreach ($gateways as $key => $label)
                <option value="{{ $key }}" @selected($filters['gateway'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="settlement" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
            <option value="">تسویه: همه</option>
            <option value="settled" @selected($filters['settlement'] === 'settled')>تسویه‌شده</option>
            <option value="unsettled" @selected($filters['settlement'] === 'unsettled')>تسویه‌نشده</option>
        </select>
        <button class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-700">فیلتر</button>
    </form>

    {{-- Per-gateway breakdown --}}
    @if ($byGateway->isNotEmpty())
        <div class="mb-6 overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
            <table class="w-full text-sm">
                <thead class="bg-brand-50 text-xs text-brand-500">
                    <tr>
                        <th class="p-3 text-right font-medium">درگاه</th>
                        <th class="p-3 text-right font-medium">تعداد</th>
                        <th class="p-3 text-right font-medium">درآمد</th>
                        <th class="p-3 text-right font-medium">تسویه‌شده</th>
                        <th class="p-3 text-right font-medium">باقی‌مانده</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-50">
                    @foreach ($byGateway as $key => $row)
                        <tr>
                            <td class="p-3 font-medium text-brand-800">{{ $gateways[$key] ?? $key }}</td>
                            <td class="p-3 text-brand-600 fa-num">{{ \App\Support\Money::toPersianDigits((string) $row['count']) }}</td>
                            <td class="p-3 text-brand-700">{{ \App\Support\Money::toman($row['gross']) }}</td>
                            <td class="p-3 text-green-600">{{ \App\Support\Money::toman($row['settled']) }}</td>
                            <td class="p-3 text-amber-600">{{ \App\Support\Money::toman($row['gross'] - $row['settled']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Settle all in current filter --}}
    @if ($summary['unsettled'] > 0)
        <form method="POST" action="{{ route('admin.reports.reconciliation.settle-all') }}" class="mb-3"
              onsubmit="return confirm('همهٔ پرداخت‌های تسویه‌نشدهٔ این فیلتر، تسویه‌شده ثبت شوند؟')">
            @csrf
            <input type="hidden" name="from" value="{{ $filters['from'] }}">
            <input type="hidden" name="to" value="{{ $filters['to'] }}">
            <input type="hidden" name="gateway" value="{{ $filters['gateway'] }}">
            <button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">✓ تسویهٔ همهٔ موارد فیلترشده</button>
        </form>
    @endif

    {{-- Detail table --}}
    <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">سفارش</th>
                    <th class="p-3 text-right font-medium">تاریخ</th>
                    <th class="p-3 text-right font-medium">درگاه</th>
                    <th class="p-3 text-right font-medium">کد پیگیری</th>
                    <th class="p-3 text-right font-medium">مبلغ</th>
                    <th class="p-3 text-right font-medium">StoqS</th>
                    <th class="p-3 text-right font-medium">تسویه</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($payments as $payment)
                    <tr class="{{ $payment->status === \App\Models\Payment::STATUS_REFUNDED ? 'bg-red-50/50' : '' }}">
                        <td class="p-3 font-medium text-brand-800 fa-num" dir="ltr">
                            @if ($payment->order)
                                <a href="{{ route('admin.orders.show', $payment->order) }}" class="hover:text-accent-600">{{ $payment->order->number }}</a>
                            @else — @endif
                        </td>
                        <td class="p-3 text-xs text-brand-400 fa-num">{{ $payment->paid_at ? \App\Support\Jalali::format($payment->paid_at) : '—' }}</td>
                        <td class="p-3 text-brand-600">{{ $gateways[$payment->gateway] ?? $payment->gateway }}</td>
                        <td class="p-3 text-xs text-brand-400 fa-num" dir="ltr">{{ $payment->ref_id ?: $payment->authority }}</td>
                        <td class="p-3 text-brand-700">
                            {{ \App\Support\Money::toman($payment->amount) }}
                            @if ($payment->status === \App\Models\Payment::STATUS_REFUNDED)<span class="ms-1 text-xs text-red-500">(بازپرداخت)</span>@endif
                        </td>
                        <td class="p-3">
                            @if ($payment->order?->stockkeeping_sale_id)
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 fa-num" dir="ltr">{{ $payment->order->stockkeeping_sale_id }}</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-600">گزارش‌نشده</span>
                            @endif
                        </td>
                        <td class="p-3">
                            @if ($payment->isSettled())
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700">تسویه‌شده</span>
                                @if (!empty($payment->meta['settlement_ref']))<span class="block text-xs text-brand-400 fa-num" dir="ltr">{{ $payment->meta['settlement_ref'] }}</span>@endif
                            @else
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">در انتظار</span>
                            @endif
                        </td>
                        <td class="p-3 text-left">
                            @if (! $payment->isSettled() && $payment->status === \App\Models\Payment::STATUS_PAID)
                                <form method="POST" action="{{ route('admin.reports.reconciliation.settle', $payment) }}" class="flex items-center justify-end gap-1">
                                    @csrf
                                    <input name="settlement_ref" placeholder="کد دسته (اختیاری)" class="w-28 rounded-lg border border-brand-200 px-2 py-1 text-xs" dir="ltr">
                                    <button class="rounded-lg bg-brand-900 px-2.5 py-1 text-xs font-medium text-white">تسویه شد</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-8 text-center text-brand-400">پرداختی برای مغایرت‌گیری یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $payments->links() }}</div>
@endsection

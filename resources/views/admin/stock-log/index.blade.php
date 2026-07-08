@extends('admin.layout')
@section('title', 'گزارش حرکت موجودی StoqS')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-xl font-bold text-brand-900">گزارش حرکت موجودی StoqS</h1>
    <div class="flex gap-2 text-xs">
        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-blue-700 ring-1 ring-blue-200">
            <span class="h-2 w-2 rounded-full bg-blue-500"></span>ارسال به StoqS (out)
        </span>
        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-emerald-700 ring-1 ring-emerald-200">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>دریافت از StoqS (in)
        </span>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    <select name="type" class="col-span-2 sm:col-span-1 rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-brand-800">
        <option value="">همه رویدادها</option>
        @foreach ($typeLabels as $val => $label)
            <option value="{{ $val }}" @selected(request('type') === $val)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="direction" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-brand-800">
        <option value="">هر دو جهت</option>
        <option value="out" @selected(request('direction') === 'out')>ارسال (out)</option>
        <option value="in" @selected(request('direction') === 'in')>دریافت (in)</option>
    </select>
    <select name="status" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-brand-800">
        <option value="">همه وضعیت‌ها</option>
        <option value="ok" @selected(request('status') === 'ok')>موفق</option>
        <option value="failed" @selected(request('status') === 'failed')>خطا</option>
    </select>
    <input type="text" name="ref" value="{{ request('ref') }}"
           placeholder="شناسه / مرجع (CXL-، RET-، …)"
           class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-brand-800 placeholder-brand-300">
    <input type="date" name="from" value="{{ request('from') }}"
           class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-brand-800">
    <div class="flex gap-2">
        <input type="date" name="to" value="{{ request('to') }}"
               class="min-w-0 flex-1 rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm text-brand-800">
        <button type="submit" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-900">فیلتر</button>
    </div>
</form>

{{-- Stats bar --}}
<div class="mb-5 flex flex-wrap gap-3">
    @php
        $total = $logs->total();
        $failed = $logs->getCollection()->where('status', 'failed')->count();
        $out = $logs->getCollection()->where('direction', 'out')->count();
        $in  = $logs->getCollection()->where('direction', 'in')->count();
    @endphp
    <div class="rounded-lg bg-white px-4 py-2 text-sm ring-1 ring-brand-100">
        <span class="font-semibold text-brand-900">{{ number_format($total) }}</span>
        <span class="text-brand-400 mr-1">رویداد</span>
    </div>
    @if ($failed > 0)
    <div class="rounded-lg bg-red-50 px-4 py-2 text-sm ring-1 ring-red-200">
        <span class="font-semibold text-red-700">{{ $failed }}</span>
        <span class="text-red-400 mr-1">خطا در این صفحه</span>
    </div>
    @endif
</div>

@if ($logs->isEmpty())
    <div class="rounded-xl bg-white py-20 text-center text-brand-400 ring-1 ring-brand-100">
        هیچ رویدادی ثبت نشده است.
    </div>
@else
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-brand-100">
        <table class="min-w-full text-sm">
            <thead class="border-b border-brand-100 bg-brand-50">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">#</th>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">زمان</th>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">نوع رویداد</th>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">جهت</th>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">مرجع</th>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">جزئیات</th>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">وضعیت</th>
                    <th class="px-4 py-3 text-right font-semibold text-brand-500">StoqS ID</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @foreach ($logs as $log)
                <tr class="hover:bg-brand-50/50 {{ $log->status === 'failed' ? 'bg-red-50/30' : '' }}">
                    <td class="px-4 py-3 text-brand-400 tabular-nums">{{ $log->id }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-brand-500 tabular-nums" dir="ltr">
                        {{ $log->created_at->format('Y-m-d') }}<br>
                        <span class="text-xs text-brand-300">{{ $log->created_at->format('H:i:s') }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $typeCls = match($log->event_type) {
                                'sale_push'        => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
                                'cancel_restock'   => 'bg-amber-50 text-amber-700 ring-amber-200',
                                'return_restock'   => 'bg-orange-50 text-orange-700 ring-orange-200',
                                'webhook_in'       => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                'stock_pull'       => 'bg-teal-50 text-teal-700 ring-teal-200',
                                default            => 'bg-brand-50 text-brand-700 ring-brand-200',
                            };
                        @endphp
                        <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $typeCls }}">
                            {{ $typeLabels[$log->event_type] ?? $log->event_type }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if ($log->direction === 'out')
                            <span class="inline-flex items-center gap-1 text-blue-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                ارسال
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-emerald-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                                دریافت
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($log->ref)
                            <span class="font-mono text-xs text-brand-700">{{ $log->ref }}</span>
                        @endif
                        @if ($log->order)
                            <br><a href="{{ route('admin.orders.show', $log->order) }}"
                                   class="text-xs text-brand-400 hover:text-brand-700 underline">
                                سفارش #{{ $log->order->number }}
                            </a>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-brand-600">
                        @php $p = $log->payload ?? []; @endphp
                        @if ($log->event_type === 'sale_push')
                            {{ count($p['items'] ?? []) }} قلم
                            @if (!empty($p['total']))
                                · {{ number_format((int)$p['total']) }} ت
                            @endif
                        @elseif ($log->event_type === 'cancel_restock' || $log->event_type === 'return_restock')
                            {{ count($p) }} قلم
                            @foreach (array_slice($p, 0, 2) as $item)
                                <span class="ml-1 rounded bg-brand-50 px-1 py-0.5 font-mono text-xs text-brand-500">
                                    {{ $item['barcode'] ?? ('v:'.$item['variant_id']) }}+{{ $item['delta'] }}
                                </span>
                            @endforeach
                            @if (count($p) > 2)
                                <span class="text-xs text-brand-400">+{{ count($p)-2 }} بیشتر</span>
                            @endif
                        @elseif ($log->event_type === 'webhook_in')
                            <span class="font-mono text-xs">{{ $p['barcode'] ?? '—' }}</span>
                            @if (!empty($p['delta']))
                                <span class="{{ (int)$p['delta'] > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ (int)$p['delta'] > 0 ? '+' : '' }}{{ $p['delta'] }}
                                </span>
                            @elseif (!empty($p['new_stock']))
                                = {{ $p['new_stock'] }}
                            @endif
                            @if (!empty($p['reason']))
                                <span class="text-brand-400">({{ $p['reason'] }})</span>
                            @endif
                        @elseif ($log->event_type === 'stock_pull')
                            {{ $p['matched'] ?? 0 }} به‌روز شد
                            @if (($p['unmatched'] ?? 0) > 0)
                                · {{ $p['unmatched'] }} ناشناخته
                            @endif
                            · {{ $p['total'] ?? 0 }} کل
                        @else
                            <span class="text-brand-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($log->status === 'ok')
                            <span class="inline-flex items-center gap-1 text-xs text-emerald-700">
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                موفق
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-red-700" title="{{ $log->error }}">
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                خطا
                            </span>
                            @if ($log->error)
                                <p class="mt-0.5 max-w-[16rem] truncate text-xs text-red-400" title="{{ $log->error }}">{{ $log->error }}</p>
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($log->remote_id)
                            <span class="font-mono text-xs text-brand-500" title="{{ $log->remote_id }}">
                                {{ Str::limit($log->remote_id, 14) }}
                            </span>
                        @else
                            <span class="text-brand-200">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
@endif
@endsection

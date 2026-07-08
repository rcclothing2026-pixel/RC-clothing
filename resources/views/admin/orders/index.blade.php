@extends('admin.layout')

@section('title', 'سفارش‌ها')

@section('content')
    <h1 class="mb-6 text-xl font-bold text-brand-900">سفارش‌ها</h1>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="جستجوی شماره، نام یا موبایل..." class="w-full max-w-xs rounded-lg border border-brand-200 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
            <option value="">همه وضعیت‌ها</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-700">فیلتر</button>
    </form>

    <form method="POST" action="{{ route('admin.orders.bulk') }}" id="bulk-form">
        @csrf
        <div id="bulk-bar" class="mb-3 hidden flex-wrap items-center gap-2 rounded-card bg-brand-50 p-3 ring-1 ring-brand-100">
            <span class="text-sm text-brand-600"><b id="bulk-count" class="fa-num">۰</b> انتخاب شده — تغییر وضعیت به:</span>
            <select name="status" class="rounded-lg border border-brand-200 px-2 py-1.5 text-xs">
                @foreach ($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <button class="rounded-lg bg-brand-900 px-3 py-1.5 text-xs font-medium text-white">اعمال</button>
        </div>

        <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
            <table class="admin-table w-full text-sm">
                <thead class="bg-brand-50 text-xs text-brand-500">
                    <tr>
                        <th class="p-3"><input type="checkbox" id="check-all" class="rounded"></th>
                        <th class="p-3 text-right font-medium">شماره</th>
                        <th class="p-3 text-right font-medium">مشتری</th>
                        <th class="p-3 text-right font-medium">مبلغ</th>
                        <th class="p-3 text-right font-medium">وضعیت</th>
                        <th class="p-3 text-right font-medium">تاریخ</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-50">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="p-3"><input type="checkbox" name="ids[]" value="{{ $order->id }}" class="row-check rounded"></td>
                            <td class="p-3 font-medium text-brand-800 fa-num" dir="ltr"><a href="{{ route('admin.orders.show', $order) }}" class="hover:text-accent-600">{{ $order->number }}</a></td>
                            <td class="p-3 text-brand-600">{{ $order->customer_name }} <span class="text-xs text-brand-400 fa-num" dir="ltr">{{ $order->customer_phone }}</span></td>
                            <td class="p-3 text-brand-700">{{ $order->formattedTotal() }}</td>
                            <td class="p-3"><span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-600">{{ $order->statusLabel() }}</span></td>
                            <td class="p-3 text-xs text-brand-400 fa-num">{{ \App\Support\Jalali::format($order->created_at) }}</td>
                            <td class="p-3 text-left">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-accent-600 hover:underline">جزئیات</a>
                                <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" class="ms-2 text-brand-400 hover:text-brand-700">فاکتور</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-brand-400">سفارشی یافت نشد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-4 fa-num">{{ $orders->links() }}</div>

    <script>
        (function () {
            const form = document.getElementById('bulk-form');
            const bar = document.getElementById('bulk-bar');
            const countEl = document.getElementById('bulk-count');
            const checkAll = document.getElementById('check-all');
            const rows = () => Array.from(form.querySelectorAll('.row-check'));
            const faN = (n) => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]);
            function refresh() {
                const c = rows().filter(x => x.checked).length;
                countEl.textContent = faN(c);
                bar.classList.toggle('hidden', c === 0);
                bar.classList.toggle('flex', c > 0);
            }
            checkAll?.addEventListener('change', () => { rows().forEach(x => x.checked = checkAll.checked); refresh(); });
            form.addEventListener('change', (e) => { if (e.target.classList.contains('row-check')) refresh(); });
            refresh();
        })();
    </script>
@endsection

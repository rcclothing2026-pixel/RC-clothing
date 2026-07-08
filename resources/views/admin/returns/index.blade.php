@extends('admin.layout')

@section('title', 'مرجوعی‌ها')

@section('content')
    <h1 class="mb-6 text-xl font-bold text-brand-900">مرجوعی‌ها</h1>

    <form method="GET" class="mb-4">
        <select name="status" onchange="this.form.requestSubmit()" class="rounded-lg border border-brand-200 bg-white px-3 py-2 text-sm">
            <option value="">همه وضعیت‌ها</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">سفارش</th>
                    <th class="p-3 text-right font-medium">اقلام</th>
                    <th class="p-3 text-right font-medium">وضعیت</th>
                    <th class="p-3 text-right font-medium">تاریخ</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($returns as $return)
                    <tr>
                        <td class="p-3 font-medium text-brand-800 fa-num" dir="ltr">{{ $return->order?->number ?? '—' }}</td>
                        <td class="p-3 fa-num text-brand-600">{{ \App\Support\Money::toPersianDigits((string) collect($return->items)->sum('quantity')) }} کالا</td>
                        <td class="p-3"><span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand-600">{{ $return->statusLabel() }}</span></td>
                        <td class="p-3 text-xs text-brand-400 fa-num">{{ \App\Support\Jalali::format($return->created_at) }}</td>
                        <td class="p-3 text-left"><a href="{{ route('admin.returns.show', $return) }}" class="text-accent-600 hover:underline">بررسی</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-brand-400">درخواست مرجوعی‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 fa-num">{{ $returns->links() }}</div>
@endsection

@extends('admin.layout')

@section('title', 'بررسی مرجوعی')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">مرجوعی سفارش
            <a href="{{ route('admin.orders.show', $return->order) }}" class="fa-num text-accent-600 hover:underline" dir="ltr">{{ $return->order?->number }}</a>
        </h1>
        <a href="{{ route('admin.returns.index') }}" class="text-sm text-brand-500 hover:underline">→ بازگشت</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-3 text-sm font-bold text-brand-800">اقلام مرجوعی</h2>
            <table class="w-full text-sm">
                <thead class="text-xs text-brand-400"><tr><th class="py-2 text-right">کالا</th><th class="py-2 text-right">بارکد</th><th class="py-2 text-right">تعداد</th></tr></thead>
                <tbody class="divide-y divide-brand-50">
                    @foreach ($return->items as $it)
                        <tr>
                            <td class="py-2 text-brand-700">{{ $it['name'] ?? '—' }}@if(!empty($it['size'])) <span class="text-brand-400">({{ $it['size'] }})</span>@endif</td>
                            <td class="py-2 fa-num text-brand-400" dir="ltr">{{ $it['sku'] ?? '—' }}</td>
                            <td class="py-2 fa-num text-brand-700">{{ \App\Support\Money::toPersianDigits((string) ($it['quantity'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4 border-t border-brand-100 pt-4 text-sm">
                <p class="text-xs text-brand-400">دلیل مشتری</p>
                <p class="mt-1 whitespace-pre-line text-brand-600">{{ $return->reason }}</p>
            </div>
        </div>

        <aside class="h-fit space-y-3 rounded-card bg-white p-6 ring-1 ring-brand-100">
            <p class="text-sm">وضعیت فعلی: <span class="font-bold text-brand-900">{{ $return->statusLabel() }}</span></p>
            <form method="POST" action="{{ route('admin.returns.update', $return) }}" class="space-y-3">
                @csrf @method('PATCH')
                <select name="status" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    @foreach (['approved' => 'تأیید', 'rejected' => 'رد', 'received' => 'دریافت و بازگشت به انبار'] as $k => $label)
                        <option value="{{ $k }}" @selected($return->status === $k)>{{ $label }}</option>
                    @endforeach
                </select>
                <input name="admin_note" value="{{ $return->admin_note }}" placeholder="یادداشت (اختیاری)" class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                <button class="w-full rounded-lg bg-brand-900 py-2 text-sm font-semibold text-white">ثبت وضعیت</button>
            </form>
            <p class="text-xs text-brand-400">با انتخاب «دریافت و بازگشت به انبار»، موجودی به‌صورت محلی و در StoqS بازگردانده می‌شود.</p>
        </aside>
    </div>
@endsection

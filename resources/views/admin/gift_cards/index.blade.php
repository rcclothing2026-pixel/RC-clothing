@extends('admin.layout')

@section('title', 'کارت‌های هدیه')

@section('content')
    <h1 class="mb-6 text-xl font-bold text-brand-900">کارت‌های هدیه</h1>

    <form method="POST" action="{{ route('admin.gift-cards.store') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-card bg-white p-5 ring-1 ring-brand-100">
        @csrf
        <div>
            <label class="mb-1 block text-xs text-brand-600">کد (خالی = خودکار)</label>
            <input name="code" dir="ltr" class="rounded-lg border border-brand-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs text-brand-600">مبلغ (تومان)</label>
            <input name="initial_balance" type="number" min="1000" required class="rounded-lg border border-brand-200 px-3 py-2 text-sm fa-num">
        </div>
        <div>
            <label class="mb-1 block text-xs text-brand-600">انقضا (شمسی، اختیاری)</label>
            <input name="expires_at" data-jdp dir="ltr" autocomplete="off" class="rounded-lg border border-brand-200 px-3 py-2 text-sm text-center fa-num" placeholder="۱۴۰۴/۱۲/۲۹">
        </div>
        <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">صدور کارت</button>
    </form>

    <div class="overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr>
                    <th class="p-3 text-right font-medium">کد</th>
                    <th class="p-3 text-right font-medium">موجودی</th>
                    <th class="p-3 text-right font-medium">اولیه</th>
                    <th class="p-3 text-right font-medium">انقضا</th>
                    <th class="p-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-50">
                @forelse ($cards as $card)
                    <tr>
                        <td class="p-3 font-bold text-brand-800" dir="ltr">{{ $card->code }}</td>
                        <td class="p-3 text-brand-700">{{ \App\Support\Money::toman($card->balance) }}</td>
                        <td class="p-3 text-brand-400">{{ \App\Support\Money::toman($card->initial_balance) }}</td>
                        <td class="p-3 text-xs text-brand-400">{{ $card->expires_at ? \App\Support\Jalali::format($card->expires_at) : '—' }}</td>
                        <td class="p-3 text-left">
                            <form action="{{ route('admin.gift-cards.destroy', $card) }}" method="POST" onsubmit="return confirm('حذف این کارت؟')">@csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-600">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-8 text-center text-brand-400">کارت هدیه‌ای صادر نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 fa-num">{{ $cards->links() }}</div>
@endsection

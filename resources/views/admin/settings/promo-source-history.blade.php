@extends('admin.layout')

@section('title', 'تاریخچهٔ مدیریت تخفیف‌ها')

@section('content')
    @php($__fa = fn ($n) => \App\Support\Money::toPersianDigits((string) $n))
    @php($__src = fn ($s) => $s === 'stoqs' ? 'StoqS' : ($s === 'shop' ? 'فروشگاه' : '-'))
    @php($__origins = ['shop_admin' => 'مدیریت فروشگاه', 'stoqs_panel' => 'پنل StoqS', 'stoqs_api' => 'API StoqS', 'pull' => 'همگام‌سازی'])
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900">تاریخچهٔ مدیریت تخفیف‌ها</h1>
        <a href="{{ route('admin.settings.integration') }}" class="text-sm text-accent-700 hover:underline">بازگشت</a>
    </div>
    <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
        <table class="w-full text-start text-sm">
            <thead class="bg-brand-50 text-xs text-brand-500">
                <tr><th class="p-3 text-start">شماره</th><th class="p-3 text-start">تغییر</th><th class="p-3 text-start">از طرف</th><th class="p-3 text-start">توسط</th><th class="p-3 text-start">یادداشت</th><th class="p-3 text-start">زمان</th></tr>
            </thead>
            <tbody class="fa-num">
                @forelse ($rows as $r)
                    <tr class="border-t border-brand-100">
                        <td class="p-3">{{ $__fa($r->seq) }}</td>
                        <td class="p-3">{{ $__src($r->from_source) }} ← {{ $__src($r->to_source) }}</td>
                        <td class="p-3">{{ $__origins[$r->origin] ?? $r->origin }}@unless ($r->confirmed) <span class="text-amber-700">(تأیید نشده)</span>@endunless</td>
                        <td class="p-3">{{ $r->by_name ?? '-' }}</td>
                        <td class="p-3">{{ $r->note ?? '' }}</td>
                        <td class="p-3">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('Y/m/d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-brand-400">هنوز تغییری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@php($__ps = \App\Services\StockKeeping\StoqsPromoSource::state())
@if (\App\Services\StockKeeping\StoqsPromoSource::ready())
<div class="mb-6 rounded-card bg-white p-4 ring-1 ring-brand-100" id="promo-source">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-bold text-brand-800">مدیریت تخفیف‌های سایت</h2>
            <p class="mt-1 text-xs text-brand-500">تخفیف‌ها، کوپن‌ها و اوتلت را یا خود فروشگاه مدیریت می‌کند یا StoqS.</p>
        </div>
        <div class="flex items-center gap-2">
            @foreach (['shop' => 'مدیریت با فروشگاه', 'stoqs' => 'مدیریت با StoqS'] as $__key => $__label)
                @if ($__ps['source'] === $__key)
                    <span class="rounded-lg bg-brand-900 px-3 py-1.5 text-xs font-bold text-white">{{ $__label }}</span>
                @else
                    <form method="POST" action="{{ route('admin.settings.promo-source.prepare') }}">
                        @csrf
                        <input type="hidden" name="to" value="{{ $__key }}">
                        <button class="rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">{{ $__label }}</button>
                    </form>
                @endif
            @endforeach
        </div>
    </div>
    <p class="mt-3 text-xs text-brand-500 fa-num">
        شمارهٔ تغییر: {{ \App\Support\Money::toPersianDigits((string) $__ps['seq']) }}
        @if ($__ps['changed_at'])
            · {{ \Illuminate\Support\Carbon::parse($__ps['changed_at'])->format('Y/m/d H:i') }}
        @endif
        @if ($__ps['by'])
            · توسط {{ $__ps['by'] }}
        @endif
        @if ($__ps['note'])
            · «{{ $__ps['note'] }}»
        @endif
        @if ($__ps['pending'])
            <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-amber-700">در انتظار تأیید StoqS</span>
        @endif
        · <a href="{{ route('admin.settings.promo-source.history') }}" class="text-accent-700 hover:underline">تاریخچه</a>
    </p>
</div>
@endif

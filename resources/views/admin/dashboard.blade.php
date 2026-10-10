@extends('admin.layout')

@section('title', 'داشبورد')

@section('content')
    <h1 class="mb-6 text-xl font-bold text-brand-900">داشبورد</h1>

    {{-- Headline KPIs --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        {{-- Today's revenue + order count --}}
        <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">درآمد امروز</p>
            <p class="mt-2 text-lg font-bold text-brand-900">{{ \App\Support\Money::toman($todayRevenue) }}</p>
            <p class="mt-1 text-[11px] text-brand-400">
                <span class="fa-num">{{ \App\Support\Money::toPersianDigits((string) $todayOrderCount) }}</span> سفارش پرداخت‌شده
            </p>
        </div>

        {{-- This week + WoW delta --}}
        <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">درآمد ۷ روز اخیر</p>
            <p class="mt-2 text-lg font-bold text-brand-900">{{ \App\Support\Money::toman($weekRevenue) }}</p>
            @if ($weekDeltaPct !== null)
                @php($up = $weekDeltaPct >= 0)
                <p class="mt-1 text-[11px] font-medium {{ $up ? 'text-green-600' : 'text-red-500' }}">
                    {{ $up ? '▲' : '▼' }} <span class="fa-num">{{ \App\Support\Money::toPersianDigits((string) abs($weekDeltaPct)) }}٪</span> نسبت به ۷ روز قبل
                </p>
            @else
                <p class="mt-1 text-[11px] text-brand-400">داده هفته قبل برای مقایسه نیست</p>
            @endif
        </div>

        {{-- This month --}}
        <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">درآمد این ماه</p>
            <p class="mt-2 text-lg font-bold text-brand-900">{{ \App\Support\Money::toman($monthRevenue) }}</p>
            <p class="mt-1 text-[11px] text-brand-400">جمع کل: {{ \App\Support\Money::toman($revenue) }}</p>
        </div>

        {{-- Action-needed counters --}}
        <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
            <p class="text-xs text-brand-400">نیازمند رسیدگی</p>
            <div class="mt-2 space-y-1.5 text-sm">
                <a href="{{ route('admin.orders.index', ['status' => 'pending']) }}" class="flex items-center justify-between gap-2 text-brand-700 transition hover:text-accent-600">
                    <span>در انتظار پرداخت</span>
                    <span class="rounded-full {{ $pendingCount > 0 ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand-500' }} px-2 py-0.5 text-xs font-bold fa-num">{{ \App\Support\Money::toPersianDigits((string) $pendingCount) }}</span>
                </a>
                <a href="{{ route('admin.messages.index') }}" class="flex items-center justify-between gap-2 text-brand-700 transition hover:text-accent-600">
                    <span>پیام خوانده‌نشده</span>
                    <span class="rounded-full {{ $unreadSupport > 0 ? 'bg-blue-50 text-blue-700' : 'bg-brand-50 text-brand-500' }} px-2 py-0.5 text-xs font-bold fa-num">{{ \App\Support\Money::toPersianDigits((string) $unreadSupport) }}</span>
                </a>
                <a href="{{ route('admin.orders.index', ['status' => 'failed']) }}" class="flex items-center justify-between gap-2 text-brand-700 transition hover:text-accent-600">
                    <span>پرداخت ناموفق (۷ روز)</span>
                    <span class="rounded-full {{ $failedPayments > 0 ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-500' }} px-2 py-0.5 text-xs font-bold fa-num">{{ \App\Support\Money::toPersianDigits((string) $failedPayments) }}</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Sales trend (14 days) --}}
    <section class="mt-6 rounded-card bg-white p-6 ring-1 ring-brand-100">
        <h2 class="mb-4 text-base font-bold text-brand-900">روند فروش (۱۴ روز اخیر)</h2>
        @php($max = max(1, collect($salesSeries)->max('rev')))
        <div class="flex h-40 items-end gap-1.5">
            @foreach ($salesSeries as $point)
                <div class="group flex h-full flex-1 flex-col items-center justify-end" title="{{ $point['label'] }} — {{ \App\Support\Money::toman($point['rev']) }}">
                    <div class="w-full rounded-t bg-brand-800/85 transition group-hover:bg-accent-500" style="height: {{ max(2, (int) round($point['rev'] / $max * 100)) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-[10px] text-brand-300 fa-num">
            <span dir="ltr">{{ $salesSeries[0]['label'] ?? '' }}</span>
            <span dir="ltr">{{ end($salesSeries)['label'] ?? '' }}</span>
        </div>
    </section>

    @if ($topProducts->isNotEmpty())
        <section class="mt-6 rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-4 text-base font-bold text-brand-900">پرفروش‌ترین محصولات</h2>
            <div class="space-y-2">
                @foreach ($topProducts as $tp)
                    <div class="flex items-center justify-between border-b border-brand-50 py-2 text-sm last:border-0">
                        <span class="text-brand-800">{{ $tp->name }}</span>
                        <span class="flex items-center gap-4">
                            <span class="text-xs text-brand-400 fa-num">{{ \App\Support\Money::toPersianDigits((string) $tp->qty) }} عدد</span>
                            <span class="font-bold text-brand-900">{{ \App\Support\Money::toman((int) $tp->revenue) }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-4 text-base font-bold text-brand-900">سفارش‌های اخیر</h2>
            @forelse ($recentOrders as $order)
                <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center justify-between border-b border-brand-50 py-2.5 text-sm last:border-0">
                    <span class="font-medium text-brand-800 fa-num" dir="ltr">{{ $order->number }}</span>
                    <span class="text-xs text-brand-500">{{ $order->statusLabel() }}</span>
                    <span class="font-bold text-brand-900">{{ $order->formattedTotal() }}</span>
                </a>
            @empty
                <p class="text-sm text-brand-400">سفارشی ثبت نشده است.</p>
            @endforelse
        </section>

        <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-4 text-base font-bold text-brand-900">موجودی رو به اتمام</h2>
            @forelse ($lowStock as $variant)
                <div class="flex items-center justify-between border-b border-brand-50 py-2.5 text-sm last:border-0">
                    <span class="line-clamp-1 text-brand-800">{{ $variant->product?->name }} <span class="text-xs text-brand-400">({{ $variant->color }} {{ $variant->size }})</span></span>
                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-600 fa-num">{{ \App\Support\Money::toPersianDigits((string) $variant->stock_qty) }}</span>
                </div>
            @empty
                <p class="text-sm text-brand-400">موجودی همه محصولات کافی است.</p>
            @endforelse
        </section>
    </div>
@endsection

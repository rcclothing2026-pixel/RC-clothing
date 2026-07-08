@php
    $gatewayLabels = ['zarinpal' => 'زرین‌پال', 'zibal' => 'زیبال', 'snapppay' => 'اسنپ‌پی', 'gift' => 'کارت هدیه'];
    $paymentLabels = ['pending' => 'در انتظار', 'paid' => 'پرداخت شده', 'failed' => 'ناموفق', 'canceled' => 'لغو شده', 'refunded' => 'بازپرداخت شده'];
    $addr = $order->shipping_address;
@endphp

@extends('admin.layout')

@section('title', 'سفارش ' . $order->number)

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-bold text-brand-900 fa-num" dir="ltr">{{ $order->number }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders.invoice', $order) }}" target="_blank" class="rounded-lg bg-brand-100 px-3 py-1.5 text-sm font-medium text-brand-700">🖨 فاکتور</a>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-brand-500 hover:underline">→ بازگشت</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">اطلاعات سفارش</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-brand-50">
                        <tr><td class="py-2 font-medium text-brand-500">کد سفارش</td><td class="py-2 text-brand-900 fa-num" dir="ltr">{{ $order->number }}</td></tr>
                        <tr><td class="py-2 font-medium text-brand-500">تاریخ ثبت سفارش</td><td class="py-2 text-brand-900 fa-num">{{ $order->placed_at ? \App\Support\Jalali::format($order->placed_at, true) : \App\Support\Jalali::format($order->created_at, true) }}</td></tr>
                        <tr><td class="py-2 font-medium text-brand-500">تاریخ پرداخت</td><td class="py-2 text-brand-900 fa-num">{{ $order->paid_at ? \App\Support\Jalali::format($order->paid_at, true) : '—' }}</td></tr>
                        <tr><td class="py-2 font-medium text-brand-500">آخرین به‌روزرسانی</td><td class="py-2 text-brand-900 fa-num">{{ \App\Support\Jalali::format($order->updated_at, true) }}</td></tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">مشتری و آدرس</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-brand-50">
                        <tr><td class="py-2 font-medium text-brand-500">نام و نام خانوادگی</td><td class="py-2 text-brand-900">{{ $order->customer_name }}</td></tr>
                        <tr><td class="py-2 font-medium text-brand-500">اطلاعات تماس</td><td class="py-2 text-brand-900 fa-num" dir="ltr">{{ $order->customer_phone }}</td></tr>
                        <tr><td class="py-2 font-medium text-brand-500">آدرس</td>
                            <td class="py-2 text-brand-700">
                                @if ($addr)
                                    {{ $addr['province'] ?? '' }}، {{ $addr['city'] ?? '' }} — {{ $addr['line'] ?? '' }}
                                    @if (!empty($addr['postal_code']))<br><span class="text-xs text-brand-400 fa-num" dir="ltr">کد پستی: {{ $addr['postal_code'] }}</span>@endif
                                @else
                                    <span class="text-brand-400">—</span>
                                @endif
                            </td>
                        </tr>
                        <tr><td class="py-2 font-medium text-brand-500">یادداشت مشتری</td><td class="py-2 text-brand-700">{{ $order->customer_note ?: '—' }}</td></tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">روش‌ها و پیگیری</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-brand-50">
                        <tr><td class="py-2 font-medium text-brand-500">روش پرداخت</td>
                            <td class="py-2 text-brand-900">
                                @if ($order->payment && $order->payment->gateway)
                                    @php($gw = $order->payment->gateway)
                                    {{ $gw === 'gift' ? 'کارت هدیه' : ($gatewayLabels[$gw] ?? $gw) }}
                                @else
                                    <span class="text-brand-400">—</span>
                                @endif
                            </td>
                        </tr>
                        <tr><td class="py-2 font-medium text-brand-500">شیوه ارسال</td><td class="py-2 text-brand-900">{{ $order->shipping_method_name ?? '—' }}</td></tr>
                        <tr><td class="py-2 font-medium text-brand-500">وزن</td><td class="py-2 text-brand-400">—</td></tr>
                        <tr><td class="py-2 font-medium text-brand-500">کد پیگیری درگاه پرداخت</td>
                            <td class="py-2 text-brand-700 fa-num" dir="ltr">
                                @if ($order->payment && $order->payment->ref_id)
                                    {{ $order->payment->ref_id }}
                                @else
                                    <span class="text-brand-400">—</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-card bg-white p-6 ring-1 ring-brand-100">
                <h2 class="mb-4 text-base font-bold text-brand-900">اقلام سفارش</h2>
                <div class="divide-y divide-brand-50">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <div>
                                <p class="font-medium text-brand-800">{{ $item->name }}</p>
                                <p class="text-xs text-brand-400">{{ $item->color }} {{ $item->size }} — تعداد <span class="fa-num">{{ \App\Support\Money::toPersianDigits((string) $item->quantity) }}</span> @if($item->sku)— SKU: <span dir="ltr">{{ $item->sku }}</span>@endif</p>
                            </div>
                            <span class="font-semibold text-brand-900">{{ $item->formattedLineTotal() }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 space-y-1.5 border-t border-brand-100 pt-4 text-sm">
                    <div class="flex justify-between"><span class="text-brand-500">جمع کالاها</span><span>{{ \App\Support\Money::toman($order->subtotal) }}</span></div>
                    @if($order->discount > 0)<div class="flex justify-between"><span class="text-green-600">تخفیف</span><span class="text-green-600">−{{ \App\Support\Money::toman($order->discount) }}</span></div>@endif
                    @if($order->coupon_code)<div class="flex justify-between"><span class="text-green-600">کد «{{ $order->coupon_code }}»</span><span class="text-green-600">—</span></div>@endif
                    @if($order->loyalty_discount > 0)<div class="flex justify-between"><span class="text-green-600">امتیاز باشگاه</span><span class="text-green-600">−{{ \App\Support\Money::toman($order->loyalty_discount) }}</span></div>@endif
                    @if($order->gift_used > 0)<div class="flex justify-between"><span class="text-green-600">کارت هدیه</span><span class="text-green-600">−{{ \App\Support\Money::toman($order->gift_used) }}</span></div>@endif
                    <div class="flex justify-between"><span class="text-brand-500">ارسال ({{ $order->shipping_method_name }})</span><span>{{ $order->shipping_cost > 0 ? \App\Support\Money::toman($order->shipping_cost) : 'رایگان' }}</span></div>
                    <div class="flex justify-between text-base font-bold"><span>مبلغ کل</span><span class="text-brand-900">{{ $order->formattedTotal() }}</span></div>
                </div>
            </section>
        </div>

        <aside class="h-fit space-y-4 rounded-card bg-white p-6 ring-1 ring-brand-100">
            <div>
                <p class="mb-2 text-sm font-bold text-brand-900">وضعیت سفارش</p>
                <form action="{{ route('admin.orders.status', $order) }}" method="POST" class="flex gap-2">
                    @csrf @method('PATCH')
                    <select name="status" class="flex-1 rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected($order->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-brand-900 px-3 text-sm font-semibold text-white">ثبت</button>
                </form>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (['processing' => '📦 آماده‌سازی', 'shipped' => '✅ ارسال شد', 'delivered' => '✔️ تحویل شد'] as $key => $label)
                        <form action="{{ route('admin.orders.status', $order) }}" method="POST">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $key }}">
                            <button class="rounded-lg px-3 py-1.5 text-xs font-medium {{ $order->status === $key ? 'bg-green-600 text-white' : 'bg-brand-100 text-brand-700 hover:bg-brand-200' }}">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-brand-100 pt-4 text-sm">
                <p class="mb-1 font-bold text-brand-900">پرداخت</p>
                @if ($order->payment)
                    @php($gw = $order->payment->gateway)
                    <p class="text-brand-500">وضعیت: {{ $paymentLabels[$order->payment->status] ?? $order->payment->status }}</p>
                    <p class="text-brand-500">روش: {{ $gatewayLabels[$gw] ?? $gw }}</p>
                    @if ($order->payment->ref_id)<p class="mt-1 text-xs text-brand-400 fa-num" dir="ltr">کد پیگیری: {{ $order->payment->ref_id }}</p>@endif
                @else
                    <p class="text-brand-400">—</p>
                @endif
                @if ($order->isPaid() && optional($order->payment)->status !== 'refunded')
                    <form action="{{ route('admin.orders.refund', $order) }}" method="POST" class="mt-3" onsubmit="return confirm('سفارش بازپرداخت و لغو شود؟ موجودی به انبار بازمی‌گردد.')">
                        @csrf
                        <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">↩ بازپرداخت و لغو</button>
                    </form>
                @endif
            </div>

            <div class="border-t border-brand-100 pt-4 text-xs text-brand-400">
                <p>گزارش به StoqS:</p>
                @if ($order->stockkeeping_sale_id)
                    <p class="mt-1 fa-num text-green-600" dir="ltr">{{ $order->stockkeeping_sale_id }}</p>
                @else
                    <p class="mt-1 font-medium {{ optional($stoqsEvent ?? null)->status === 'failed' ? 'text-red-600' : 'text-amber-600' }}">
                        {{ optional($stoqsEvent ?? null)->status === 'failed' ? 'ارسال ناموفق بود' : 'در صف ارسال' }}
                    </p>
                    @if (optional($stoqsEvent ?? null)->last_error)
                        <p class="mt-1 break-words rounded bg-red-50 p-2 text-red-500" dir="ltr">{{ \Illuminate\Support\Str::limit($stoqsEvent->last_error, 400) }}</p>
                    @endif
                    <form action="{{ route('admin.orders.resend-stoqs', $order) }}" method="POST" class="mt-2">
                        @csrf
                        <button class="rounded-lg border border-brand-200 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-50">↻ ارسال مجدد به StoqS</button>
                    </form>
                @endif
                @if ($order->fulfillmentLocationLabel())
                    <p class="mt-2">کسر موجودی از: <span class="text-brand-600">{{ $order->fulfillmentLocationLabel() }}</span></p>
                @endif
            </div>
        </aside>
    </div>
@endsection

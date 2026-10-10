<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Order Invoice') }} {{ $order->number }}</title>
    @php($s = \App\Models\Setting::map())
    <style>
        @font-face { font-family: Vazirmatn; src: local('Vazirmatn'); }
        * { box-sizing: border-box; }
        body { font-family: Vazirmatn, Tahoma, sans-serif; color: #1f2433; margin: 0; padding: 24px; background: #fff; font-size: 13px; line-height: 1.8; }
        .sheet { max-width: 800px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f2433; padding-bottom: 16px; }
        .head h1 { margin: 0; font-size: 22px; }
        .muted { color: #6b7280; font-size: 12px; }
        .meta { margin-top: 16px; display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .box { background: #f7f8fa; border-radius: 8px; padding: 12px 14px; flex: 1; min-width: 220px; }
        .box b { display: block; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { padding: 9px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        thead th { background: #f0f1f4; font-size: 12px; color: #4b5563; }
        tfoot td { border: none; padding: 4px 8px; }
        .totals { margin-top: 8px; margin-inline-start: auto; width: 280px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { border-top: 2px solid #1f2433; margin-top: 6px; padding-top: 8px; font-weight: 700; font-size: 15px; }
        .fa-num { font-variant-numeric: tabular-nums; }
        .actions { text-align: center; margin: 20px 0; }
        .btn { background: #1f2433; color: #fff; border: 0; border-radius: 8px; padding: 10px 22px; font: inherit; cursor: pointer; }
        @media print { .actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="actions"><button class="btn" onclick="window.print()">{{ __('Print Invoice') }}</button></div>
    <div class="sheet">
        <div class="head">
            <div>
                <h1>{{ ($s['site.store_name'] ?? null) ?: 'Racket Club' }}</h1>
                <div class="muted">{{ __('Sales Invoice') }}</div>
            </div>
            <div style="text-align:right">
                <div>{{ __('Number:') }} <b class="fa-num" dir="ltr">{{ $order->number }}</b></div>
                <div class="muted">{{ __('Date:') }} {{ \App\Support\Jalali::format($order->placed_at ?? $order->created_at, true) }}</div>
                <div class="muted">{{ __('Status:') }} {{ $order->statusLabel() }}</div>
            </div>
        </div>

        <div class="meta">
            <div class="box">
                <b>{{ __('Customer') }}</b>
                {{ $order->customer_name }} — <span class="fa-num" dir="ltr">{{ $order->customer_phone }}</span>
            </div>
            @if ($order->shipping_address)
                @php($a = (array) $order->shipping_address)
                <div class="box">
                    <b>{{ __('Shipping Address') }}</b>
                    {{ $a['province'] ?? '' }}, {{ $a['city'] ?? '' }} — {{ $a['line'] ?? '' }}
                    @if (!empty($a['postal_code']))<div class="muted fa-num">{{ __('Postal code:') }} {{ $a['postal_code'] }}</div>@endif
                </div>
            @endif
        </div>

        <table>
            <thead>
                <tr><th>#</th><th>{{ __('Item') }}</th><th>{{ __('Qty') }}</th><th>{{ __('Unit Price') }}</th><th>{{ __('Total') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($order->items as $i => $item)
                    <tr>
                        <td class="fa-num">{{ $i + 1 }}</td>
                        <td>{{ $item->name }}@if($item->size) <span class="muted">({{ $item->size }}@if($item->color) / {{ $item->color }}@endif)</span>@endif</td>
                        <td class="fa-num">{{ $item->quantity }}</td>
                        <td>{{ \App\Support\Money::toman($item->unit_price) }}</td>
                        <td>{{ \App\Support\Money::toman($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>{{ __('Subtotal') }}</span><span>{{ \App\Support\Money::toman($order->subtotal) }}</span></div>
            @if ($order->discount > 0)
                <div class="row"><span>{{ __('Discount') }}{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}</span><span>−{{ \App\Support\Money::toman($order->discount) }}</span></div>
            @endif
            <div class="row"><span>{{ __('Shipping') }}{{ $order->shipping_method_name ? ' ('.$order->shipping_method_name.')' : '' }}</span><span>{{ $order->shipping_cost > 0 ? \App\Support\Money::toman($order->shipping_cost) : __('Free') }}</span></div>
            <div class="row grand"><span>{{ __('Total') }}</span><span>{{ \App\Support\Money::toman($order->total) }}</span></div>
        </div>

        @if ($order->payment && $order->payment->ref_id)
            <p class="muted" style="margin-top:18px">{{ __('Payment reference:') }} <span class="fa-num" dir="ltr">{{ $order->payment->ref_id }}</span></p>
        @endif
        @if ($phone = ($s['site.contact_phone'] ?? null))
            <p class="muted">{{ __('Support:') }} <span dir="ltr">{{ $phone }}</span></p>
        @endif
    </div>
</body>
</html>

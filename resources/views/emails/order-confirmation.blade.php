<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأیید سفارش {{ $order->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #f5f1f2; font-family: Tahoma, Arial, sans-serif; font-size: 14px; color: #282828; direction: rtl; }
        .wrap { max-width: 600px; margin: 32px auto; }
        .header { background: #282828; border-radius: 16px 16px 0 0; padding: 28px 32px; text-align: center; }
        .header a { text-decoration: none; }
        .brand { font-size: 24px; font-weight: bold; color: #e0d5d9; letter-spacing: 2px; }
        .tagline { color: #9e8e92; font-size: 12px; margin-top: 4px; }
        .card { background: #ffffff; padding: 32px; }
        .card + .card { border-top: 1px solid #f0eaec; }
        h2 { font-size: 18px; font-weight: bold; color: #282828; margin-bottom: 16px; }
        .order-badge { display: inline-block; background: #f5f1f2; border: 1px solid #e0d5d9; border-radius: 8px; padding: 8px 16px; font-size: 16px; font-weight: bold; color: #cc3333; letter-spacing: 1px; margin-bottom: 16px; }
        .intro { color: #524d4f; line-height: 1.8; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f5f1f2; padding: 10px 12px; text-align: right; font-size: 12px; color: #9e8e92; font-weight: normal; border-bottom: 1px solid #e0d5d9; }
        td { padding: 12px; border-bottom: 1px solid #f0eaec; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .item-name { font-weight: bold; color: #282828; }
        .item-meta { font-size: 12px; color: #9e8e92; margin-top: 2px; }
        .price { font-weight: bold; white-space: nowrap; text-align: left; direction: ltr; }
        .totals { margin-top: 16px; }
        .totals tr td { border-bottom: none; padding: 6px 12px; }
        .totals tr:last-child td { border-top: 2px solid #282828; font-weight: bold; font-size: 16px; padding-top: 12px; }
        .address-box { background: #f5f1f2; border-radius: 12px; padding: 16px; margin-top: 8px; line-height: 1.9; color: #524d4f; }
        .cta { text-align: center; padding: 32px; }
        .btn { display: inline-block; background: #282828; color: #ffffff; text-decoration: none; padding: 14px 36px; border-radius: 100px; font-size: 14px; font-weight: bold; }
        .footer { background: #282828; border-radius: 0 0 16px 16px; padding: 20px 32px; text-align: center; }
        .footer p { color: #9e8e92; font-size: 12px; line-height: 1.8; }
        .footer a { color: #cc3333; text-decoration: none; }
        @media (max-width: 640px) {
            .wrap { margin: 0; }
            .header { border-radius: 0; padding: 20px; }
            .card { padding: 20px; }
            .footer { border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="wrap">
    {{-- Header --}}
    <div class="header">
        <a href="{{ config('app.url') }}">
            <div class="brand">CHIIACO</div>
            <div class="tagline">فروشگاه آنلاین چیاکو</div>
        </a>
    </div>

    {{-- Greeting --}}
    <div class="card">
        <div class="order-badge">{{ $order->number }}</div>
        <p class="intro">
            {{ $order->customer_name ?: 'مشتری گرامی' }}،<br>
            سفارش شما با موفقیت ثبت و پرداخت آن تأیید شد.
            به محض آماده‌سازی، اطلاع‌رسانی خواهیم کرد.
        </p>
    </div>

    {{-- Items --}}
    <div class="card">
        <h2>اقلام سفارش</h2>
        <table>
            <thead>
                <tr>
                    <th>محصول</th>
                    <th>تعداد</th>
                    <th>قیمت</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            <div class="item-name">{{ $item->name }}</div>
                            @if ($item->color || $item->size)
                                <div class="item-meta">
                                    @if ($item->color)رنگ: {{ $item->color }}@endif
                                    @if ($item->color && $item->size) · @endif
                                    @if ($item->size)سایز: {{ $item->size }}@endif
                                </div>
                            @endif
                        </td>
                        <td>{{ $item->quantity }}</td>
                        <td class="price">{{ $item->formattedLineTotal() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            @if ($order->discount > 0)
                <tr>
                    <td style="color:#9e8e92">جمع کالاها</td>
                    <td class="price" style="text-align:left">{{ \App\Support\Money::toman($order->subtotal) }}</td>
                </tr>
                <tr>
                    <td style="color:#2d7a4f">تخفیف</td>
                    <td class="price" style="color:#2d7a4f;text-align:left">−{{ \App\Support\Money::toman($order->discount) }}</td>
                </tr>
            @endif
            @if ($order->shipping_cost > 0)
                <tr>
                    <td style="color:#9e8e92">هزینه ارسال</td>
                    <td class="price" style="text-align:left">{{ \App\Support\Money::toman($order->shipping_cost) }}</td>
                </tr>
            @endif
            <tr>
                <td>مبلغ پرداختی</td>
                <td class="price" style="text-align:left;color:#cc3333">{{ $order->formattedTotal() }}</td>
            </tr>
        </table>
    </div>

    {{-- Shipping address --}}
    @if ($order->shipping_address)
        <div class="card">
            <h2>آدرس تحویل</h2>
            @php($addr = $order->shipping_address)
            <div class="address-box">
                @if (!empty($addr['name']))<strong>{{ $addr['name'] }}</strong><br>@endif
                @if (!empty($addr['province'])){{ $addr['province'] }}@endif
                @if (!empty($addr['city'])) — {{ $addr['city'] }}@endif
                @if (!empty($addr['address']))<br>{{ $addr['address'] }}@endif
                @if (!empty($addr['postal_code']))<br>کد پستی: {{ $addr['postal_code'] }}@endif
                @if (!empty($addr['phone']))<br>{{ $addr['phone'] }}@endif
            </div>
        </div>
    @endif

    {{-- CTA --}}
    <div class="card cta">
        <p style="color:#524d4f;margin-bottom:20px">برای مشاهده جزئیات سفارش وارد حساب کاربری خود شوید.</p>
        <a href="{{ route('account.orders') }}" class="btn">مشاهده سفارش‌ها</a>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <p>
            چیاکو — فروشگاه آنلاین پوشاک<br>
            <a href="{{ config('app.url') }}">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a>
            &nbsp;·&nbsp; این ایمیل خودکار است، پاسخ ندهید.
        </p>
    </div>
</div>
</body>
</html>

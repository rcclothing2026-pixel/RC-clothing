@extends('admin.layout')

@section('title', 'اتصال به StoqS')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">اتصال به StoqS</h1>
        <p class="mt-1 text-sm text-brand-500">انبار/فروشگاه مبدأ موجودی سایت، گزارش فروش، و همگام‌سازی کاتالوگ.</p>
    </div>

    {{-- Scheduler heartbeat: proves the schedule:run cron is firing (which drives
         the automatic stock pull / sales flush). Red = cron broken or missing. --}}
    @php($hb = $status['scheduler_last_run'] ?? null)
    @php($hbAge = $hb !== null ? (now()->timestamp - (int) $hb) : null)
    {{-- 15-min window: the scheduler is driven by web traffic + an optional
         external minute-pinger, so brief idle gaps are normal and shouldn't
         alarm. Red only means nothing has ticked for a quarter hour. --}}
    @php($hbOk = $hbAge !== null && $hbAge <= 900)
    <div class="mb-6 rounded-card p-4 ring-1 {{ $hbOk ? 'bg-green-50 ring-green-200' : 'bg-red-50 ring-red-200' }}">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-block h-2.5 w-2.5 rounded-full {{ $hbOk ? 'bg-green-500' : 'bg-red-500' }}"></span>
            <span class="text-sm font-bold {{ $hbOk ? 'text-green-700' : 'text-red-700' }}">
                {{ $hbOk ? 'زمان‌بند خودکار فعال است' : 'زمان‌بند خودکار اجرا نمی‌شود' }}
            </span>
            <span class="text-xs {{ $hbOk ? 'text-green-600' : 'text-red-600' }}">
                @if ($hb === null)
                    · تاکنون اجرا نشده
                @elseif ($hbAge < 90)
                    · آخرین اجرا همین الان
                @else
                    · آخرین اجرا {{ \App\Support\Money::toPersianDigits(intdiv($hbAge, 60)) }} دقیقه پیش
                @endif
            </span>
        </div>
        @unless ($hbOk)
            <p class="mt-2 text-xs leading-6 text-red-600">
                همگام‌سازی خودکار با ترافیک سایت اجرا می‌شود؛ در ۱۵ دقیقهٔ گذشته هیچ اجرایی ثبت نشده.
                برای اجرای مطمئن و مستقل از ترافیک، یک سرویس «هر دقیقه» بیرونی (مثل cron-job.org)
                را به آدرس زیر وصل کنید:
            </p>
            {{-- Reliable minute-tick: any external uptime/cron service (cron-job.org,
                 etc.) hitting this URL every 60s drives the scheduler regardless of
                 site traffic or the host's (broken) cron. Same in-process path the
                 manual-sync buttons use. --}}
            @php($cronUrl = url('/cron/run/'.\App\Support\CronToken::value()))
            <div class="mt-3 rounded bg-white p-2.5 ring-1 ring-red-100">
                <code dir="ltr" class="block break-all rounded bg-red-50 px-2 py-1.5 text-[11px] leading-5 text-red-800 select-all">{{ $cronUrl }}</code>
                <p class="mt-1.5 text-[11px] leading-5 text-brand-500">
                    برای تست، همین حالا <a href="{{ $cronUrl }}" target="_blank" rel="noopener" class="font-medium text-red-700 underline">این لینک را باز کنید</a> — اگر «OK» دیدید، سپس این صفحه را تازه کنید.
                    ضمناً هر بازدید از سایت هم زمان‌بند را یک بار اجرا می‌کند.
                </p>
            </div>
        @endunless
    </div>

    {{-- Status cards --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <div class="text-xs text-brand-400">وضعیت اتصال</div>
            <div class="mt-1 font-bold {{ $cfg['enabled'] ? 'text-green-600' : 'text-brand-400' }}">{{ $cfg['enabled'] ? 'فعال' : 'غیرفعال' }}</div>
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <div class="text-xs text-brand-400">آخرین کاتالوگ</div>
            @if($lastSyncs['catalog'])
            <div class="mt-1 text-sm font-medium text-brand-700 fa-num" title="{{ $lastSyncs['catalog']->summary }}">
                {{ $lastSyncs['catalog']->created_at->format('Y/m/d H:i') }}
                <span class="text-xs text-brand-400">{!! $lastSyncs['catalog']->source === 'manual' ? '<span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">دستی</span>' : '<span class="rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700">خودکار</span>' !!}</span>
            </div>
            @else
            <div class="mt-1 text-sm text-brand-400">—</div>
            @endif
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <div class="text-xs text-brand-400">آخرین مجموعه‌ها</div>
            @if($lastSyncs['collections'])
            <div class="mt-1 text-sm font-medium text-brand-700 fa-num">
                {{ $lastSyncs['collections']->created_at->format('Y/m/d H:i') }}
                <span class="text-xs text-brand-400">{!! $lastSyncs['collections']->source === 'manual' ? '<span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">دستی</span>' : '<span class="rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700">خودکار</span>' !!}</span>
            </div>
            @else
            <div class="mt-1 text-sm text-brand-400">—</div>
            @endif
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <div class="text-xs text-brand-400">آخرین موجودی</div>
            @if($lastSyncs['stock'])
            <div class="mt-1 text-sm font-medium text-brand-700 fa-num">
                {{ $lastSyncs['stock']->created_at->format('Y/m/d H:i') }}
                <span class="text-xs text-brand-400">{!! $lastSyncs['stock']->source === 'manual' ? '<span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">دستی</span>' : '<span class="rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700">خودکار</span>' !!}</span>
            </div>
            @else
            <div class="mt-1 text-sm text-brand-400">—</div>
            @endif
        </div>
    </div>

    {{-- Orders StoqS rejected because the product isn't in StoqS yet --}}
    @if (!empty($unmatchedSales) && count($unmatchedSales))
        <div class="mb-6 rounded-card bg-amber-50 p-4 ring-1 ring-amber-200">
            <div class="flex items-start gap-2">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01M10.3 3.86l-8.4 14.55A2 2 0 0 0 3.6 21h16.8a2 2 0 0 0 1.7-2.59L13.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-amber-800">سفارش‌هایی که در StoqS ثبت نشدند ({{ \App\Support\Money::toPersianDigits(count($unmatchedSales)) }})</h2>
                    <p class="mt-1 text-xs leading-6 text-amber-700">
                        این سفارش‌ها به‌خاطر نبودِ کالا در StoqS رد شده‌اند و به‌صورت خودکار دوباره تلاش می‌شوند. کالای زیر را در StoqS بسازید یا بارکد را هماهنگ کنید؛ در همگام‌سازی بعدی، فروش به‌صورت خودکار ثبت می‌شود.
                    </p>
                    <div class="mt-3 space-y-2">
                        @foreach ($unmatchedSales as $s)
                            <div class="rounded-lg bg-white/70 p-3 text-xs ring-1 ring-amber-100">
                                <div class="font-semibold text-brand-800">سفارش <span class="fa-num" dir="ltr">{{ $s['order_number'] }}</span>
                                    <span class="text-brand-400">· {{ \App\Support\Money::toPersianDigits($s['attempts']) }} تلاش</span>
                                </div>
                                <div class="mt-1 text-brand-600">{{ implode('، ', $s['items']) ?: '—' }}</div>
                                <div class="mt-1 text-amber-700/80" dir="ltr">{{ $s['error'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Outbox status --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <div class="text-xs text-brand-400">در صف ارسال (Outbox)</div>
            <div class="mt-1 font-bold fa-num {{ $status['outbox_pending'] > 0 ? 'text-amber-600' : 'text-brand-800' }}">{{ \App\Support\Money::toPersianDigits((string) $status['outbox_pending']) }}</div>
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <div class="text-xs text-brand-400">ارسال‌شده</div>
            <div class="mt-1 font-bold fa-num text-brand-800">{{ \App\Support\Money::toPersianDigits((string) $status['outbox_sent']) }}</div>
        </div>
        <div class="rounded-card bg-white p-4 ring-1 ring-brand-100">
            <div class="text-xs text-brand-400">آخرین خطا</div>
            <div class="mt-1 text-sm font-medium text-brand-700 fa-num">
                @php($lastErr = $recentLogs->first(fn($l) => $l->status === 'failed'))
                {{ $lastErr ? $lastErr->created_at->format('Y/m/d H:i') : '—' }}
            </div>
        </div>
    </div>

    {{-- Outbox detail: exactly which pushes are stuck + one-click retry-all --}}
    @if ($outbox->isNotEmpty())
        <div class="mb-6 overflow-hidden rounded-card bg-white ring-1 ring-brand-100">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-100 px-4 py-3">
                <div class="text-sm font-bold text-brand-800">در صف ارسال به StoqS — {{ \App\Support\Money::toPersianDigits((string) $outbox->count()) }} مورد</div>
                <form method="POST" action="{{ route('admin.settings.integration.retry-outbox') }}"
                      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='در حال تلاش…'">
                    @csrf
                    <button class="rounded-full bg-brand-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-brand-800">↻ تلاش مجدد برای همه</button>
                </form>
            </div>
            <div class="max-h-96 overflow-x-auto overflow-y-auto">
                <table class="w-full min-w-[34rem] text-right text-xs">
                    <thead class="bg-brand-50 text-brand-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">نوع</th>
                            <th class="px-4 py-2 font-medium">سفارش</th>
                            <th class="px-4 py-2 font-medium">وضعیت</th>
                            <th class="px-4 py-2 font-medium">تلاش</th>
                            <th class="px-4 py-2 font-medium">خطا</th>
                            <th class="px-4 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-50">
                        @foreach ($outbox as $ev)
                            @php($ord = $ev->payload['order_number'] ?? null)
                            @php($typeLabel = match ($ev->type) {
                                \App\Models\IntegrationEvent::TYPE_SALE_CREATED => 'فروش',
                                \App\Models\IntegrationEvent::TYPE_INCOME_RECORDED => 'درآمد',
                                \App\Models\IntegrationEvent::TYPE_CUSTOMER_UPSERTED => 'مشتری',
                                default => $ev->type,
                            })
                            <tr class="{{ $ev->status === 'failed' ? 'bg-red-50/40' : '' }}">
                                <td class="px-4 py-2 text-brand-700">{{ $typeLabel }}</td>
                                <td class="px-4 py-2 fa-num">
                                    @if ($ord)
                                        <a href="{{ url('/admin/orders/'.$ord) }}" class="font-medium text-accent-600 hover:underline" dir="ltr">{{ $ord }}</a>
                                    @else — @endif
                                </td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $ev->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ $ev->status === 'failed' ? 'ناموفق' : 'در صف' }}</span>
                                </td>
                                <td class="px-4 py-2 fa-num text-brand-500">{{ \App\Support\Money::toPersianDigits((string) $ev->attempts) }}</td>
                                <td class="px-4 py-2 text-red-500" dir="ltr">{{ \Illuminate\Support\Str::limit((string) $ev->last_error, 90) }}</td>
                                <td class="px-4 py-2 text-left">
                                    <form method="POST" action="{{ route('admin.settings.integration.dismiss', $ev) }}"
                                          onsubmit="return confirm('این مورد از صف حذف شود؟ دیگر برای ارسال به StoqS تلاش نمی‌شود.')">
                                        @csrf
                                        <button class="rounded-full px-3 py-1 text-[11px] font-bold text-red-600 ring-1 ring-red-200 transition hover:bg-red-50">رد کردن</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Connection + locations --}}
    <form method="POST" action="{{ route('admin.settings.integration.update') }}"
          class="max-w-3xl space-y-6 rounded-card bg-white p-6 ring-1 ring-brand-100">
        @csrf @method('PATCH')

        <label class="inline-flex items-center gap-2 text-sm font-medium text-brand-800">
            <input type="checkbox" name="enabled" value="1" @checked($cfg['enabled']) class="rounded"> اتصال فعال باشد
        </label>

        <label class="flex items-start gap-2 text-sm font-medium text-brand-800">
            <input type="checkbox" name="import_in_stock_only" value="1" @checked($cfg['import_in_stock_only']) class="mt-0.5 rounded">
            <span>
                فقط محصولات دارای موجودی وارد شوند
                <span class="block text-xs font-normal text-brand-400">هنگام وارد کردن کاتالوگ، محصولاتی که در StoqS موجودی ندارند نادیده گرفته می‌شوند.</span>
            </span>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">آدرس StoqS</label>
                <input type="text" name="base_url" value="{{ old('base_url', $cfg['base_url'] ?: 'https://stoqs.ir') }}" dir="ltr"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">کلید API @if($cfg['has_key'])<span class="text-green-600">(ذخیره شده)</span>@endif</label>
                <input type="password" name="api_key" value="" dir="ltr" placeholder="{{ $cfg['has_key'] ? 'برای تغییر، کلید جدید وارد کنید' : 'sk_live_...' }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-brand-700">کلید امضای وبهوک @if($cfg['has_secret'])<span class="text-green-600">(ذخیره شده)</span>@endif</label>
                <input type="password" name="webhook_secret" value="" dir="ltr" placeholder="{{ $cfg['has_secret'] ? 'برای تغییر وارد کنید' : 'وبهوک امضا' }}"
                       class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
            </div>
        </div>

        {{-- Location picker --}}
        <div class="border-t border-brand-100 pt-4">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="text-sm font-bold text-brand-800">انبارها و فروشگاه‌های مبدأ موجودی</h2>
            </div>
            @if (empty($locations))
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    ابتدا کلید را ذخیره کنید، سپس روی «دریافت مکان‌ها از StoqS» بزنید تا فهرست انبار/فروشگاه‌ها بیاید.
                </p>
            @else
                <p class="mb-2 text-xs text-brand-400">موجودی سایت = مجموع مکان‌های انتخاب‌شده. «مبدأ کسر موجودی» جایی است که فروش از آن کم می‌شود.</p>
                <div class="space-y-2">
                    @foreach ($locations as $token => $name)
                        <label class="flex items-center gap-3 rounded-lg border border-brand-100 px-3 py-2 text-sm">
                            <input type="checkbox" name="locations[]" value="{{ $token }}" @checked(in_array($token, $cfg['locations'], true)) class="rounded">
                            <span class="font-medium text-brand-800">{{ $name }}</span>
                            <span class="text-xs text-brand-400" dir="ltr">{{ $token }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4">
                    <label class="mb-1 block text-sm font-medium text-brand-700">مبدأ کسر موجودی (هنگام فروش)</label>
                    <select name="fulfillment_location" class="w-full max-w-sm rounded-lg border border-brand-200 px-3 py-2 text-sm">
                        <option value="">— خودکار: اولین مکان —</option>
                        @foreach ($locations as $token => $name)
                            <option value="{{ $token }}" @selected($cfg['fulfillment_location'] === $token)>{{ $name }} ({{ $token }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div class="pt-2">
            <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره تنظیمات</button>
        </div>
    </form>

    {{-- Actions --}}
    <div class="mt-6 flex max-w-3xl flex-wrap gap-3">
        <form method="POST" action="{{ route('admin.settings.integration.locations') }}">
            @csrf
            <button class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">🔄 دریافت مکان‌ها از StoqS</button>
        </form>
        <form method="POST" action="{{ route('admin.settings.integration.import') }}">
            @csrf
            <button class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">📥 وارد کردن کاتالوگ</button>
        </form>
        <form method="POST" action="{{ route('admin.settings.integration.collections') }}">
            @csrf
            <button class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">🏷️ وارد کردن مجموعه‌ها</button>
        </form>
        <form method="POST" action="{{ route('admin.settings.integration.pull') }}">
            @csrf
            <button class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">📦 همگام‌سازی موجودی</button>
        </form>
    </div>

    {{-- Sync history --}}
    @if($recentLogs->isNotEmpty())
    <div class="mt-8 max-w-4xl">
        <h2 class="mb-3 text-sm font-bold text-brand-800">تاریخچه همگام‌سازی</h2>
        <div class="overflow-x-auto rounded-card bg-white ring-1 ring-brand-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-brand-100 text-right text-xs text-brand-400">
                        <th class="px-3 py-2 font-medium">زمان</th>
                        <th class="px-3 py-2 font-medium">نوع</th>
                        <th class="px-3 py-2 font-medium">خلاصه</th>
                        <th class="px-3 py-2 font-medium">منبع</th>
                        <th class="px-3 py-2 font-medium">مدت</th>
                        <th class="px-3 py-2 font-medium">وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recentLogs as $log): ?>
                    <?php
                    $detailItems = [];
                    if ($log->event_type === 'stock_pull' && !empty($log->payload['unmatched_items'])) {
                        $detailItems = $log->payload['unmatched_items'];
                    } elseif ($log->event_type === 'catalog_import' && !empty($log->payload['failed'])) {
                        $detailItems = $log->payload['failed'];
                    }
                    $detailId = 'log-detail-'.$log->id;
                    ?>
                    <tr class="border-b border-brand-50 text-brand-700 hover:bg-brand-50/50">
                        <td class="fa-num whitespace-nowrap px-3 py-2 text-xs" title="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('Y/m/d H:i') }}</td>
                        <td class="px-3 py-2 text-xs">{{ \App\Models\StockkeeepingLog::TYPE_LABELS[$log->event_type] ?? $log->event_type }}</td>
                        <td class="px-3 py-2 text-xs">
                            {{ $log->summary ?? '—' }}
                            @if($detailItems)
                            <button onclick="document.getElementById('{{ $detailId }}').classList.toggle('hidden')" class="mr-1 text-xs text-brand-400 hover:text-brand-600">▼</button>
                            @endif
                        </td>
                        <td class="px-3 py-2">{!! $log->source ? ($log->source === 'manual' ? '<span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700">دستی</span>' : '<span class="rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-700">خودکار</span>') : '<span class="text-xs text-brand-300">—</span>' !!}</td>
                        <td class="fa-num px-3 py-2 text-xs text-brand-400">
                            @if($log->duration_ms)
                                {{ $log->duration_ms < 1000 ? $log->duration_ms.'ms' : number_format($log->duration_ms / 1000, 1).'s' }}
                            @else
                            —
                            @endif
                        </td>
                        <td class="px-3 py-2">{!! $log->status === 'ok' ? '<span class="text-green-600">✓</span>' : '<span class="text-red-600">✗</span>' !!}</td>
                    </tr>
                    <?php
                    $hasDetail = !empty($detailItems);
                    $isStock = $log->event_type === 'stock_pull' && $hasDetail;
                    $isCatalog = $log->event_type === 'catalog_import' && $hasDetail;
                    ?>
                    @if($hasDetail)
                    <tr id="{{ $detailId }}" class="hidden border-b border-brand-50">
                        <td colspan="6" class="px-6 py-3">
                            @if($isStock)
                            <div class="text-xs text-brand-500 mb-1">بارکدهای ناشناخته (در سایت یافت نشد):</div>
                            <ul class="space-y-1">
                                @foreach($detailItems as $item)
                                <li class="text-xs text-red-600 fa-num">✗ {{ $item['barcode'] }} — {{ $item['product'] }}</li>
                                @endforeach
                            </ul>
                            @endif
                            @if($isCatalog)
                            <div class="text-xs text-brand-500 mb-1">محصولات خطا در وارد کردن:</div>
                            <ul class="space-y-1">
                                @foreach($detailItems as $item)
                                <li class="text-xs text-red-600">✗ {{ $item['name'] }} — {{ $item['error'] }}</li>
                                @endforeach
                            </ul>
                            @endif
                        </td>
                    </tr>
                    @endif
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection

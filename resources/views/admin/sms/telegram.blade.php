@extends('admin.layout')

@section('title', 'ربات تلگرام')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-brand-900">پنل پیامک</h1>
        @include('admin.sms._tabs', ['active' => 'telegram'])
    </div>

    <div class="max-w-3xl space-y-6">
        {{-- Bot token + connection --}}
        <div class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-2 text-sm font-bold text-brand-800">ربات تلگرام دوطرفه</h2>
            <p class="mb-4 text-xs leading-6 text-brand-500">
                مدیران اعلان سفارش‌ها را می‌گیرند و می‌توانند وضعیت سفارش را همان‌جا تغییر دهند.
                مشتری‌ها هم می‌توانند حساب خود را وصل کنند و وضعیت سفارش‌شان را در تلگرام دریافت کنند.
            </p>

            <form method="POST" action="{{ route('admin.sms.telegram.update') }}" class="space-y-3">
                @csrf @method('PATCH')
                <div>
                    <label class="block text-sm font-medium text-brand-700">توکن ربات (BotFather)
                        @if ($hasToken)<span class="text-green-600">(ذخیره شده)</span>@endif
                    </label>
                    <input name="telegram_bot_token" value="" type="password" dir="ltr"
                           placeholder="{{ $hasToken ? 'برای تغییر، توکن جدید وارد کنید' : '123456:ABC...' }}"
                           class="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-brand-700">نام کاربری ربات (بدون @)</label>
                    <input name="telegram_bot_username" value="{{ ltrim($botUsername, '@') }}" dir="ltr"
                           placeholder="ChiiacoBot"
                           class="mt-1 w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-brand-400">برای لینک «اتصال تلگرام» مشتری لازم است. روی سرور ایران به‌صورت خودکار شناسایی نمی‌شود؛ دستی وارد کنید.</p>
                </div>

                <div class="rounded-lg bg-amber-50 p-3 ring-1 ring-amber-100">
                    <p class="mb-2 text-xs font-semibold text-amber-800">رلهٔ ارسال (برای میزبان فیلترشده در ایران)</p>
                    <p class="mb-3 text-xs leading-6 text-amber-700">
                        روی هاست ایران، <span dir="ltr">api.telegram.org</span> فیلتر است. یک اسکریپت Google Apps Script روی سرورهای گوگل اجرا کنید و آدرس <span dir="ltr">/exec</span> آن را اینجا بگذارید تا پیام‌ها از طریق آن ارسال شود. (راهنما: <span dir="ltr">google-apps-script/telegram-relay.gs</span>)
                    </p>
                    <label class="block text-xs font-medium text-amber-800">آدرس رله (URL اسکریپت)</label>
                    <input name="telegram_relay_url" value="{{ $relayUrl }}" dir="ltr"
                           placeholder="https://script.google.com/macros/s/XXXX/exec"
                           class="mt-1 w-full rounded-lg border border-amber-200 px-3 py-2 text-sm">
                    <label class="mt-2 block text-xs font-medium text-amber-800">کلید مشترک رله (SECRET)
                        @if ($hasRelaySecret)<span class="text-green-600">(ذخیره شده)</span>@endif
                    </label>
                    <input name="telegram_relay_secret" value="" type="password" dir="ltr"
                           placeholder="{{ $hasRelaySecret ? 'برای تغییر، کلید جدید وارد کنید' : 'همان مقدار SECRET در فایل .gs' }}"
                           class="mt-1 w-full rounded-lg border border-amber-200 px-3 py-2 text-sm">
                </div>

                <div class="rounded-lg bg-blue-50 p-3 ring-1 ring-blue-100">
                    <label class="block text-xs font-semibold text-blue-800">📣 شناسهٔ کانال عمومی (برای اعلان خودکار محصول جدید)</label>
                    <input name="telegram_channel_id" value="{{ $channelId }}" dir="ltr"
                           placeholder="@chiiaco_channel یا -1001234567890"
                           class="mt-1 w-full rounded-lg border border-blue-200 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs leading-6 text-blue-700">
                        ربات شما باید مدیر کانال باشد. اولین بار که محصول فعال شود (<code>is_active=true</code>)، اعلان به این کانال ارسال می‌شود. خالی بگذارید تا غیرفعال شود.
                    </p>
                </div>

                <div class="rounded-lg bg-green-50 p-3 ring-1 ring-green-100">
                    <label class="block text-xs font-semibold text-green-800">🎁 کد تخفیف خوش‌آمدگویی (روی اولین <code>/start</code> ربات ارسال می‌شود)</label>
                    <input name="telegram_welcome_coupon" value="{{ $welcomeCoupon }}" dir="ltr"
                           placeholder="WELCOME10"
                           class="mt-1 w-full rounded-lg border border-green-200 px-3 py-2 text-sm uppercase">
                    <p class="mt-1 text-xs leading-6 text-green-700">
                        ابتدا در «کدهای تخفیف» این کد را با گزینهٔ «فقط برای اولین خرید» بسازید. خالی بگذارید تا غیرفعال شود.
                    </p>
                </div>

                <button class="rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره تنظیمات</button>
            </form>

            <div class="mt-3 text-xs text-brand-500">
                وضعیت ارسال:
                @if ($relayUrl)
                    <span class="font-medium text-amber-700">از طریق رلهٔ Google</span>
                @else
                    <span class="font-medium text-brand-600">مستقیم به تلگرام</span>
                @endif
                @if ($botUsername)
                    — ربات: <a href="https://t.me/{{ ltrim($botUsername, '@') }}" target="_blank" class="font-medium text-accent-600" dir="ltr">{{ '@'.ltrim($botUsername, '@') }}</a>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap gap-3 border-t border-brand-100 pt-4">
                <form method="POST" action="{{ route('admin.sms.telegram.sync') }}">@csrf
                    <button class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">🔄 دریافت چت‌های جدید</button>
                </form>
                <form method="POST" action="{{ route('admin.sms.telegram.test') }}">@csrf
                    <button class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">✉️ پیام آزمایشی به مدیران</button>
                </form>
                <form method="POST" action="{{ route('admin.sms.telegram.webhook') }}">@csrf
                    <button class="rounded-lg border border-brand-200 px-4 py-2 text-sm text-brand-700 hover:bg-brand-50">🔗 فعال‌سازی وبهوک</button>
                </form>
            </div>
            <p class="mt-3 text-xs text-brand-400">برای کارکردن دکمه‌های وضعیت و اتصال مشتری‌ها، سایت باید روی دامنه‌ی عمومی با HTTPS باشد و یک‌بار «فعال‌سازی وبهوک» را بزنید.</p>

            @if ($webhookUrl)
                <div class="mt-4 rounded-lg bg-brand-50 p-3 ring-1 ring-brand-100">
                    <p class="mb-1 text-xs font-semibold text-brand-700">نشانی وبهوک (تنظیم مطمئن از روی سیستم خودتان)</p>
                    <p class="mb-2 text-xs leading-6 text-brand-500">
                        چون ارسال از سرور ایران از طریق رله است و تأیید وبهوک خوانده نمی‌شود، مطمئن‌ترین راه این است که وبهوک را یک‌بار از کامپیوتر خودتان (با VPN روشن) تنظیم کنید. این آدرس را کپی کنید و در دستور زیر بگذارید:
                    </p>
                    <input readonly dir="ltr" value="{{ $webhookUrl }}" onclick="this.select()"
                           class="mb-2 w-full rounded border border-brand-200 bg-white px-2 py-1 text-xs text-brand-700">
                    <p class="text-xs text-brand-400" dir="ltr">
                        # وضعیت فعلی:<br>
                        curl "https://api.telegram.org/bot&lt;TOKEN&gt;/getWebhookInfo"<br><br>
                        # تنظیم وبهوک:<br>
                        curl "https://api.telegram.org/bot&lt;TOKEN&gt;/setWebhook?url={{ $webhookUrl }}"
                    </p>
                </div>
            @endif
        </div>

        {{-- Admin event toggles --}}
        <div class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-1 text-sm font-bold text-brand-800">اعلان‌های مدیریت</h2>
            <p class="mb-3 text-xs text-brand-400">انتخاب کنید کدام رویدادها برای مدیران ارسال شود. (اعلان‌های مشتری همیشه فعال است.)</p>
            <form method="POST" action="{{ route('admin.sms.telegram.events') }}" class="space-y-2">
                @csrf
                @foreach ($events as $key => $e)
                    <label class="flex items-center gap-2 text-sm text-brand-700">
                        <input type="checkbox" name="event_{{ $key }}" value="1" @checked($e['on']) class="rounded"> {{ $e['label'] }}
                    </label>
                @endforeach
                <button class="mt-2 rounded-lg bg-brand-900 px-5 py-2 text-sm font-semibold text-white">ذخیره اعلان‌ها</button>
            </form>
        </div>

        {{-- Assigned admins --}}
        <div class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-3 text-sm font-bold text-brand-800">مدیران ربات ({{ \App\Support\Money::toPersianDigits((string) $admins->count()) }})</h2>
            @forelse ($admins as $a)
                <div class="flex items-center justify-between border-b border-brand-50 py-2 text-sm last:border-0">
                    <span class="text-brand-700">{{ $a->first_name ?? '—' }} @if($a->username)<span class="text-brand-400" dir="ltr">{{ '@'.$a->username }}</span>@endif</span>
                    <span class="flex items-center gap-3">
                        <span class="text-xs text-brand-400 fa-num" dir="ltr">{{ $a->chat_id }}</span>
                        <form method="POST" action="{{ route('admin.sms.telegram.admins.remove') }}" onsubmit="return confirm('این مدیر حذف شود؟')">
                            @csrf<input type="hidden" name="chat_id" value="{{ $a->chat_id }}">
                            <button class="text-xs text-red-500 hover:underline">حذف</button>
                        </form>
                    </span>
                </div>
            @empty
                <p class="text-sm text-brand-400">هنوز مدیری اضافه نشده است. از فهرست پایین یک چت را تأیید کنید.</p>
            @endforelse

            {{-- Manual add by numeric ID --}}
            <form method="POST" action="{{ route('admin.sms.telegram.admins.manual') }}" class="mt-4 flex items-end gap-2 border-t border-brand-100 pt-4">
                @csrf
                <div class="flex-1">
                    <label class="mb-1 block text-xs text-brand-500">افزودن مدیر با شناسه عددی تلگرام</label>
                    <input name="chat_id" dir="ltr" inputmode="numeric" placeholder="مثلاً 123456789"
                           class="w-full rounded-lg border border-brand-200 px-3 py-2 text-sm">
                </div>
                <button class="rounded-lg bg-brand-100 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-200">افزودن</button>
            </form>
            <p class="mt-2 text-xs text-brand-400">شناسه عددی را می‌توانید با ربات <span dir="ltr">@userinfobot</span> پیدا کنید. مدیر باید یک‌بار ربات شما را /start کند تا پیام دریافت کند.</p>
        </div>

        {{-- Pending chats waiting to be assigned --}}
        <div class="rounded-card bg-white p-6 ring-1 ring-brand-100">
            <h2 class="mb-1 text-sm font-bold text-brand-800">در انتظار تأیید ({{ \App\Support\Money::toPersianDigits((string) $pending->count()) }})</h2>
            <p class="mb-3 text-xs text-brand-400">کسانی که ربات را /start کرده‌اند ولی هنوز نقشی ندارند. برای دریافت اعلان مدیریتی، آن‌ها را «تأیید به‌عنوان مدیر» کنید.</p>
            @forelse ($pending as $p)
                <div class="flex items-center justify-between border-b border-brand-50 py-2 text-sm last:border-0">
                    <span class="text-brand-700">{{ $p->first_name ?? '—' }} @if($p->username)<span class="text-brand-400" dir="ltr">{{ '@'.$p->username }}</span>@endif</span>
                    <span class="flex items-center gap-3">
                        <span class="text-xs text-brand-400 fa-num" dir="ltr">{{ $p->chat_id }}</span>
                        <form method="POST" action="{{ route('admin.sms.telegram.admins.assign') }}">
                            @csrf<input type="hidden" name="chat_id" value="{{ $p->chat_id }}">
                            <button class="rounded-lg bg-accent-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-accent-700">تأیید به‌عنوان مدیر</button>
                        </form>
                    </span>
                </div>
            @empty
                <p class="text-sm text-brand-400">چتی در انتظار تأیید نیست.</p>
            @endforelse
        </div>

        {{-- Connected customers (read-only count) --}}
        <div class="rounded-card bg-white p-5 ring-1 ring-brand-100">
            <p class="text-sm text-brand-600">مشتریان متصل به تلگرام: <b class="fa-num">{{ \App\Support\Money::toPersianDigits((string) $customerCount) }}</b></p>
            <p class="mt-1 text-xs text-brand-400">مشتری‌ها از صفحه «حساب کاربری» در سایت، تلگرام خود را وصل می‌کنند.</p>
        </div>
    </div>
@endsection

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\TelegramChat;
use App\Services\Sms\SmsService;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    /** Melipayamak settings (underscored field => dotted setting key). */
    private const SETTINGS = [
        'sms_otp_body_id' => 'sms.otp_body_id',
        'sms_order_body_id' => 'sms.order_body_id',
        'sms_sender' => 'sms.sender',
        'sms_admin_phone' => 'sms.admin_phone',
    ];

    public function settings(): View
    {
        return view('admin.sms.settings', ['s' => Setting::map()]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $values = [];
        foreach (self::SETTINGS as $field => $key) {
            $values[$key] = $request->input($field);
        }

        // API key is a secret — only overwrite it when a new value is entered.
        $apiKey = $request->input('sms_api_key');
        if ($apiKey !== null && $apiKey !== '') {
            $values['sms.api_key'] = trim($apiKey);
        }

        $values['sms.enabled'] = $request->boolean('sms_enabled');
        $values['sms.notify_paid'] = $request->boolean('sms_notify_paid');
        Setting::putMany($values);

        return back()->with('success', 'تنظیمات پیامک ذخیره شد.');
    }

    public function compose(): View
    {
        return view('admin.sms.compose');
    }

    /** Fire a test SMS (OTP or an order/free pattern) through Melipayamak. */
    public function testSms(Request $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'test_phone' => ['required', 'string', 'max:40'],
            'test_type' => ['required', 'in:otp,pattern'],
            'test_body_id' => ['nullable', 'string', 'max:20'],
            'test_args' => ['nullable', 'string', 'max:300'],
        ]);

        if (! $sms->isConfigured()) {
            return back()->with('error', 'ابتدا کلید API ملی‌پیامک را وارد و ذخیره کنید.');
        }

        try {
            if ($data['test_type'] === 'otp') {
                $sms->sendOtp($data['test_phone'], (string) random_int(10000, 99999));

                return back()->with('success', 'کد آزمایشی ارسال شد. اگر پیامک رسید، تنظیمات OTP درست است.');
            }

            // Pattern: bodyId + args (args separated by ، or ;).
            $args = array_values(array_filter(array_map('trim', preg_split('/[،;]/u', (string) ($data['test_args'] ?? '')))));
            if (! $sms->pattern($data['test_phone'], (string) ($data['test_body_id'] ?? ''), $args, 'order')) {
                return back()->with('error', 'ارسال آزمایشی ناموفق بود. bodyId یا کلید API را بررسی کنید (گزارش را ببینید).');
            }

            return back()->with('success', 'پیامک پترن آزمایشی ارسال شد. اگر رسید، bodyId درست است.');
        } catch (\Throwable $e) {
            return back()->with('error', 'ارسال آزمایشی ناموفق: '.$e->getMessage());
        }
    }

    public function sendCampaign(Request $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', 'in:all,buyers,single'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:480'],
        ]);

        if ($data['audience'] === 'single') {
            if (empty($data['phone'])) {
                return back()->withInput()->with('success', 'برای ارسال تکی، شماره را وارد کنید.');
            }
            $phones = collect([$data['phone']]);
        } else {
            $phones = $sms->audience($data['audience']);
        }

        $sent = 0;
        foreach ($phones->unique() as $phone) {
            if ($sms->send($phone, $data['message'], 'campaign')) {
                $sent++;
            }
        }

        return back()->with('success', \App\Support\Money::toPersianDigits((string) $sent).' پیامک ارسال شد.');
    }

    /* ----------------------- Telegram (admin notifications) ----------------------- */

    public function telegram(TelegramNotifier $tg): View
    {
        $events = [];
        foreach (\App\Services\Telegram\TelegramNotifier::EVENTS as $key => $label) {
            $events[$key] = ['label' => $label, 'on' => (bool) Setting::get('telegram.events.'.$key, true)];
        }

        // Degrade gracefully if the table hasn't been migrated yet (don't 500).
        $hasTable = \Illuminate\Support\Facades\Schema::hasTable('telegram_chats');

        return view('admin.sms.telegram', [
            'hasToken' => (bool) config('telegram.bot_token'),
            'botUsername' => (string) config('telegram.bot_username'),
            'relayUrl' => (string) config('telegram.relay_url'),
            'hasRelaySecret' => (bool) config('telegram.relay_secret'),
            'webhookUrl' => $tg->enabled() ? route('webhooks.telegram', $tg->webhookSecret()) : '',
            'admins' => $hasTable ? TelegramChat::where('role', TelegramChat::ROLE_ADMIN)->latest()->get() : collect(),
            'pending' => $hasTable ? TelegramChat::pending()->latest()->get() : collect(),
            'customerCount' => $hasTable ? TelegramChat::customers()->count() : 0,
            'events' => $events,
            'welcomeCoupon' => (string) Setting::get('telegram.welcome_coupon', ''),
            'channelId' => (string) Setting::get('telegram.channel_id', ''),
        ]);
    }

    /** Save which admin event types broadcast to Telegram. */
    public function updateTelegramEvents(Request $request): RedirectResponse
    {
        $values = [];
        foreach (array_keys(\App\Services\Telegram\TelegramNotifier::EVENTS) as $key) {
            $values['telegram.events.'.$key] = $request->boolean('event_'.$key);
        }
        Setting::putMany($values);

        return back()->with('success', 'تنظیمات اعلان‌ها ذخیره شد.');
    }

    public function updateTelegram(Request $request, TelegramNotifier $tg): RedirectResponse
    {
        $request->validate([
            'telegram_bot_token' => ['nullable', 'string', 'max:120'],
            'telegram_bot_username' => ['nullable', 'string', 'max:64'],
            'telegram_relay_url' => ['nullable', 'string', 'max:300'],
            'telegram_relay_secret' => ['nullable', 'string', 'max:120'],
            'telegram_welcome_coupon' => ['nullable', 'string', 'max:40'],
            'telegram_channel_id' => ['nullable', 'string', 'max:64'],
        ]);

        if ($request->filled('telegram_bot_token')) {
            Setting::put('telegram.bot_token', trim($request->input('telegram_bot_token')));
            config(['telegram.bot_token' => trim($request->input('telegram_bot_token'))]);
        }

        // Relay (Iran-filtered hosts). URL is pre-filled in the form, so saving
        // whatever is there is safe; the secret is only saved when re-entered.
        if ($request->has('telegram_relay_url')) {
            $url = trim((string) $request->input('telegram_relay_url'));
            Setting::put('telegram.relay_url', $url);
            config(['telegram.relay_url' => $url]);
        }
        if ($request->filled('telegram_relay_secret')) {
            Setting::put('telegram.relay_secret', trim($request->input('telegram_relay_secret')));
            config(['telegram.relay_secret' => trim($request->input('telegram_relay_secret'))]);
        }

        // Bot @username: manual entry wins; otherwise try to auto-detect (only
        // works when Telegram is directly reachable / via proxy — not relay-only).
        if ($request->filled('telegram_bot_username')) {
            $u = ltrim(trim($request->input('telegram_bot_username')), '@');
            Setting::put('telegram.bot_username', $u);
            config(['telegram.bot_username' => $u]);
        } elseif ($request->filled('telegram_bot_token') && ! $tg->relayConfigured()) {
            $tg->detectBotUsername();
        }

        // First-order welcome coupon. Setting is a coupon CODE; admin pre-creates
        // the coupon with first_order_only=true. Blank disables the welcome gift.
        if ($request->has('telegram_welcome_coupon')) {
            Setting::put('telegram.welcome_coupon', mb_strtoupper(trim((string) $request->input('telegram_welcome_coupon'))));
        }

        // Public channel for new-product auto-broadcast. Bot must be an admin
        // of the channel; supply either @channel_username or numeric chat_id.
        if ($request->has('telegram_channel_id')) {
            Setting::put('telegram.channel_id', trim((string) $request->input('telegram_channel_id')));
        }

        return back()->with('success', 'تنظیمات ربات تلگرام ذخیره شد.');
    }

    /** Promote a pending chat (someone who /started) to admin. */
    public function assignAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate(['chat_id' => ['required', 'string', 'max:40']]);
        $chat = TelegramChat::where('chat_id', $data['chat_id'])->first();
        if (! $chat) {
            return back()->with('success', 'چت موردنظر یافت نشد.');
        }
        $chat->update(['role' => TelegramChat::ROLE_ADMIN, 'is_active' => true, 'linked_at' => now()]);

        return back()->with('success', 'مدیر «'.$chat->label().'» اضافه شد.');
    }

    /** Add an admin manually by their numeric Telegram ID. */
    public function addAdminById(Request $request): RedirectResponse
    {
        $data = $request->validate(['chat_id' => ['required', 'regex:/^\d{4,20}$/']]);
        $chat = TelegramChat::firstOrNew(['chat_id' => $data['chat_id']]);
        $chat->role = TelegramChat::ROLE_ADMIN;
        $chat->is_active = true;
        $chat->linked_at = now();
        $chat->save();

        return back()->with('success', 'مدیر با شناسه '.$data['chat_id'].' اضافه شد. (برای دریافت پیام باید یک‌بار ربات را /start کند.)');
    }

    /** Demote an admin chat back to pending (stops their alerts). */
    public function removeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate(['chat_id' => ['required', 'string', 'max:40']]);
        TelegramChat::where('chat_id', $data['chat_id'])
            ->update(['role' => TelegramChat::ROLE_PENDING]);

        return back()->with('success', 'مدیر حذف شد.');
    }

    public function syncTelegram(TelegramNotifier $tg): RedirectResponse
    {
        if (! $tg->enabled()) {
            return back()->with('success', 'ابتدا توکن ربات را ذخیره کنید.');
        }
        $added = $tg->syncSubscribers();

        return back()->with('success', $added > 0
            ? \App\Support\Money::toPersianDigits((string) $added).' کاربر جدید ربات اضافه شد.'
            : 'کاربر جدیدی یافت نشد. ابتدا در تلگرام ربات را /start کنید، سپس دوباره تلاش کنید.');
    }

    public function testTelegram(TelegramNotifier $tg): RedirectResponse
    {
        $n = $tg->broadcast('✅ پیام آزمایشی از فروشگاه چیاکو — اعلان‌های مدیریت اینجا دریافت می‌شود.');

        return back()->with('success', \App\Support\Money::toPersianDigits((string) $n).' پیام آزمایشی ارسال شد.');
    }

    public function webhookTelegram(TelegramNotifier $tg): RedirectResponse
    {
        if (! $tg->enabled()) {
            return back()->with('success', 'ابتدا توکن ربات را ذخیره کنید.');
        }
        $r = $tg->setWebhook(route('webhooks.telegram', $tg->webhookSecret()));

        return back()->with('success', ($r['ok'] ?? false)
            ? 'وبهوک تلگرام فعال شد — حالا دکمه‌های وضعیت در پیام‌ها کار می‌کنند.'
            : 'فعال‌سازی وبهوک ناموفق بود: '.($r['description'] ?? 'خطا (آدرس باید عمومی و HTTPS باشد)'));
    }

    public function log(Request $request): View
    {
        $messages = SmsMessage::when($request->string('type')->toString(), fn ($q, $t) => $q->where('type', $t))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.sms.log', ['messages' => $messages]);
    }
}

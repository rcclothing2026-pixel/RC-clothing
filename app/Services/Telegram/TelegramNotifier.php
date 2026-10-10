<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Models\Setting;
use App\Models\StockkeeepingLog;
use App\Models\TelegramChat;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Two-sided Telegram bot.
 *
 *  - ADMIN side: assigned admins receive order/ops alerts (with full details)
 *    and inline buttons to change order status straight from Telegram. Only
 *    chats assigned the admin role may act on those buttons.
 *  - CUSTOMER side: a shopper links their website account to the bot (via a
 *    one-time deep-link token or by sharing their phone) and then receives
 *    their own order updates.
 *
 * Every chat the bot knows about lives in `telegram_chats` with a role
 * (admin | customer | pending). Outbound sends are role-targeted; inbound
 * updates are routed and authorized by role in {@see handleUpdate()}.
 */
class TelegramNotifier
{
    public function token(): string
    {
        return (string) config('telegram.bot_token');
    }

    public function enabled(): bool
    {
        return $this->token() !== '';
    }

    /** Admin event types that can be toggled in the panel (key => label). */
    public const EVENTS = [
        'order_paid' => 'سفارش جدید پرداخت‌شده',
        'order_status' => 'تغییر وضعیت سفارش',
        'order_canceled' => 'لغو/بازپرداخت سفارش',
        'low_stock' => 'هشدار موجودی کم',
        'contact_message' => 'پیام جدید از فرم تماس با ما',
    ];

    /** Whether an admin event type is enabled (admin-toggleable, default on). */
    public function eventEnabled(string $key): bool
    {
        return (bool) Setting::get('telegram.events.'.$key, true);
    }

    /** Order statuses that count as a completed (paid) sale. */
    private const PAID_STATUSES = [Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED];

    /** Reply-keyboard menu labels. A tapped button arrives as a message with this text. */
    private const BTN_TODAY = '🆕 سفارش‌های امروز';
    private const BTN_PENDING = '⏳ در انتظار پردازش';
    private const BTN_SUMMARY = '📊 خلاصه فروش';
    private const BTN_FIND = '🔍 جستجوی سفارش';
    private const BTN_MY_ORDERS = '📦 سفارش‌های من';
    private const BTN_LAST_ORDER = '🚚 آخرین سفارش';
    private const BTN_LINK_STATUS = '🔗 وضعیت حساب';
    private const BTN_SUPPORT = '☎️ پشتیبانی';
    private const BTN_CONNECT = '🔗 اتصال حساب';

    /** Public @username of the bot (no @), used for customer deep links. */
    public function botUsername(): string
    {
        return ltrim((string) config('telegram.bot_username'), '@');
    }

    /** Shared secret embedded in the webhook URL (so only Telegram can post). */
    public function webhookSecret(): string
    {
        return substr(hash('sha256', $this->token()), 0, 24);
    }

    /** Whether a Google Apps Script relay is configured (Iran-filtered hosts). */
    public function relayConfigured(): bool
    {
        return (string) config('telegram.relay_url') !== '';
    }

    /**
     * Call a Telegram Bot API method. Send priority:
     *   1) direct via SOCKS/HTTP proxy if TELEGRAM_PROXY is set;
     *   2) Google Apps Script relay if configured (works from Iran);
     *   3) direct to api.telegram.org (dev / unfiltered hosts).
     *
     * @return array<string, mixed>|null
     */
    public function api(string $method, array $params): ?array
    {
        $url = "https://api.telegram.org/bot{$this->token()}/{$method}";

        // Priority 0: padeliran hub — one HTTPS POST to a known-reachable host
        // that terminates at Telegram. Bearer-auth means the hub uses this
        // bot's identity. No relay, no proxy round-trip. When configured this
        // is the ONLY path used, because the fallbacks were unreliable enough
        // that new-order alerts kept vanishing.
        if ($hub = (string) config('telegram.hub_url')) {
            return Http::asJson()
                ->connectTimeout(5)->timeout(10)
                ->retry(2, 500, throw: false)
                ->withToken($this->token())
                ->post($hub, ['method' => $method] + $params)
                ->json();
        }

        if ($proxy = (string) config('telegram.proxy')) {
            return Http::withOptions(['proxy' => $proxy])->timeout(15)->asForm()->post($url, $params)->json();
        }

        if ($this->relayConfigured()) {
            return $this->viaRelay($method, $params);
        }

        return Http::timeout(10)->asForm()->post($url, $params)->json();
    }

    /**
     * Forward a call through the Apps Script relay. The script runs doPost()
     * (which hits Telegram) and THEN 302-redirects to the blocked
     * script.googleusercontent.com — so we disable redirect-following and treat
     * a 2xx/3xx as success. The body is usually unreadable, so we synthesize
     * {ok:true} for it (response-bearing calls like getMe degrade gracefully).
     *
     * @return array<string, mixed>|null
     */
    private function viaRelay(string $method, array $params): ?array
    {
        try {
            $resp = Http::withOptions(['allow_redirects' => false])
                ->timeout(15)
                ->asJson()
                ->post((string) config('telegram.relay_url'), [
                    'secret' => (string) config('telegram.relay_secret'),
                    'method' => $method,
                    'params' => $params,
                    'bot_token' => $this->token(),
                ]);

            if ($resp->status() >= 200 && $resp->status() < 400) {
                $json = $resp->json();

                return is_array($json) ? $json : ['ok' => true];
            }
            Log::warning('[telegram] relay returned HTTP '.$resp->status());
        } catch (Throwable $e) {
            Log::warning('[telegram] relay failed', ['error' => $e->getMessage()]);
        }

        return ['ok' => false];
    }

    /** Ask Telegram for the bot's @username and remember it (called on token save). */
    public function detectBotUsername(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        $username = $this->api('getMe', [])['result']['username'] ?? null;
        if ($username) {
            Setting::put('telegram.bot_username', $username);
            config(['telegram.bot_username' => $username]);
        }

        return $username;
    }

    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        try {
            $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
            if ($replyMarkup) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }

            return (bool) ($this->api('sendMessage', $params)['ok'] ?? false);
        } catch (Throwable $e) {
            Log::warning('[telegram] send failed', ['chat' => $chatId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /* ----------------------------- Targeting ----------------------------- */

    /** Send to every assigned admin chat. Returns the number delivered. */
    public function notifyAdmins(string $text, ?array $replyMarkup = null): int
    {
        $sent = 0;
        foreach (TelegramChat::admins()->get() as $chat) {
            if ($this->sendMessage($chat->chat_id, $text, $replyMarkup)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** Back-compat alias — admin broadcasts (low-stock digest, test message). */
    public function broadcast(string $text, ?array $replyMarkup = null): int
    {
        return $this->notifyAdmins($text, $replyMarkup);
    }

    /**
     * Push a product card to the configured public Telegram channel. Returns
     * true if delivered (so the observer can stamp broadcast_at), false if
     * the channel isn't configured or the send failed.
     */
    public function broadcastNewProduct(\App\Models\Product $product): bool
    {
        $channel = trim((string) Setting::get('telegram.channel_id', ''));
        if ($channel === '' || ! $this->enabled()) {
            return false;
        }
        try {
            $image = $product->primary_image_url ?? null;
            $price = \App\Support\Money::toman((int) ($product->compare_at_price && $product->compare_at_price > $product->price
                ? $product->price
                : ($product->price ?? 0)));
            $url = url('/product/'.$product->slug);
            $lines = [];
            $lines[] = '✨ <b>محصول جدید در چیاکو</b>';
            $lines[] = '<b>'.e($product->name).'</b>';
            if ($product->summary) {
                $lines[] = e(\Illuminate\Support\Str::limit($product->summary, 220));
            }
            $lines[] = '💰 '.$price;
            $lines[] = $url;
            $caption = implode("\n\n", $lines);

            if ($image) {
                $result = $this->api('sendPhoto', [
                    'chat_id' => $channel,
                    'photo' => $image,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ]);
            } else {
                $result = $this->api('sendMessage', [
                    'chat_id' => $channel,
                    'text' => $caption,
                    'parse_mode' => 'HTML',
                ]);
            }

            return (bool) ($result['ok'] ?? false);
        } catch (Throwable $e) {
            Log::warning('[telegram] channel broadcast failed', ['product' => $product->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** The linked customer chat for an order's account, if any. */
    public function customerChatForOrder(Order $order): ?TelegramChat
    {
        if (! $order->user_id) {
            return null;
        }

        return TelegramChat::customers()->where('user_id', $order->user_id)->first();
    }

    /** Send a message to the order's linked customer (if they connected Telegram). */
    public function notifyCustomer(Order $order, string $text, ?array $replyMarkup = null): bool
    {
        $chat = $this->customerChatForOrder($order);

        return $chat ? $this->sendMessage($chat->chat_id, $text, $replyMarkup) : false;
    }

    /* --------------------------- Registration ---------------------------- */

    public function chatFor(?string $chatId): ?TelegramChat
    {
        return $chatId ? TelegramChat::where('chat_id', (string) $chatId)->first() : null;
    }

    /**
     * Remember a private chat as "pending" (started the bot but unassigned).
     * Never downgrades an existing admin/customer chat.
     */
    public function registerPendingChat(?array $chat): ?TelegramChat
    {
        if (! $chat || ($chat['type'] ?? '') !== 'private') {
            return null;
        }
        $row = TelegramChat::firstOrNew(['chat_id' => (string) $chat['id']]);
        $row->first_name = $chat['first_name'] ?? $row->first_name;
        $row->username = $chat['username'] ?? $row->username;
        if (! $row->exists) {
            $row->role = TelegramChat::ROLE_PENDING;
        }
        $row->is_active = true;
        $row->save();

        return $row;
    }

    /** Back-compat: register a chat, return true if newly added. */
    public function registerChat(?array $chat): bool
    {
        if (! $chat) {
            return false;
        }
        $existed = TelegramChat::where('chat_id', (string) ($chat['id'] ?? ''))->exists();

        return $this->registerPendingChat($chat) !== null && ! $existed;
    }

    /** Poll getUpdates and register any chats that /started the bot (→ pending). */
    public function syncSubscribers(): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $added = 0;
        foreach (($this->api('getUpdates', ['limit' => 100])['result'] ?? []) as $u) {
            if ($this->registerChat($u['message']['chat'] ?? $u['my_chat_member']['chat'] ?? null)) {
                $added++;
            }
        }

        return $added;
    }

    /* ----------------------- Customer account linking -------------------- */

    /** Create a one-time, 15-minute token for the customer connect deep link. */
    public function createLinkToken(User $user): string
    {
        $token = Str::random(40);
        Cache::put('tg:link:'.$token, $user->id, now()->addMinutes(15));

        return $token;
    }

    /** The deep link a customer taps to connect their account, or '' if unconfigured. */
    public function connectUrl(User $user): string
    {
        $username = $this->botUsername();
        if (! $username || ! $this->enabled()) {
            return '';
        }

        return 'https://t.me/'.$username.'?start='.$this->createLinkToken($user);
    }

    /** Link a chat to the account encoded in a connect token. */
    public function linkByToken(string $token, array $chat): ?User
    {
        $userId = Cache::pull('tg:link:'.$token);
        if (! $userId) {
            return null;
        }
        $user = User::find($userId);

        return $user ? $this->attachCustomer($user, $chat) : null;
    }

    /** Link a chat to the account matching a shared phone number. */
    public function linkByPhone(string $phone, array $chat): ?User
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen((string) $digits) < 10) {
            return null;
        }
        $local = '0'.substr($digits, -10);
        $user = User::where('phone', $local)->first();

        return $user ? $this->attachCustomer($user, $chat) : null;
    }

    /** Bind a chat to a user as their (single) customer chat. */
    private function attachCustomer(User $user, array $chat): User
    {
        // A user has at most one active customer chat — detach any others.
        TelegramChat::customers()->where('user_id', $user->id)
            ->where('chat_id', '!=', (string) $chat['id'])
            ->update(['user_id' => null, 'role' => TelegramChat::ROLE_PENDING]);

        $row = TelegramChat::firstOrNew(['chat_id' => (string) $chat['id']]);
        $row->first_name = $chat['first_name'] ?? $row->first_name;
        $row->username = $chat['username'] ?? $row->username;
        $row->user_id = $user->id;
        $row->role = TelegramChat::ROLE_CUSTOMER;
        $row->is_active = true;
        $row->linked_at = now();
        $row->save();

        return $user;
    }

    /** Remove a customer's Telegram link (from the account page). */
    public function unlinkCustomer(User $user): void
    {
        TelegramChat::where('user_id', $user->id)
            ->where('role', TelegramChat::ROLE_CUSTOMER)
            ->update(['user_id' => null, 'role' => TelegramChat::ROLE_PENDING, 'is_active' => false]);
    }

    /* ----------------------------- Webhook ------------------------------- */

    public function setWebhook(string $url): array
    {
        return $this->api('setWebhook', ['url' => $url, 'allowed_updates' => json_encode(['message', 'callback_query'])]) ?? [];
    }

    public function deleteWebhook(): array
    {
        return $this->api('deleteWebhook', []) ?? [];
    }

    /**
     * Handle an incoming Telegram update (webhook): /start (with optional
     * connect token), shared-contact linking, and the admin status-button
     * callbacks. Admin actions are authorized against the chat's role.
     */
    public function handleUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);

            return;
        }
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
        }
    }

    /** Route an inbound message: contact share, /start link token, or /start. */
    private function handleMessage(array $message): void
    {
        $chat = $message['chat'] ?? null;
        if (! $chat || ($chat['type'] ?? '') !== 'private') {
            return;
        }

        // Customer shared their phone → match an account and link.
        if (! empty($message['contact']['phone_number'])) {
            $user = $this->linkByPhone((string) $message['contact']['phone_number'], $chat);
            $row = $this->chatFor((string) $chat['id']);
            $this->sendMessage((string) $chat['id'], $user
                ? '✅ حساب شما با موفقیت متصل شد. از این پس وضعیت سفارش‌هایتان همین‌جا اطلاع‌رسانی می‌شود.'
                : 'شماره‌ای مطابق با حساب فروشگاه پیدا نشد. لطفاً از همان شماره‌ای که در سایت ثبت‌نام کرده‌اید استفاده کنید.',
                $this->mainMenu($row));

            return;
        }

        $text = trim((string) ($message['text'] ?? ''));

        // Admin reply mode: if this admin just pressed «✍️ پاسخ» on a support
        // alert, their next message is forwarded into that customer's chat. The
        // cache target survives 5 minutes; clearing it releases the per-message
        // lock so another admin can pick up the next round.
        $chatRow = $this->chatFor((string) $chat['id']);
        if ($chatRow && $chatRow->isAdmin() && $text !== '' && ! str_starts_with($text, '/')) {
            $targetKey = 'tg_reply_target:'.$chat['id'];
            $messageId = Cache::pull($targetKey);
            if ($messageId && ($parent = \App\Models\ContactMessage::find((int) $messageId))) {
                Cache::forget('tg_reply_lock:'.$parent->id);
                $adminUserId = optional($chatRow->user_id ? User::find($chatRow->user_id) : null)?->id;
                $ok = $this->sendSupportReply($parent, $text, $adminUserId);
                $this->sendMessage((string) $chat['id'], $ok
                    ? '✅ پاسخ شما برای مشتری ارسال شد.'
                    : '⚠️ ارسال پاسخ ناموفق بود (شناسهٔ مشتری در دسترس نیست).');

                return;
            }
        }

        // /start <payload> — three payload shapes:
        //   · 32-char link-token → connect the customer's account
        //   · contact / account / help → open the support conversation
        //   · order_<NUMBER> → support for a specific order (admin alert is
        //     labelled by order number)
        if (str_starts_with($text, '/start')) {
            $param = trim(substr($text, strlen('/start')));
            if ($param !== '' && $this->linkByToken($param, $chat)) {
                $row = $this->chatFor((string) $chat['id']);
                $this->sendMessage((string) $chat['id'], '✅ حساب شما متصل شد. وضعیت سفارش‌هایتان همین‌جا اطلاع‌رسانی می‌شود.', $this->mainMenu($row));

                return;
            }
            // Support-flow deep links. We remember the order context for 30 min
            // so the *next* free-text message can include it on the admin alert.
            if (in_array($param, ['contact', 'account', 'help'], true) || str_starts_with($param, 'order_')) {
                $row = $this->registerPendingChat($chat);
                $orderNumber = str_starts_with($param, 'order_') ? substr($param, 6) : null;
                if ($orderNumber !== null) {
                    Cache::put('tg_support_order:'.$chat['id'], $orderNumber, now()->addMinutes(30));
                    $this->sendMessage((string) $chat['id'], "👋 درباره سفارش <b>".e($orderNumber)."</b> چطور می‌توانم کمک کنم؟\nپیام، عکس یا صوت خود را همین‌جا بفرستید.");
                } else {
                    $this->sendMessage((string) $chat['id'], '👋 سلام! پیام خود را بفرستید تا تیم پشتیبانی چیاکو به‌زودی پاسخ بدهد.');
                }

                return;
            }
            $row = $this->registerPendingChat($chat);
            $isFirstStart = $row && $row->wasRecentlyCreated;
            $this->sendMessage((string) $chat['id'], $this->welcomeText($row), $this->mainMenu($row));
            if ($isFirstStart) {
                $welcomeCoupon = $this->welcomeCouponMessage();
                if ($welcomeCoupon !== null) {
                    $this->sendMessage((string) $chat['id'], $welcomeCoupon);
                }
            }

            return;
        }

        // Otherwise: a tapped menu button or free text → route to a pull action.
        $row = $this->chatFor((string) $chat['id']) ?? $this->registerPendingChat($chat);
        $this->routeMenu($row, $chat, $text);
    }

    /** Welcome / status text shown on /start, tailored to the chat's role. */
    private function welcomeText(?TelegramChat $chat): string
    {
        return match ($chat?->role) {
            TelegramChat::ROLE_ADMIN => '✅ شما به‌عنوان مدیر فروشگاه چیاکو ثبت شده‌اید. اعلان سفارش‌ها همین‌جا ارسال می‌شود.',
            TelegramChat::ROLE_CUSTOMER => '✅ حساب شما متصل است. وضعیت سفارش‌هایتان همین‌جا اطلاع‌رسانی می‌شود.',
            default => "👋 به ربات فروشگاه چیاکو خوش آمدید.\n\nبرای دریافت وضعیت سفارش‌ها از دکمهٔ «🔗 اتصال حساب» پایین استفاده کنید (اشتراک‌گذاری شماره) یا در حساب کاربری سایت دکمهٔ «اتصال تلگرام» را بزنید.\n\nاگر شما مدیر هستید، از مدیر اصلی بخواهید شما را در پنل تأیید کند.",
        };
    }

    /**
     * Welcome-coupon message for first /start. Returns null when no code is
     * configured, the coupon doesn't exist, isn't active, or has expired —
     * the bot stays silent on the gift rather than promising a broken code.
     */
    private function welcomeCouponMessage(): ?string
    {
        $code = mb_strtoupper(trim((string) Setting::get('telegram.welcome_coupon', '')));
        if ($code === '') {
            return null;
        }
        $coupon = \App\Models\Coupon::findByCode($code);
        if (! $coupon || ! $coupon->is_active) {
            return null;
        }
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return null;
        }

        $valueLabel = match ($coupon->type) {
            \App\Models\Coupon::TYPE_PERCENT => $coupon->value.'٪ تخفیف',
            \App\Models\Coupon::TYPE_FIXED => \App\Support\Money::toman((int) $coupon->value).' تخفیف',
            \App\Models\Coupon::TYPE_FREE_SHIPPING => 'ارسال رایگان',
            default => 'تخفیف ویژه',
        };

        return "🎁 <b>هدیهٔ خوش‌آمد</b>\n\nبه چیاکو خوش آمدید! کد زیر را در سبد خرید وارد کنید تا روی اولین سفارش‌تان <b>".e($valueLabel)."</b> دریافت کنید:\n\n<code>".e($coupon->code)."</code>";
    }

    /** Route an inbound callback (button press) with role authorization. */
    private function handleCallback(array $cb): void
    {
        $data = (string) ($cb['data'] ?? '');
        $chatId = $cb['message']['chat']['id'] ?? null;
        $messageId = $cb['message']['message_id'] ?? null;

        // Admin status changes — assigned admins only.
        if (preg_match('/^st:(processing|shipped|delivered|canceled):(\d+)$/', $data, $m)) {
            $chat = $this->chatFor($chatId !== null ? (string) $chatId : null);
            if (! $chat || ! $chat->isAdmin()) {
                $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'شما اجازه‌ی تغییر وضعیت سفارش را ندارید.', 'show_alert' => true]);

                return;
            }
            $this->applyStatusCallback($cb, $m[1], (int) $m[2], $chatId, $messageId);

            return;
        }

        // Customer self-service — only the order's linked customer.
        if (preg_match('/^cust:(received|cancelreq):(\d+)$/', $data, $m)) {
            $this->handleCustomerCallback($cb, $m[1], (int) $m[2], $chatId, $messageId);

            return;
        }

        // Admin decision on a customer's cancellation request — admins only.
        if (preg_match('/^adm:(cancelok|cancelno):(\d+)$/', $data, $m)) {
            $chat = $this->chatFor($chatId !== null ? (string) $chatId : null);
            if (! $chat || ! $chat->isAdmin()) {
                $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'اجازه ندارید.', 'show_alert' => true]);

                return;
            }
            $this->handleAdminCancelDecision($cb, $m[1], (int) $m[2], $chatId, $messageId);

            return;
        }

        // Admin claims a support message to reply to — locks for 5 min so two
        // admins don't double-respond; their next free-text message will be
        // forwarded to the customer's chat (see admin-reply branch in
        // handleMessage above).
        if (preg_match('/^reply:(\d+)$/', $data, $m)) {
            $chat = $this->chatFor($chatId !== null ? (string) $chatId : null);
            if (! $chat || ! $chat->isAdmin()) {
                $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'فقط مدیران می‌توانند پاسخ دهند.', 'show_alert' => true]);

                return;
            }
            $messageId = (int) $m[1];
            $parent = \App\Models\ContactMessage::find($messageId);
            if (! $parent || ! $parent->telegram_chat_id) {
                $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'پیام یافت نشد یا قابل پاسخ‌دهی نیست.', 'show_alert' => true]);

                return;
            }
            $lockKey = 'tg_reply_lock:'.$messageId;
            $existing = Cache::get($lockKey);
            if ($existing && (string) $existing !== (string) $chatId) {
                $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'یک مدیر دیگر در حال پاسخ به این پیام است.', 'show_alert' => true]);

                return;
            }
            Cache::put($lockKey, (string) $chatId, now()->addMinutes(5));
            Cache::put('tg_reply_target:'.$chatId, $messageId, now()->addMinutes(5));
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
            $this->sendMessage((string) $chatId, "✍️ <b>پاسخ شما به «".e($parent->senderLabel())."»</b>\n\nپاسخ خود را همین‌جا تایپ و ارسال کنید. (تا ۵ دقیقه فرصت دارید.)");

            return;
        }

        // Admin opens an order's full detail from a pulled list.
        if (preg_match('/^adm:view:(\d+)$/', $data, $m)) {
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
            $chat = $this->chatFor($chatId !== null ? (string) $chatId : null);
            if ($chat && $chat->isAdmin() && ($order = Order::find((int) $m[1]))) {
                $this->sendMessage((string) $chatId, $this->orderSummary($order->loadMissing('items'), '🛒 <b>سفارش</b>'), $this->statusKeyboard($order));
            }

            return;
        }

        // Customer opens their own order's detail from their list.
        if (preg_match('/^cust:view:(\d+)$/', $data, $m)) {
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
            $chat = $this->chatFor($chatId !== null ? (string) $chatId : null);
            $order = Order::find((int) $m[1]);
            if ($order && $chat && $chat->role === TelegramChat::ROLE_CUSTOMER && (int) $chat->user_id === (int) $order->user_id) {
                $this->sendMessage((string) $chatId, $this->customerOrderText($order->loadMissing('items'), '🧾 <b>سفارش شما</b>'), $this->customerKeyboard($order));
            }

            return;
        }

        // Anything else — acknowledge silently.
        $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id']]);
    }

    /* --------------------------- Pull / menu ----------------------------- */

    /**
     * Role-aware persistent reply keyboard — the bottom menu that replaces typed
     * /commands. Admins get ops pulls, customers get their own orders, everyone
     * else gets a one-tap "share phone to connect" button.
     */
    private function mainMenu(?TelegramChat $chat): array
    {
        if ($chat?->isAdmin()) {
            $rows = [[self::BTN_TODAY, self::BTN_PENDING], [self::BTN_SUMMARY, self::BTN_FIND]];
        } elseif ($chat?->role === TelegramChat::ROLE_CUSTOMER) {
            $rows = [[self::BTN_MY_ORDERS, self::BTN_LAST_ORDER], [self::BTN_LINK_STATUS, self::BTN_SUPPORT]];
        } else {
            return ['keyboard' => [
                [['text' => self::BTN_CONNECT, 'request_contact' => true]],
                [['text' => self::BTN_SUPPORT]],
            ], 'resize_keyboard' => true];
        }

        return [
            'keyboard' => array_map(fn ($r) => array_map(fn ($t) => ['text' => $t], $r), $rows),
            'resize_keyboard' => true,
        ];
    }

    /** Route a tapped menu button (or free text) to the matching pull action. */
    private function routeMenu(?TelegramChat $chat, array $tgChat, string $text): void
    {
        $chatId = (string) $tgChat['id'];

        if ($text === self::BTN_SUPPORT) {
            $this->sendSupport($chatId);

            return;
        }

        if ($chat?->isAdmin()) {
            if ($text === self::BTN_TODAY) {
                $this->adminTodayOrders($chatId);
            } elseif ($text === self::BTN_PENDING) {
                $this->adminPendingOrders($chatId);
            } elseif ($text === self::BTN_SUMMARY) {
                $this->adminSalesSummary($chatId);
            } elseif ($text === self::BTN_FIND) {
                $this->sendMessage($chatId, '🔍 شمارهٔ سفارش را بفرستید تا جزئیاتش را ببینید.');
            } else {
                $this->adminFindOrder($chatId, $text); // free text → order-number lookup
            }

            return;
        }

        if ($chat?->role === TelegramChat::ROLE_CUSTOMER) {
            if ($text === self::BTN_MY_ORDERS) {
                $this->customerOrders($chat, $chatId);
            } elseif ($text === self::BTN_LAST_ORDER) {
                $this->customerLastOrder($chat, $chatId);
            } elseif ($text === self::BTN_LINK_STATUS) {
                $this->sendMessage($chatId, '🔗 حساب شما به فروشگاه چیاکو متصل است. سفارش‌هایتان همین‌جا قابل پیگیری است.', $this->mainMenu($chat));
            } else {
                // Free text from a logged-in customer = a support message.
                $this->captureSupportMessage($chat, $tgChat, $text);
            }

            return;
        }

        // Pending / unlinked: any non-empty free text is treated as a support
        // message. Empty payload (e.g. shared a photo only) gets the welcome.
        if ($text !== '') {
            $this->captureSupportMessage($chat, $tgChat, $text);

            return;
        }
        $this->sendMessage($chatId, $this->welcomeText($chat), $this->mainMenu($chat));
    }

    /**
     * Save an inbound Telegram message as a ContactMessage, ack the sender, and
     * alert admins (with an inline «✍️ پاسخ» button). The thread key is the
     * chat_id so subsequent messages and replies group automatically. If the
     * /start payload was order_<NUMBER>, the order number is prepended to the
     * message body so the admin alert + inbox show which order it's about.
     */
    private function captureSupportMessage(?TelegramChat $chat, array $tgChat, string $text): void
    {
        $chatId = (string) $tgChat['id'];

        // Best-effort identity. Prefer the linked account's name/phone so admin
        // replies can fall back to SMS if the chat is later deleted.
        $user = $chat?->user_id ? User::find($chat->user_id) : null;
        $name = $user?->name ?: trim(((string) ($tgChat['first_name'] ?? '')).' '.((string) ($tgChat['last_name'] ?? ''))) ?: ('Telegram #'.$chatId);

        // Order context set by /start order_<NUMBER> — applies once then clears,
        // so subsequent free-text messages don't keep tagging the same order.
        $orderKey = 'tg_support_order:'.$chatId;
        $orderNumber = Cache::pull($orderKey);
        $body = $orderNumber ? "(سفارش {$orderNumber})\n".$text : $text;

        $message = \App\Models\ContactMessage::create([
            'name' => $name,
            'phone' => $user?->phone,
            'email' => $user?->email,
            'message' => $body,
            'is_read' => false,
            'telegram_chat_id' => $chatId,
            'source' => \App\Models\ContactMessage::SOURCE_TELEGRAM,
            'direction' => \App\Models\ContactMessage::DIR_IN,
        ]);

        $this->sendMessage($chatId, '🙏 پیامتان دریافت شد. تیم پشتیبانی چیاکو در اولین فرصت پاسخ می‌دهد.');

        try {
            $this->notifyNewContact($message);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function adminTodayOrders(string $chatId): void
    {
        $orders = Order::whereIn('status', self::PAID_STATUSES)
            ->whereDate('paid_at', today())->latest('paid_at')->limit(15)->get();
        $this->sendOrderList($chatId, '🆕 <b>سفارش‌های امروز</b>', $orders, 'adm:view');
    }

    private function adminPendingOrders(string $chatId): void
    {
        $orders = Order::where('status', Order::STATUS_PAID)->latest('paid_at')->limit(15)->get();
        $this->sendOrderList($chatId, '⏳ <b>در انتظار پردازش</b>', $orders, 'adm:view');
    }

    private function adminSalesSummary(string $chatId): void
    {
        $base = fn () => Order::whereIn('status', self::PAID_STATUSES);
        $todayCount = (clone $base())->whereDate('paid_at', today())->count();
        $todaySum = (int) (clone $base())->whereDate('paid_at', today())->sum('total');
        $monthCount = (clone $base())->where('paid_at', '>=', now()->startOfMonth())->count();
        $monthSum = (int) (clone $base())->where('paid_at', '>=', now()->startOfMonth())->sum('total');
        $pending = Order::where('status', Order::STATUS_PAID)->count();

        $this->sendMessage($chatId, implode("\n", [
            '📊 <b>خلاصه فروش</b>',
            '',
            'امروز: '.Money::toPersianDigits((string) $todayCount).' سفارش — '.Money::toPersianDigits(number_format($todaySum)).' تومان',
            'این ماه: '.Money::toPersianDigits((string) $monthCount).' سفارش — '.Money::toPersianDigits(number_format($monthSum)).' تومان',
            'در انتظار پردازش: '.Money::toPersianDigits((string) $pending).' سفارش',
        ]));
    }

    private function adminFindOrder(string $chatId, string $text): void
    {
        $order = Order::where('number', trim($text))->first();
        if (! $order) {
            $this->sendMessage($chatId, 'سفارشی با شمارهٔ «'.$text.'» پیدا نشد. شمارهٔ دقیق سفارش را بفرستید یا از منوی پایین استفاده کنید.');

            return;
        }
        $this->sendMessage($chatId, $this->orderSummary($order->loadMissing('items'), '🛒 <b>سفارش</b>'), $this->statusKeyboard($order));
    }

    private function customerOrders(TelegramChat $chat, string $chatId): void
    {
        $orders = Order::where('user_id', $chat->user_id)->latest()->limit(5)->get();
        $this->sendOrderList($chatId, '📦 <b>سفارش‌های شما</b>', $orders, 'cust:view');
    }

    private function customerLastOrder(TelegramChat $chat, string $chatId): void
    {
        $order = Order::where('user_id', $chat->user_id)->latest()->first();
        if (! $order) {
            $this->sendMessage($chatId, 'هنوز سفارشی ثبت نکرده‌اید.');

            return;
        }
        $this->sendMessage($chatId, $this->customerOrderText($order->loadMissing('items'), '🚚 <b>آخرین سفارش شما</b>'), $this->customerKeyboard($order));
    }

    private function sendSupport(string $chatId): void
    {
        // The customer tapped «☎️ پشتیبانی» from the menu. The next free-text
        // message they send gets captured as a support ticket (handled in
        // routeMenu → captureSupportMessage), so the right thing here is to
        // invite them to type — not show a static phone list. Phone/website
        // are still shown as a fallback for anyone who really wants to call.
        $phone = (string) (Setting::get('site.support_phone') ?: Setting::get('sms.admin_phone'));
        $lines = ['📩 <b>پشتیبانی چیاکو</b>', '', 'پیام، عکس یا صوت خود را همین‌جا بفرستید — تیم پشتیبانی در اولین فرصت پاسخ می‌دهد.'];
        if ($phone !== '') {
            $lines[] = '';
            $lines[] = '<i>یا تماس بگیرید: '.$phone.'</i>';
        }
        $this->sendMessage($chatId, implode("\n", $lines));
    }

    /** Send a compact order list with a "view detail" inline button per row. */
    private function sendOrderList(string $chatId, string $heading, $orders, string $cbPrefix): void
    {
        if ($orders->isEmpty()) {
            $this->sendMessage($chatId, $heading."\n\nموردی یافت نشد.");

            return;
        }
        $lines = [$heading, ''];
        $rows = [];
        foreach ($orders as $o) {
            $lines[] = '• '.$o->number.' — '.$o->statusLabel().' — '.Money::toPersianDigits(number_format($o->total)).' تومان';
            $rows[] = [['text' => '🧾 '.$o->number, 'callback_data' => $cbPrefix.':'.$o->id]];
        }
        $this->sendMessage($chatId, implode("\n", $lines), ['inline_keyboard' => $rows]);
    }

    /** Handle a customer self-service button (confirm receipt, request cancel). */
    private function handleCustomerCallback(array $cb, string $action, int $orderId, mixed $chatId, mixed $messageId): void
    {
        $order = Order::find($orderId);
        $chat = $this->chatFor($chatId !== null ? (string) $chatId : null);
        if (! $order || ! $chat || $chat->role !== TelegramChat::ROLE_CUSTOMER || (int) $chat->user_id !== (int) $order->user_id) {
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'این سفارش متعلق به شما نیست.', 'show_alert' => true]);

            return;
        }

        if ($action === 'received') {
            if ($order->status !== Order::STATUS_SHIPPED) {
                $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'این سفارش در وضعیت ارسال‌شده نیست.']);

                return;
            }
            $order->update(['status' => Order::STATUS_DELIVERED]);
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'ممنون! دریافت سفارش ثبت شد.']);
            $this->editCustomerMessage($order, $chatId, $messageId, '✔️ <b>سفارش تحویل شد</b>');
            $this->notifyAdmins($this->orderSummary($order->fresh('items'), '✔️ <b>مشتری دریافت سفارش را تأیید کرد</b>'));

            return;
        }

        // cancelreq — forward to admins for approval, don't cancel directly.
        if (! in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_PROCESSING], true)) {
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'این سفارش دیگر قابل لغو نیست.', 'show_alert' => true]);

            return;
        }
        $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'درخواست لغو شما برای بررسی ارسال شد.', 'show_alert' => true]);
        $this->notifyAdmins($this->orderSummary($order->fresh('items'), '⚠️ <b>درخواست لغو از مشتری</b>'), $this->cancelDecisionKeyboard($order));
    }

    /** Admin approves/declines a customer cancellation request. */
    private function handleAdminCancelDecision(array $cb, string $action, int $orderId, mixed $chatId, mixed $messageId): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'سفارش یافت نشد']);

            return;
        }

        if ($action === 'cancelok') {
            $wasPaid = $order->isPaid();
            $order->update(['status' => Order::STATUS_CANCELED]);
            if ($wasPaid) {
                try {
                    $this->restockCancelled($order);
                } catch (Throwable $e) {
                    report($e);
                }
            }
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'سفارش لغو شد.']);
            if ($chatId && $messageId) {
                $this->api('editMessageText', [
                    'chat_id' => $chatId, 'message_id' => $messageId, 'parse_mode' => 'HTML',
                    'text' => $this->orderSummary($order->fresh('items'), '❌ <b>سفارش لغو شد (تأیید درخواست مشتری)</b>'),
                ]);
            }
            try {
                $this->notifyCustomerStatus($order->fresh());
            } catch (Throwable $e) {
                report($e);
            }

            return;
        }

        // cancelno — decline, keep the order and tell the customer.
        $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'درخواست لغو رد شد.']);
        if ($chatId && $messageId) {
            $this->api('editMessageText', [
                'chat_id' => $chatId, 'message_id' => $messageId, 'parse_mode' => 'HTML',
                'text' => $this->orderSummary($order->fresh('items'), 'ℹ️ <b>درخواست لغو مشتری رد شد</b>'),
                'reply_markup' => json_encode($this->statusKeyboard($order)),
            ]);
        }
        try {
            $this->notifyCustomer($order, '⛔️ درخواست لغو شما بررسی شد، اما سفارش در حال پردازش است و لغو نشد. برای سؤال بیشتر با پشتیبانی در تماس باشید.', $this->customerKeyboard($order));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Edit the customer's message in place after a self-service action. */
    private function editCustomerMessage(Order $order, mixed $chatId, mixed $messageId, string $heading): void
    {
        if (! $chatId || ! $messageId) {
            return;
        }
        $this->api('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'parse_mode' => 'HTML',
            'text' => $this->customerOrderText($order->fresh('items'), $heading),
            'reply_markup' => json_encode($this->customerKeyboard($order)),
        ]);
    }

    /** Apply an authorized admin status-change button. */
    private function applyStatusCallback(array $cb, string $status, int $orderId, mixed $chatId, mixed $messageId): void
    {
        $order = Order::find($orderId);
        if (! $order) {
            $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'سفارش یافت نشد']);

            return;
        }

        $wasPaid = $order->isPaid();
        $order->update(['status' => $status]);

        // Fire the customer "shipped" SMS, same as the admin page does.
        if ($status === Order::STATUS_SHIPPED) {
            try {
                app(SmsService::class)->notifyOrderShipped($order);
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Cancelling a paid order returns stock locally + to StoqS.
        if ($status === Order::STATUS_CANCELED && $wasPaid) {
            try {
                $this->restockCancelled($order);
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Keep the customer informed of the change too.
        try {
            $this->notifyCustomerStatus($order->fresh());
        } catch (Throwable $e) {
            report($e);
        }

        $this->api('answerCallbackQuery', ['callback_query_id' => $cb['id'], 'text' => 'وضعیت به «'.$order->statusLabel().'» تغییر کرد']);
        if ($chatId && $messageId) {
            $this->api('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'parse_mode' => 'HTML',
                'text' => $this->orderSummary($order->fresh('items'), '🛒 <b>سفارش</b>'),
                'reply_markup' => json_encode($this->statusKeyboard($order)),
            ]);
        }
    }

    /* ----------------------- Customer notifications ---------------------- */

    /** Concise, customer-facing order summary. */
    public function customerOrderText(Order $order, string $heading): string
    {
        $order->loadMissing('items');
        $count = (int) $order->items->sum('quantity');

        return implode("\n", [
            $heading,
            '',
            'کد سفارش: '.$order->number,
            'تعداد اقلام: '.Money::toPersianDigits((string) $count),
            'مبلغ کل: '.Money::toPersianDigits(number_format($order->total)).' تومان',
            'وضعیت: <b>'.$order->statusLabel().'</b>',
        ]);
    }

    /** Buttons shown to the customer, depending on order status. */
    private function customerKeyboard(Order $order): array
    {
        $rows = [[['text' => '🧾 مشاهده سفارش', 'url' => route('account.orders.show', $order)]]];

        if ($order->status === Order::STATUS_SHIPPED) {
            $rows[] = [['text' => '✔️ تحویل گرفتم', 'callback_data' => 'cust:received:'.$order->id]];
        } elseif (in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_PROCESSING], true)) {
            $rows[] = [['text' => '❌ درخواست لغو سفارش', 'callback_data' => 'cust:cancelreq:'.$order->id]];
        }

        return ['inline_keyboard' => $rows];
    }

    /** Approve/decline keyboard sent to admins for a customer cancel request. */
    private function cancelDecisionKeyboard(Order $order): array
    {
        return ['inline_keyboard' => [[
            ['text' => '✅ تأیید لغو', 'callback_data' => 'adm:cancelok:'.$order->id],
            ['text' => '✖️ رد درخواست', 'callback_data' => 'adm:cancelno:'.$order->id],
        ]]];
    }

    /** Order-confirmation message to the customer (on payment). */
    public function notifyCustomerOrderConfirmed(Order $order): void
    {
        $text = $this->customerOrderText($order, '✅ <b>سفارش شما ثبت شد</b>')
            ."\n\nبا تشکر از خرید شما 🌸 وضعیت سفارش را همین‌جا اطلاع می‌دهیم.";
        $this->notifyCustomer($order, $text, $this->customerKeyboard($order));
    }

    /** Status-change message to the customer. */
    public function notifyCustomerStatus(Order $order): void
    {
        $heading = match ($order->status) {
            Order::STATUS_PROCESSING => '📦 <b>سفارش در حال آماده‌سازی</b>',
            Order::STATUS_SHIPPED => '🚚 <b>سفارش ارسال شد</b>',
            Order::STATUS_DELIVERED => '✔️ <b>سفارش تحویل شد</b>',
            Order::STATUS_CANCELED => '❌ <b>سفارش لغو شد</b>',
            default => '🔄 <b>به‌روزرسانی سفارش</b>',
        };
        $note = match ($order->status) {
            Order::STATUS_SHIPPED => "\n\nسفارش شما ارسال شد و به‌زودی به دستتان می‌رسد.",
            Order::STATUS_DELIVERED => "\n\nممنون که از چیاکو خرید کردید 🌸",
            Order::STATUS_CANCELED => "\n\nدر صورت پرداخت، مبلغ طبق روال بازگردانده می‌شود.",
            default => '',
        };
        $this->notifyCustomer($order, $this->customerOrderText($order, $heading).$note, $this->customerKeyboard($order));
    }

    /* --------------------------- Order alerts ---------------------------- */

    /** Full admin alert for a paid order, with status buttons. */
    public function notifyOrderPaid(Order $order): void
    {
        if (! $this->eventEnabled('order_paid')) {
            return;
        }
        $order->loadMissing('items');
        $this->notifyAdmins($this->orderSummary($order, '🛒 <b>سفارش جدید پرداخت‌شده</b>'), $this->statusKeyboard($order));
    }

    /**
     * Admin alert for a new inbound support message — from the contact form OR
     * from a Telegram conversation. All user input is HTML-escaped so a hostile
     * body can't smuggle Telegram markup. Telegram-sourced messages carry a
     * «✍️ پاسخ» inline button that initiates the in-Telegram reply flow.
     */
    public function notifyNewContact(\App\Models\ContactMessage $message): void
    {
        if (! $this->eventEnabled('contact_message')) {
            return;
        }
        $heading = $message->source === \App\Models\ContactMessage::SOURCE_TELEGRAM
            ? '📩 <b>پیام جدید پشتیبانی (تلگرام)</b>'
            : '📩 <b>پیام جدید از فرم تماس با ما</b>';

        $lines = [$heading, ''];
        $lines[] = '<b>از:</b> '.e($message->senderLabel());
        if ($message->phone) {
            $lines[] = '<b>تلفن:</b> <code>'.e($message->phone).'</code>';
        }
        if ($message->email) {
            $lines[] = '<b>ایمیل:</b> <code>'.e($message->email).'</code>';
        }
        $lines[] = '';
        $lines[] = '<b>پیام:</b>';
        $lines[] = e($message->message);

        // Inline reply keyboard is only useful for Telegram-sourced messages
        // (we have the customer's chat_id to forward the reply into).
        $markup = null;
        if ($message->source === \App\Models\ContactMessage::SOURCE_TELEGRAM && $message->telegram_chat_id) {
            $markup = ['inline_keyboard' => [[
                ['text' => '✍️ پاسخ', 'callback_data' => 'reply:'.$message->id],
            ]]];
        }

        $this->notifyAdmins(implode("\n", $lines), $markup);
    }

    /**
     * Forward an admin-typed reply into the customer's Telegram chat and log
     * the outbound message in ContactMessage. Returns true on send success.
     */
    public function sendSupportReply(\App\Models\ContactMessage $parent, string $reply, ?int $adminUserId = null): bool
    {
        $chatId = (string) ($parent->telegram_chat_id ?? '');
        if ($chatId === '' || trim($reply) === '') {
            return false;
        }
        $ok = $this->sendMessage($chatId, "💬 <b>پاسخ پشتیبانی چیاکو:</b>\n\n".e($reply));
        if (! $ok) {
            return false;
        }
        \App\Models\ContactMessage::create([
            'name' => $parent->name,
            'phone' => $parent->phone,
            'email' => $parent->email,
            'message' => $reply,
            'is_read' => true,
            'telegram_chat_id' => $chatId,
            'source' => \App\Models\ContactMessage::SOURCE_TELEGRAM,
            'direction' => \App\Models\ContactMessage::DIR_OUT,
            'admin_user_id' => $adminUserId,
        ]);

        return true;
    }

    /** Build the human-readable order summary (name, contact, items, address). */
    public function orderSummary(Order $order, string $heading): string
    {
        $addr = (array) $order->shipping_address;
        $gwLabels = ['zarinpal' => 'زرین‌پال', 'zibal' => 'زیبال', 'snapppay' => 'اسنپ‌پی', 'gift' => 'کارت هدیه'];

        $lines = [
            $heading,
            '',
            '━━━ <b>اطلاعات سفارش</b> ━━━',
            'کد سفارش: '.$order->number,
            'تاریخ ثبت: '.($order->placed_at ? Money::toPersianDigits($order->placed_at->format('Y/m/d H:i')) : '—'),
            'تاریخ پرداخت: '.($order->paid_at ? Money::toPersianDigits($order->paid_at->format('Y/m/d H:i')) : '—'),
            '',
            '━━━ <b>مشتری</b> ━━━',
            'نام: '.($order->customer_name ?: '—'),
            'تماس: '.($order->customer_phone ?: '—'),
        ];
        if ($addr) {
            $addressParts = array_filter([
                $addr['province'] ?? null, $addr['city'] ?? null, $addr['line'] ?? null,
            ]);
            if ($addressParts) {
                $lines[] = 'آدرس: '.implode('، ', $addressParts);
            }
            if (! empty($addr['postal_code'])) {
                $lines[] = 'کدپستی: '.$addr['postal_code'];
            }
        }
        $lines[] = '';
        $lines[] = '━━━ <b>اقلام سفارش</b> ━━━';
        foreach ($order->items as $it) {
            $variant = trim(($it->size ? ' '.$it->size : '').($it->color ? ' '.$it->color : ''));
            $total = Money::toPersianDigits(number_format($it->line_total));
            $qty = Money::toPersianDigits((string) $it->quantity);
            $lines[] = '▫ '.$it->name.$variant.' — '.$qty.' عدد × '.$total.' تومان';
        }
        $lines[] = '';
        $lines[] = '━━━ <b>صورتحساب</b> ━━━';
        $lines[] = 'جمع کالاها: '.Money::toPersianDigits(number_format($order->subtotal)).' تومان';
        if ($order->discount > 0) {
            $lines[] = 'تخفیف: −'.Money::toPersianDigits(number_format($order->discount)).' تومان';
        }
        if ($order->coupon_code) {
            $lines[] = 'کد تخفیف: '.$order->coupon_code;
        }
        if ($order->loyalty_discount > 0) {
            $lines[] = 'امتیاز باشگاه: −'.Money::toPersianDigits(number_format($order->loyalty_discount)).' تومان';
        }
        if ($order->gift_used > 0) {
            $lines[] = 'کارت هدیه: −'.Money::toPersianDigits(number_format($order->gift_used)).' تومان';
        }
        $shippingLabel = $order->shipping_cost_on_delivery
            ? '<b>پس‌کرایه</b> (دریافت از مشتری هنگام تحویل)'
            : ($order->shipping_cost > 0 ? Money::toPersianDigits(number_format($order->shipping_cost)).' تومان' : 'رایگان');
        $lines[] = 'ارسال: '.$order->shipping_method_name.' — '.$shippingLabel;
        if ($order->gift_wrap) {
            $lines[] = '🎁 بسته‌بندی کادویی: '.($order->gift_wrap_price > 0 ? Money::toPersianDigits(number_format($order->gift_wrap_price)).' تومان' : 'رایگان');
            if (filled($order->gift_message)) {
                $lines[] = 'پیام روی کارت: '.e($order->gift_message);
            }
        }
        $lines[] = 'مبلغ کل: <b>'.Money::toPersianDigits(number_format($order->total)).' تومان</b>';
        $lines[] = '';
        $lines[] = '━━━ <b>روش‌ها</b> ━━━';
        if ($order->payment && $order->payment->gateway) {
            $gw = $order->payment->gateway;
            $lines[] = 'پرداخت: '.($gwLabels[$gw] ?? $gw);
            if ($order->payment->ref_id) {
                $lines[] = 'کد پیگیری: '.$order->payment->ref_id;
            }
        }
        $lines[] = 'ارسال: '.($order->shipping_method_name ?: '—');
        $lines[] = '';
        if ($order->customer_note) {
            $lines[] = '';
            $lines[] = '📝 یادداشت مشتری: '.$order->customer_note;
        }
        $lines[] = '';
        $lines[] = 'وضعیت: <b>'.$order->statusLabel().'</b>';

        return implode("\n", $lines);
    }

    /** @return array<string, mixed>|null */
    private function statusKeyboard(Order $order): ?array
    {
        $transitions = [
            Order::STATUS_PAID => [
                ['text' => '📦 در حال آماده‌سازی', 'callback_data' => 'st:processing:'.$order->id],
                ['text' => '❌ لغو شد', 'callback_data' => 'st:canceled:'.$order->id],
            ],
            Order::STATUS_PROCESSING => [
                ['text' => '✅ ارسال شد', 'callback_data' => 'st:shipped:'.$order->id],
                ['text' => '❌ لغو شد', 'callback_data' => 'st:canceled:'.$order->id],
            ],
            Order::STATUS_SHIPPED => [
                ['text' => '✔️ تحویل شد', 'callback_data' => 'st:delivered:'.$order->id],
            ],
        ];

        $buttons = $transitions[$order->status] ?? null;
        if (! $buttons) {
            return null; // terminal state — no buttons
        }

        return ['inline_keyboard' => [$buttons]];
    }

    /** Broadcast an order status change notification to all admins. */
    public function notifyOrderStatusChanged(Order $order, string $oldStatus): void
    {
        if (! $this->eventEnabled('order_status')) {
            return;
        }
        $this->notifyAdmins($this->orderSummary($order, '🔄 <b>تغییر وضعیت سفارش</b>'), $this->statusKeyboard($order));
    }

    /** Broadcast an order cancellation notification. */
    public function notifyOrderCancelled(Order $order): void
    {
        if (! $this->eventEnabled('order_canceled')) {
            return;
        }
        $this->notifyAdmins($this->orderSummary($order, '❌ <b>سفارش لغو شد</b>'), null); // no buttons — terminal state
    }

    /** Return stock for a canceled paid order (mirrors Admin/OrderController). */
    private function restockCancelled(Order $order): void
    {
        $order->loadMissing('items.variant');
        $skItems = [];
        foreach ($order->items as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }
            if ($item->variant) {
                $item->variant->increment('stock_qty', $qty);
            }
            if ($item->stockkeeping_variant_id) {
                $skItems[] = ['variant_id' => (int) $item->stockkeeping_variant_id, 'delta' => $qty];
            } elseif ($item->sku) {
                $skItems[] = ['barcode' => (string) $item->sku, 'delta' => $qty];
            }
        }
        if ($skItems) {
            $ref = 'CXL-'.$order->number;
            try {
                app(\App\Services\StockKeeping\StockKeepingClient::class)
                    ->adjustStock($skItems, 'website_cancel', $ref);
                StockkeeepingLog::record(
                    StockkeeepingLog::TYPE_CANCEL_RESTOCK, 'out', $ref, $order->id, $skItems,
                );
            } catch (Throwable $e) {
                report($e);
                StockkeeepingLog::record(
                    StockkeeepingLog::TYPE_CANCEL_RESTOCK, 'out', $ref, $order->id, $skItems,
                    'failed', null, $e->getMessage(),
                );
            }
        }
    }
}

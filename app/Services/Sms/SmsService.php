<?php

namespace App\Services\Sms;

use App\Models\Order;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\TelegramChat;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Single Melipayamak SMS gateway (console.melipayamak.com). Modeled on a known
 * working production integration: one account UUID (the "API key") in the URL
 * path, no username/password, TLS always verified.
 *
 *   - OTP / pattern (bodyId): POST {body_url}/{key} {bodyId, to, args}
 *   - Auto-OTP:               POST {otp_url}/{key}  {to}            → {code,status}
 *   - Free text:              POST {text_url}/{key} {from, to, text}
 *
 * A send succeeds when the provider returns status === '' and a positive recId
 * (auto-OTP returns a numeric `code` instead). Every send is logged to
 * sms_messages. Telegram notifications are an entirely separate system
 * (App\Services\Telegram) and are NOT touched here.
 *
 * Credentials/bodyIds come from admin settings (the `sms.*` Setting keys),
 * falling back to env via config(). The endpoints live in
 * config('services.melipayamak.*').
 */
class SmsService
{
    /** True once a Melipayamak API key is configured (admin or env). */
    public function isConfigured(): bool
    {
        return $this->key() !== '';
    }

    /** Master switch — SMS is live only when configured AND enabled in admin. */
    public function isEnabled(): bool
    {
        return $this->isConfigured() && (bool) Setting::get('sms.enabled', true);
    }

    /* --------------------------------------------------------------------- */
    /*  OTP                                                                   */
    /* --------------------------------------------------------------------- */

    /**
     * Send a one-time code. We own the code: when an OTP bodyId is configured we
     * send OUR code through that approved pattern and return null (caller keeps
     * its own code). With no bodyId we fall back to Melipayamak's auto-OTP, which
     * generates + returns the code (caller stores that). When SMS isn't
     * configured at all we no-op and return null so local logins still work
     * (the caller surfaces a dev code).
     */
    public function sendOtp(string $phone, string $code): ?string
    {
        if (! $this->isConfigured()) {
            return null; // dev / not set up yet — caller surfaces the code
        }

        $phone = $this->normalizePhone($phone);
        $bodyId = (int) Setting::get('sms.otp_body_id', 0);

        if ($bodyId > 0) {
            // Pattern 463236 has two variables: {0}=name, {1}=code. We only have
            // the phone at login, so use the account name if it exists else a
            // generic greeting. The code fills {1}.
            $name = User::where('phone', $phone)->value('name') ?: 'کاربر';
            $this->shared($phone, $bodyId, [$name, $code]);

            return null;
        }

        // No pattern configured → provider-generated auto-OTP.
        $resp = $this->post(
            rtrim((string) config('services.melipayamak.otp_url'), '/').'/'.$this->key(),
            ['to' => $phone],
        );
        $providerCode = trim((string) ($resp['code'] ?? ''));
        if ($providerCode === '' || ! ctype_digit($providerCode)) {
            throw new RuntimeException('Melipayamak OTP failed: '.($resp['status'] ?: 'unknown'));
        }

        return $providerCode;
    }

    /* --------------------------------------------------------------------- */
    /*  Order notifications                                                   */
    /* --------------------------------------------------------------------- */

    /** Notify the customer their order was paid (via the order bodyId). */
    public function notifyOrderPaid(Order $order): void
    {
        if (! Setting::get('sms.notify_paid', false) || ! $order->customer_phone) {
            return;
        }

        $bodyId = (int) Setting::get('sms.order_body_id', 0);
        if ($bodyId <= 0) {
            return; // bodyId not set yet — nothing to send
        }

        // Pattern 463237 has three variables: {0}=name, {1}=store, {2}=order
        // number (no amount). Order matters.
        $name = $order->customer_name ?: 'مشتری';
        $store = (string) (Setting::get('site.store_name') ?: 'چیاکو');
        $this->pattern($order->customer_phone, (string) $bodyId, [$name, $store, $order->number], 'order');
        // Admin alerts are delivered via the Telegram bot (see CheckoutController).
    }

    /**
     * Notify the customer their order shipped. Kept working for later: no-ops
     * silently until a shipped bodyId is configured.
     */
    public function notifyOrderShipped(Order $order): void
    {
        if (! Setting::get('sms.notify_shipped', false) || ! $order->customer_phone) {
            return;
        }

        // If the customer is connected to the Telegram bot, the shipment update
        // is delivered there (notifyCustomerStatus) — skip the SMS to avoid
        // double-notifying.
        if ($this->customerOnTelegram($order)) {
            return;
        }

        $bodyId = (int) Setting::get('sms.shipped_body_id', 0);
        if ($bodyId <= 0) {
            return;
        }

        $this->pattern($order->customer_phone, (string) $bodyId, [$order->customer_name ?: 'مشتری', $order->number], 'order');
    }

    /** Is the order's customer linked to the Telegram bot? (best-effort) */
    private function customerOnTelegram(Order $order): bool
    {
        if (! $order->user_id || ! Schema::hasTable('telegram_chats')) {
            return false;
        }

        try {
            return TelegramChat::customers()->where('user_id', $order->user_id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /* --------------------------------------------------------------------- */
    /*  Generic sends (used by campaigns / tests)                             */
    /* --------------------------------------------------------------------- */

    /** Send a provider pattern (bodyId + args) and log it. */
    public function pattern(string $phone, string $bodyId, array $args, string $type = 'order'): bool
    {
        $summary = 'pattern '.$bodyId.': '.implode('؛ ', $args);

        return $this->dispatch($phone, fn () => $this->shared(
            $this->normalizePhone($phone), (int) $bodyId, array_values(array_map('strval', $args))
        ), $summary, $type);
    }

    /** Send a free-text message and log it. */
    public function send(string $phone, string $message, string $type = 'campaign'): bool
    {
        return $this->dispatch($phone, fn () => $this->simple($this->normalizePhone($phone), $message), $message, $type);
    }

    /* --------------------------------------------------------------------- */
    /*  Audience helpers (campaigns)                                          */
    /* --------------------------------------------------------------------- */

    /** @return array<int, string> */
    public function adminPhones(): array
    {
        $raw = (string) Setting::get('sms.admin_phone', '');

        return array_values(array_filter(array_map('trim', preg_split('/[,،\s]+/', $raw))));
    }

    /**
     * Resolve a campaign audience to phone numbers.
     *
     * @return Collection<int, string>
     */
    public function audience(string $group): Collection
    {
        $paid = [Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED];

        return match ($group) {
            'buyers' => User::whereHas('orders', fn ($q) => $q->whereIn('status', $paid))
                ->whereNotNull('phone')->pluck('phone'),
            default => User::whereNotNull('phone')->pluck('phone'),
        };
    }

    /* --------------------------------------------------------------------- */
    /*  Low-level transport                                                   */
    /* --------------------------------------------------------------------- */

    /** Shared/pattern send: {bodyId, to, args} → asserts a positive recId. */
    private function shared(string $phone, int $bodyId, array $args): void
    {
        $resp = $this->post(
            rtrim((string) config('services.melipayamak.body_url'), '/').'/'.$this->key(),
            ['bodyId' => $bodyId, 'to' => $phone, 'args' => $args],
        );
        $this->assertSent($resp);
    }

    /** Free-text send: {from, to, text} → asserts a positive recId. */
    private function simple(string $phone, string $text): void
    {
        $resp = $this->post(
            rtrim((string) config('services.melipayamak.text_url'), '/').'/'.$this->key(),
            ['from' => (string) Setting::get('sms.sender', ''), 'to' => $phone, 'text' => $text],
        );
        $this->assertSent($resp);
    }

    /**
     * Success is a positive recId. The console endpoint returns the status as a
     * human message ("عملیات موفق" on success, an error phrase otherwise), so we
     * key off recId — not an empty status — and surface the status only on error.
     */
    private function assertSent(array $resp): void
    {
        $recId = $resp['recId'] ?? null;

        if (empty($recId) || (is_numeric($recId) && (float) $recId <= 0)) {
            throw new RuntimeException('Melipayamak send failed: '.(trim((string) ($resp['status'] ?? '')) ?: 'unknown'));
        }
    }

    /**
     * POST a JSON payload. TLS verification stays ON (never disabled). POST is
     * preserved across a 301/302 redirect — Guzzle's default downgrades it to a
     * GET, which the send endpoints reject with 405.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $url, array $payload): array
    {
        $resp = Http::asJson()
            ->timeout(15)
            ->withOptions(['allow_redirects' => ['strict' => true]])
            ->post($url, $payload)
            ->throw();

        return is_array($resp->json()) ? $resp->json() : [];
    }

    /** The Melipayamak account key (admin setting wins over env). */
    private function key(): string
    {
        return (string) (Setting::get('sms.api_key') ?: config('services.melipayamak.api_key', ''));
    }

    /* --------------------------------------------------------------------- */
    /*  Logging + normalization                                              */
    /* --------------------------------------------------------------------- */

    /** Run a send closure, record the result, swallow errors (logged). */
    private function dispatch(string $phone, callable $send, string $summary, string $type): bool
    {
        try {
            $send();
            $this->record($phone, $summary, $type, 'sent');

            return true;
        } catch (Throwable $e) {
            $this->record($phone, $summary, $type, 'failed', $e->getMessage());
            Log::warning('[sms] send failed', ['phone' => $phone, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function record(string $phone, string $body, string $type, string $status, ?string $error = null): void
    {
        try {
            SmsMessage::create(compact('phone', 'body', 'type', 'status', 'error'));
        } catch (Throwable) {
            // logging table missing — never let logging break a send
        }
    }

    /** Normalize Iranian mobile numbers to a canonical 09xxxxxxxxx form. */
    public function normalizePhone(string $phone): string
    {
        $phone = strtr($phone, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $phone = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '0098')) {
            $phone = '0'.substr($phone, 4);
        } elseif (str_starts_with($phone, '98') && strlen($phone) === 12) {
            $phone = '0'.substr($phone, 2);
        } elseif (str_starts_with($phone, '9') && strlen($phone) === 10) {
            $phone = '0'.$phone;
        }

        return $phone;
    }
}

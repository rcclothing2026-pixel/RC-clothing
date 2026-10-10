<?php

namespace App\Console\Commands;

use App\Models\SavedCart;
use App\Models\Setting;
use App\Services\Sms\SmsService;
use Illuminate\Console\Command;

/**
 * Remind logged-in customers about a cart they left behind: a saved cart that's
 * been idle between 1h and 48h, not yet reminded, whose owner has a phone. Sends
 * one SMS with a recovery link, then marks it reminded.
 */
class RecoverAbandonedCarts extends Command
{
    protected $signature = 'cart:recover';

    protected $description = 'SMS customers a link to recover their abandoned cart';

    public function handle(SmsService $sms): int
    {
        if (! Setting::get('sms.notify_cart', false)) {
            $this->info('Cart recovery disabled (sms.notify_cart).');

            return self::SUCCESS;
        }

        $carts = SavedCart::with('user')
            ->whereNull('reminded_at')
            ->where('updated_at', '<=', now()->subHour())
            ->where('updated_at', '>=', now()->subHours(48))
            ->get()
            ->filter(fn ($c) => $c->user?->phone && ! empty($c->items));

        $pattern = (string) Setting::get('sms.pattern_cart_recovery', '');
        $sent = 0;

        foreach ($carts as $cart) {
            $link = route('cart.restore', $cart->token);
            if ($pattern !== '') {
                $ok = $sms->pattern($cart->user->phone, $pattern, [$cart->user->name ?: 'مشتری', $link], 'campaign');
            } else {
                $ok = $sms->send($cart->user->phone, "سبد خرید شما در چیاکو منتظر شماست: {$link}", 'campaign');
            }
            $cart->update(['reminded_at' => now()]);
            if ($ok) {
                $sent++;
            }
        }

        $this->info("Reminded {$sent} abandoned cart(s).");

        return self::SUCCESS;
    }
}

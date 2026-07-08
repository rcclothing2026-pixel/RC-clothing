<?php

namespace App\Console\Commands;

use App\Mail\AbandonedCartEmail;
use App\Models\SavedCart;
use App\Models\EmailLog;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Send email reminders for abandoned carts (complements the SMS recovery).
 */
class SendAbandonedCartEmails extends Command
{
    protected $signature = 'cart:recover-email';

    protected $description = 'Email customers a recovery link for their abandoned cart';

    public function handle(): int
    {
        if (! Setting::get('email.notify_cart', false)) {
            $this->info('Cart recovery email disabled (email.notify_cart).');
            return self::SUCCESS;
        }

        $carts = SavedCart::with('user')
            ->whereNull('reminded_at')
            ->where('updated_at', '<=', now()->subHour())
            ->where('updated_at', '>=', now()->subHours(48))
            ->get()
            ->filter(fn ($c) => $c->user?->email && ! empty($c->items));

        $sent = 0;
        foreach ($carts as $cart) {
            try {
                Mail::to($cart->user->email)->send(new AbandonedCartEmail($cart));
                EmailLog::create([
                    'type' => 'abandoned_cart',
                    'recipient_email' => $cart->user->email,
                    'subject' => 'سبد خرید شما در چیاکو منتظر است!',
                    'status' => 'sent',
                    'relatable_type' => SavedCart::class,
                    'relatable_id' => $cart->id,
                ]);
                $cart->update(['reminded_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                EmailLog::create([
                    'type' => 'abandoned_cart',
                    'recipient_email' => $cart->user->email,
                    'subject' => 'سبد خرید شما در چیاکو منتظر است!',
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed for {$cart->user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Emailed {$sent} abandoned cart(s).");
        return self::SUCCESS;
    }
}

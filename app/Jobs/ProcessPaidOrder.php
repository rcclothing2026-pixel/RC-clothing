<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Services\Sms\SmsService;
use App\Services\StockKeeping\StockKeepingReporter;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Post-payment side effects, off the customer's request path: report the sale to
 * StoqS, SMS the customer, and alert admins on Telegram. Each is isolated so one
 * failure can't sink the others. With the sync queue driver it runs inline (same
 * as before); with a real queue + worker it runs in the background.
 */
class ProcessPaidOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $orderId) {}

    public function handle(StockKeepingReporter $reporter, SmsService $sms, TelegramNotifier $telegram): void
    {
        $order = Order::find($this->orderId);
        if (! $order) {
            return;
        }

        try {
            $reporter->reportPaidOrder($order->fresh(['items', 'payment', 'user']));
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $sms->notifyOrderPaid($order);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $telegram->notifyOrderPaid($order);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $telegram->notifyCustomerOrderConfirmed($order);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            if ($order->user?->email) {
                Mail::to($order->user->email)->send(new OrderConfirmation($order->load('items')));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}

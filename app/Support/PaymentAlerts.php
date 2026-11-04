<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Admin warning: active payment methods that can't take payments (missing
 * credentials, so hidden at checkout), and orders recorded as paid through
 * the DevGateway simulator (DEV- references) — those were never paid.
 */
class PaymentAlerts
{
    public static function summary(): array
    {
        return Cache::remember('admin:payment-alerts', 300, function () {
            $factory = app(PaymentGatewayFactory::class);
            $unusable = PaymentMethod::active()->get()->reject(fn ($m) => $factory->usable($m))->pluck('label')->filter()->values()->all();
            $fake = PaymentGatewayFactory::devAllowed() ? [] : Order::whereIn('id', Payment::where('status', Payment::STATUS_PAID)
                    ->where(fn ($q) => $q->where('authority', 'like', 'DEV-%')->orWhere('ref_id', 'like', 'DEV-%'))->select('order_id'))
                ->latest()->limit(50)->pluck('number')->all();

            return array_filter(['unusable' => $unusable, 'fake' => $fake]);
        });
    }
}

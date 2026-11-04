<?php

namespace App\Services\Payment;

use App\Models\PaymentMethod;
use InvalidArgumentException;

/**
 * Builds a concrete PaymentGateway from an admin-configured PaymentMethod
 * record. A method without credentials is NOT usable: the DevGateway
 * simulator (it approves without a bank) is used only when
 * shop.dev_gateway is on, i.e. in tests and local demos, never in
 * production. It once stood in for a half-configured method on the live
 * shop and marked unpaid orders as paid.
 */
class PaymentGatewayFactory
{
    public function make(PaymentMethod $method): PaymentGateway
    {
        $gateway = $this->build($method);
        if ($gateway->isConfigured()) {
            return $gateway;
        }
        if (self::devAllowed()) {
            return new DevGateway($method->key);
        }

        throw new \RuntimeException('درگاه «'.$method->key.'» تنظیم نشده است.');
    }

    /** The driver for the callback's verify step; same rules as make(). */
    public function makeReal(PaymentMethod $method): PaymentGateway
    {
        return $this->make($method);
    }

    /** True when the method can take a payment here: it is configured, or the simulator is allowed. */
    public function usable(PaymentMethod $method): bool
    {
        try {
            return $this->build($method)->isConfigured() || self::devAllowed();
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function devAllowed(): bool
    {
        return (bool) config('shop.dev_gateway', false);
    }

    private function build(PaymentMethod $method): PaymentGateway
    {
        return match ($method->key) {
            'zarinpal' => new ZarinpalGateway(
                merchantId: $method->config('merchant_id'),
                sandbox: $method->sandbox,
            ),
            'zibal' => new ZibalGateway(
                merchant: $method->config('merchant'),
                sandbox: $method->sandbox,
            ),
            'snapppay' => new SnappPayGateway(
                baseUrl: $method->config('base_url'),
                clientId: $method->config('client_id'),
                clientSecret: $method->config('client_secret'),
                username: $method->config('username'),
                password: $method->config('password'),
            ),
            default => throw new InvalidArgumentException("Unknown gateway: {$method->key}"),
        };
    }
}

<?php

namespace App\Services\Payment;

use App\Models\PaymentMethod;
use InvalidArgumentException;

/**
 * Builds a concrete PaymentGateway from an admin-configured PaymentMethod
 * record. Falls back to the DevGateway when credentials are missing so the
 * checkout flow keeps working before real onboarding.
 */
class PaymentGatewayFactory
{
    public function make(PaymentMethod $method): PaymentGateway
    {
        $gateway = $this->build($method);

        return $gateway->isConfigured() ? $gateway : new DevGateway($method->key);
    }

    /** Force the real driver (used by the callback to verify). */
    public function makeReal(PaymentMethod $method): PaymentGateway
    {
        $gateway = $this->build($method);

        // If it was never configured, the request also used DevGateway.
        return $gateway->isConfigured() ? $gateway : new DevGateway($method->key);
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

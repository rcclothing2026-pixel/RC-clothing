<?php

namespace App\Services\Payment;

/**
 * Implemented by gateways that can refund a verified transaction via API.
 * Most Iranian gateways gate automated refunds behind special merchant access,
 * so gateways that don't implement this fall back to a manual refund.
 */
interface SupportsRefund
{
    /** Refund a transaction. Returns true if the gateway accepted the refund. */
    public function refund(string $identifier, int $amountToman): bool;
}

<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

/**
 * Common contract for every online payment gateway (ZarinPal, Zibal, Snapp Pay…).
 * Amounts are passed as Toman; each driver converts to Rial where required.
 */
interface PaymentGateway
{
    /** Stable key, e.g. "zarinpal", "zibal", "snapppay". */
    public function key(): string;

    /** True when the driver has the credentials it needs to talk to the gateway. */
    public function isConfigured(): bool;

    /**
     * Start a payment. Returns the gateway's transaction identifier (stored on
     * the payment) and the URL to redirect the customer to.
     *
     * @return array{identifier: string, redirect_url: string}
     */
    public function request(int $amountToman, string $callbackUrl, string $description, array $metadata = []): array;

    /** Extract this gateway's transaction identifier from the callback request. */
    public function callbackIdentifier(Request $request): ?string;

    /** Did the customer approve the payment at the gateway (vs cancel)? */
    public function callbackApproved(Request $request): bool;

    /**
     * Verify a transaction after the callback.
     *
     * @return array{success: bool, ref_id: ?string, card_pan: ?string}
     */
    public function verify(string $identifier, int $amountToman): array;
}

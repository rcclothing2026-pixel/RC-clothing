<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Simulation driver used when a real gateway has no credentials yet. It skips
 * the bank and returns straight to the callback as "approved", so the full
 * checkout flow is demoable. Never used once a gateway is configured.
 */
class DevGateway implements PaymentGateway
{
    public function __construct(private readonly string $wrappedKey) {}

    public function key(): string
    {
        return $this->wrappedKey;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function request(int $amountToman, string $callbackUrl, string $description, array $metadata = []): array
    {
        $identifier = 'DEV-'.Str::upper(Str::random(18));
        $sep = str_contains($callbackUrl, '?') ? '&' : '?';

        return [
            'identifier' => $identifier,
            'redirect_url' => $callbackUrl.$sep.http_build_query(['identifier' => $identifier, 'approved' => 1]),
        ];
    }

    public function callbackIdentifier(Request $request): ?string
    {
        return $request->query('identifier');
    }

    public function callbackApproved(Request $request): bool
    {
        return (string) $request->query('approved') === '1';
    }

    public function verify(string $identifier, int $amountToman): array
    {
        return ['success' => true, 'ref_id' => 'DEV-'.random_int(100000, 999999), 'card_pan' => null];
    }
}

<?php

namespace App\Services\Payment;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zibal gateway (https://help.zibal.ir/IPG/API/). Amounts in Rial.
 * Use merchant "zibal" for sandbox testing.
 */
class ZibalGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $merchant,
        private readonly bool $sandbox = false,
    ) {}

    public function key(): string
    {
        return 'zibal';
    }

    public function isConfigured(): bool
    {
        // Requires an explicit merchant (use "zibal" for the sandbox merchant).
        return filled($this->merchant);
    }

    private function merchant(): string
    {
        return $this->sandbox ? 'zibal' : (string) $this->merchant;
    }

    public function request(int $amountToman, string $callbackUrl, string $description, array $metadata = []): array
    {
        $response = Http::acceptJson()->timeout(15)
            ->post('https://gateway.zibal.ir/v1/request', array_filter([
                'merchant' => $this->merchant(),
                'amount' => Money::tomanToRial($amountToman),
                'callbackUrl' => $callbackUrl,
                'description' => $description,
                'mobile' => $metadata['mobile'] ?? null,
                'orderId' => $metadata['order'] ?? null,
            ]))
            ->throw();

        $result = (int) $response->json('result');
        $trackId = $response->json('trackId');

        if ($result !== 100 || ! $trackId) {
            throw new RuntimeException('Zibal request failed: '.$response->json('message'));
        }

        return [
            'identifier' => (string) $trackId,
            'redirect_url' => 'https://gateway.zibal.ir/start/'.$trackId,
        ];
    }

    public function callbackIdentifier(Request $request): ?string
    {
        return $request->query('trackId');
    }

    public function callbackApproved(Request $request): bool
    {
        // success=1 indicates the customer completed payment at Zibal.
        return (string) $request->query('success') === '1';
    }

    public function verify(string $identifier, int $amountToman): array
    {
        $response = Http::acceptJson()->timeout(15)
            ->post('https://gateway.zibal.ir/v1/verify', [
                'merchant' => $this->merchant(),
                'trackId' => $identifier,
            ])
            ->throw();

        $result = (int) $response->json('result');

        return [
            // 100 = verified, 201 = already verified.
            'success' => in_array($result, [100, 201], true),
            'ref_id' => $response->json('refNumber') ? (string) $response->json('refNumber') : $identifier,
            'card_pan' => $response->json('cardNumber'),
        ];
    }
}

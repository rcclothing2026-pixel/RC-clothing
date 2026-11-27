<?php

namespace App\Services\Payment;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Zibal gateway (https://help.zibal.ir/IPG/API/). Amounts in Rial.
 * Use merchant "zibal" for sandbox testing.
 *
 * verify() never turns "no answer" into "not paid": a verify that timed out may still
 * have settled the money at Zibal. It asks Zibal (inquiry) what happened, and when that
 * does not settle it either, it reports the outcome as unknown so the order stays open.
 */
class ZibalGateway implements PaymentGateway
{
    private const BASE = 'https://gateway.zibal.ir/v1/';

    // Inquiry statuses.
    private const PAID_VERIFIED = 1;
    private const PAID_UNVERIFIED = 2;

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
            ->post(self::BASE.'request', array_filter([
                'merchant' => $this->merchant(),
                'amount' => Money::tomanToRial($amountToman),
                'callbackUrl' => $callbackUrl,
                'description' => $description,
                'mobile' => $metadata['mobile'] ?? null,
                // Checkout passes order_id; the order number shows in the Zibal panel.
                'orderId' => $metadata['order_id'] ?? $metadata['order'] ?? null,
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
        $rial = Money::tomanToRial($amountToman);

        // Budget: 15 s + 8 s + 8 s, so a stuck Zibal cannot hold the order lock for long.
        $first = $this->verifyOnce($identifier, $rial, 15);
        if ($first['known']) {
            return $first['outcome'];
        }

        // No usable verify answer. Ask Zibal what state the payment is in.
        $inq = $this->inquiry($identifier);
        if ($inq === null) {
            return $this->unknown('verify: '.$first['why'].'; inquiry: no answer');
        }
        $status = isset($inq['status']) ? (int) $inq['status'] : null;
        // -2 = Zibal internal error, -1 = still waiting: neither says the money did not move.
        if ($status === null || $status === -2 || $status === -1) {
            return $this->unknown('verify: '.$first['why'].'; inquiry status '.($status ?? 'missing'));
        }

        if ($status === self::PAID_VERIFIED) {
            // The lost verify did settle it.
            if ((int) ($inq['amount'] ?? 0) !== $rial) {
                return $this->mismatch($identifier, $rial, $inq);
            }

            return $this->paid($inq);
        }
        if ($status === self::PAID_UNVERIFIED) {
            // Paid but not verified yet: the first verify may still be in flight at Zibal,
            // so this is not a "not paid". Verify is idempotent (201 = already verified):
            // try once more, and if that is also unclear, leave the order open.
            $again = $this->verifyOnce($identifier, $rial, 8);

            return $again['known'] ? $again['outcome'] : $this->unknown('verify unclear twice; inquiry status 2');
        }

        // Zibal answered and the payment is not paid (pending at the bank, failed, returned…).
        return ['success' => false, 'ref_id' => null, 'card_pan' => null, 'zibal_status' => $status];
    }

    /**
     * One verify call. known=false means the answer does not tell us whether the money
     * moved (no reply, an HTTP error, or a code Zibal does not document for this).
     *
     * @return array{known: bool, outcome?: array, why?: string}
     */
    private function verifyOnce(string $identifier, int $rial, int $timeout): array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout($timeout)->post(self::BASE.'verify', [
                'merchant' => $this->merchant(),
                'trackId' => $identifier,
            ]);
        } catch (Throwable $e) {
            return ['known' => false, 'why' => $e->getMessage()];
        }
        if (! $response->successful()) {
            return ['known' => false, 'why' => 'HTTP '.$response->status()];
        }

        $result = (int) $response->json('result');

        // 100 = verified, 201 = already verified.
        if (in_array($result, [100, 201], true)) {
            $amount = $response->json('amount');
            if ($amount !== null && (int) $amount !== $rial) {
                return ['known' => true, 'outcome' => $this->mismatch($identifier, $rial, (array) $response->json())];
            }

            return ['known' => true, 'outcome' => $this->paid((array) $response->json(), $identifier)];
        }
        // 202 = not paid or failed, 203 = unknown trackId.
        if (in_array($result, [202, 203], true)) {
            return ['known' => true, 'outcome' => ['success' => false, 'ref_id' => null, 'card_pan' => null, 'zibal_result' => $result]];
        }

        return ['known' => false, 'why' => 'result '.$result.' '.$response->json('message')];
    }

    /** Zibal's view of the payment, or null when it could not be read. */
    private function inquiry(string $identifier): ?array
    {
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(8)->post(self::BASE.'inquiry', [
                'merchant' => $this->merchant(),
                'trackId' => $identifier,
            ]);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful() || (int) $response->json('result') !== 100) {
            return null;
        }

        return (array) $response->json();
    }

    private function paid(array $data, ?string $identifier = null): array
    {
        $ref = $data['refNumber'] ?? null;

        return [
            'success' => true,
            'ref_id' => $ref ? (string) $ref : ($identifier ?? (string) ($data['trackId'] ?? '')),
            'card_pan' => $data['cardNumber'] ?? null,
        ];
    }

    /** Money settled for a different amount than the order: never a sale, staff must refund. */
    private function mismatch(string $identifier, int $rial, array $data): array
    {
        report(new RuntimeException("Zibal trackId {$identifier}: settled ".($data['amount'] ?? '?')." rial, order expects {$rial}"));

        return [
            'success' => false, 'ref_id' => isset($data['refNumber']) ? (string) $data['refNumber'] : null,
            'card_pan' => $data['cardNumber'] ?? null, 'amount_mismatch' => true, 'settled_rial' => $data['amount'] ?? null,
        ];
    }

    private function unknown(string $why): array
    {
        return ['success' => false, 'unknown' => true, 'ref_id' => null, 'card_pan' => null, 'error' => $why];
    }
}

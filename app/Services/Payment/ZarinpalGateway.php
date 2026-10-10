<?php

namespace App\Services\Payment;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ZarinPal REST API v4 gateway, matching the official sample
 * (github.com/ZarinPal-Lab/Zarinpal-RestAPI-Sample-php):
 *   - request/verify → https://api.zarinpal.com/pg/v4/payment/{request,verify}.json
 *   - redirect       → https://www.zarinpal.com/pg/StartPay/{authority}
 *   - JSON body + "ZarinPal Rest Api v4" User-Agent; amount in Rial.
 *
 * Amounts are kept in Toman internally and converted to Rial at the edge.
 *
 * @see https://www.zarinpal.com/docs/
 */
class ZarinpalGateway implements PaymentGateway
{
    private const USER_AGENT = 'ZarinPal Rest Api v4';

    public function __construct(
        private readonly ?string $merchantId,
        private readonly bool $sandbox = true,
    ) {}

    public function key(): string
    {
        return 'zarinpal';
    }

    public function isConfigured(): bool
    {
        return filled($this->merchantId);
    }

    /** Host for the request/verify JSON API calls. */
    private function apiBase(): string
    {
        return $this->sandbox ? 'https://sandbox.zarinpal.com' : 'https://api.zarinpal.com';
    }

    /** Host serving the StartPay redirect page. */
    private function startPayBase(): string
    {
        return $this->sandbox ? 'https://sandbox.zarinpal.com' : 'https://www.zarinpal.com';
    }

    /** ZarinPal rejects amounts below 1,000 Toman (10,000 Rial). */
    private const MIN_TOMAN = 1000;

    public function request(int $amountToman, string $callbackUrl, string $description, array $metadata = []): array
    {
        // Pre-check the gateway minimum so a tiny amount fails with a clear,
        // localized reason instead of a confusing gateway round-trip.
        if ($amountToman < self::MIN_TOMAN) {
            throw new RuntimeException('مبلغ قابل پرداخت کمتر از حداقل مجاز درگاه زرین‌پال (۱٬۰۰۰ تومان) است.');
        }

        $response = Http::acceptJson()
            ->withUserAgent(self::USER_AGENT)
            ->timeout(15)
            ->post($this->apiBase().'/pg/v4/payment/request.json', array_filter([
                'merchant_id' => $this->merchantId,
                'amount' => Money::tomanToRial($amountToman),
                'callback_url' => $callbackUrl,
                'description' => $description,
                'metadata' => $metadata ?: null,
            ]))
            ->throw();

        $code = (int) $response->json('data.code');
        $authority = $response->json('data.authority');

        if ($code !== 100 || ! $authority) {
            throw new RuntimeException($this->errorMessage($response->json('errors'), 'request'));
        }

        return ['identifier' => $authority, 'redirect_url' => $this->startPayBase().'/pg/StartPay/'.$authority];
    }

    public function callbackIdentifier(Request $request): ?string
    {
        return $request->query('Authority');
    }

    public function callbackApproved(Request $request): bool
    {
        return $request->query('Status') === 'OK';
    }

    public function verify(string $identifier, int $amountToman): array
    {
        $response = Http::acceptJson()
            ->withUserAgent(self::USER_AGENT)
            ->timeout(15)
            ->post($this->apiBase().'/pg/v4/payment/verify.json', [
                'merchant_id' => $this->merchantId,
                'amount' => Money::tomanToRial($amountToman),
                'authority' => $identifier,
            ])
            ->throw();

        $code = (int) $response->json('data.code');

        return [
            // 100 = verified now, 101 = already verified (idempotent re-callback).
            'success' => in_array($code, [100, 101], true),
            'ref_id' => $response->json('data.ref_id') ? (string) $response->json('data.ref_id') : null,
            'card_pan' => $response->json('data.card_pan'),
        ];
    }

    /** Build a readable error from ZarinPal's {code, message} errors object. */
    private function errorMessage(mixed $errors, string $stage): string
    {
        if (is_array($errors) && $errors !== []) {
            $message = $errors['message'] ?? json_encode($errors, JSON_UNESCAPED_UNICODE);
            $code = $errors['code'] ?? null;

            // ZarinPal puts the precise field reason (e.g. amount below minimum)
            // in validations — append it so the cause is visible, not just "code -9".
            if (! empty($errors['validations']) && is_array($errors['validations'])) {
                $details = [];
                foreach ($errors['validations'] as $v) {
                    foreach ((array) $v as $reason) {
                        $details[] = $reason;
                    }
                }
                if ($details) {
                    $message .= ' — '.implode(' ', $details);
                }
            }

            return "ZarinPal {$stage} failed".($code !== null ? " (code {$code})" : '').': '.$message;
        }

        return "ZarinPal {$stage} failed.";
    }
}

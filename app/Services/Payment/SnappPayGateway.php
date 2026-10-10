<?php

namespace App\Services\Payment;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Snapp Pay (BNPL) gateway. OAuth2 + payment token flow.
 *
 * Onboarding-specific: base URL, client credentials and a merchant user are
 * issued by Snapp Pay. Endpoints/field names follow the Snapp Pay IPG docs and
 * may need minor adjustment to the merchant's contract.
 *
 * @see https://snapppay.ir/
 */
class SnappPayGateway implements PaymentGateway, SupportsRefund
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
    ) {}

    public function key(): string
    {
        return 'snapppay';
    }

    public function isConfigured(): bool
    {
        return filled($this->baseUrl) && filled($this->clientId) && filled($this->clientSecret);
    }

    private function token(): string
    {
        $response = Http::asForm()->timeout(15)
            ->withBasicAuth((string) $this->clientId, (string) $this->clientSecret)
            ->post(rtrim((string) $this->baseUrl, '/').'/api/online/v1/oauth/token', [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password,
                'scope' => 'online-merchant',
            ])
            ->throw();

        return (string) $response->json('access_token');
    }

    public function request(int $amountToman, string $callbackUrl, string $description, array $metadata = []): array
    {
        $amountRial = Money::tomanToRial($amountToman);

        // Documented SnappPay token body. mobile is needed for BNPL eligibility;
        // externalSourceAmount/discountAmount are required (0 when unused). The
        // single-item cart balances: amount == cartList.totalAmount == item amount.
        $body = [
            'amount' => $amountRial,
            'transactionId' => $metadata['order_id'] ?? $metadata['order'] ?? uniqid('chiiaco_'),
            'returnURL' => $callbackUrl,
            'paymentMethodTypeDto' => 'INSTALLMENT',
            'externalSourceAmount' => 0,
            'discountAmount' => 0,
            'cartList' => [[
                'cartId' => 1,
                'totalAmount' => $amountRial,
                'shippingAmount' => 0,
                'taxAmount' => 0,
                'cartItems' => [[
                    'id' => 1,
                    'name' => $description,
                    'count' => 1,
                    'amount' => $amountRial,
                    'category' => 'clothing',
                    'commissionType' => 100,
                ]],
            ]],
        ];
        if (! empty($metadata['mobile'])) {
            // SnappPay requires the mobile as 98 + the 10-digit national number
            // (e.g. 989123456789). The national part is always the last 10 digits
            // (9xxxxxxxxx), regardless of the stored form (09..., +98..., 0098...).
            $national = substr(preg_replace('/\D+/', '', (string) $metadata['mobile']), -10);
            if (strlen($national) === 10 && $national[0] === '9') {
                $body['mobile'] = '+98'.$national;
            }
        }

        $response = Http::acceptJson()->timeout(20)
            ->withToken($this->token())
            ->post(rtrim((string) $this->baseUrl, '/').'/api/online/payment/v1/token', $body)
            ->throw();

        if (! $response->json('successful')) {
            throw new RuntimeException('Snapp Pay request failed: '.json_encode($response->json('errorData')));
        }

        $paymentToken = (string) $response->json('response.paymentToken');

        return [
            'identifier' => $paymentToken,
            'redirect_url' => (string) $response->json('response.paymentPageUrl'),
        ];
    }

    public function callbackIdentifier(Request $request): ?string
    {
        return $request->query('paymentToken') ?? $request->query('transactionId');
    }

    public function callbackApproved(Request $request): bool
    {
        return in_array((string) $request->query('state'), ['OK', 'SUCCESS', '1'], true);
    }

    public function verify(string $identifier, int $amountToman): array
    {
        $token = $this->token();
        $base = rtrim((string) $this->baseUrl, '/');

        $verify = Http::acceptJson()->timeout(20)->withToken($token)
            ->post($base.'/api/online/payment/v1/verify', ['paymentToken' => $identifier])
            ->throw();

        if (! $verify->json('successful')) {
            return ['success' => false, 'ref_id' => null, 'card_pan' => null];
        }

        // Settle to actually capture the funds.
        $settle = Http::acceptJson()->timeout(20)->withToken($token)
            ->post($base.'/api/online/payment/v1/settle', ['paymentToken' => $identifier])
            ->throw();

        return [
            'success' => (bool) $settle->json('successful'),
            'ref_id' => $settle->json('response.transactionId') ? (string) $settle->json('response.transactionId') : $identifier,
            'card_pan' => null,
        ];
    }

    /**
     * Reverse a settled transaction (full refund). SnappPay's revert takes the
     * payment token and returns `successful`.
     */
    public function refund(string $identifier, int $amountToman): bool
    {
        $response = Http::acceptJson()->timeout(20)
            ->withToken($this->token())
            ->post(rtrim((string) $this->baseUrl, '/').'/api/online/payment/v1/revert', ['paymentToken' => $identifier])
            ->throw();

        return (bool) $response->json('successful');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Proves the SnappPay return-callback happy path (and its failure branches)
 * end-to-end against the REAL SnappPayGateway — token/verify/settle are stubbed
 * with Http::fake so no rial moves and no live gateway is hit. This is the
 * "test the callback without paying" harness: it exercises the exact field
 * names the gateway reads (state / paymentToken) and the verify+settle response
 * shapes, which is what silently broke on the host transfer.
 */
class SnappPayCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://snap.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Give the seeded snapppay method real-looking credentials so the
        // factory builds the REAL SnappPayGateway (not the DevGateway).
        PaymentMethod::where('key', 'snapppay')->update([
            'is_active' => true,
            'config' => [
                'base_url' => self::BASE,
                'client_id' => 'cid',
                'client_secret' => 'secret',
                'username' => 'user',
                'password' => 'pass',
            ],
        ]);
    }

    /** Create an unpaid order + a pending SnappPay payment holding $token as authority. */
    private function pendingOrder(string $token): Order
    {
        $user = User::factory()->create(['phone' => '09121110000']);
        $order = Order::create([
            'number' => 'TEST-'.$token,
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 500000,
            'total' => 500000,
            'customer_name' => 'تست',
            'customer_phone' => '09121110000',
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'snapppay',
            'amount' => 500000,
            'authority' => $token,
            'status' => Payment::STATUS_PENDING,
        ]);

        return $order;
    }

    private function callbackUrl(array $query): string
    {
        return route('checkout.callback', ['method' => 'snapppay']).'?'.http_build_query($query);
    }

    public function test_unknown_token_redirects_home_without_touching_gateway(): void
    {
        Http::fake();

        $this->get($this->callbackUrl(['paymentToken' => 'NOPE', 'state' => 'OK']))
            ->assertRedirect(route('home'));

        Http::assertNothingSent();
    }

    public function test_declined_state_marks_order_failed_without_verifying(): void
    {
        Http::fake();
        $order = $this->pendingOrder('TOK-DECLINED');

        $this->get($this->callbackUrl(['paymentToken' => 'TOK-DECLINED', 'state' => 'FAILED']))
            ->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_FAILED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CANCELED, $order->payment->fresh()->status);
        // Not approved → gateway is never called.
        Http::assertNothingSent();
    }

    public function test_verify_failure_marks_order_failed(): void
    {
        Http::fake([
            self::BASE.'/api/online/v1/oauth/token' => Http::response(['access_token' => 'tok']),
            self::BASE.'/api/online/payment/v1/verify' => Http::response(['successful' => false]),
            '*' => Http::response([], 200),
        ]);
        $order = $this->pendingOrder('TOK-VFAIL');

        $this->get($this->callbackUrl(['paymentToken' => 'TOK-VFAIL', 'state' => 'OK']))
            ->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_FAILED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_FAILED, $order->payment->fresh()->status);
    }

    public function test_approved_and_settled_finalizes_paid_order(): void
    {
        Http::fake([
            self::BASE.'/api/online/v1/oauth/token' => Http::response(['access_token' => 'tok']),
            self::BASE.'/api/online/payment/v1/verify' => Http::response(['successful' => true]),
            self::BASE.'/api/online/payment/v1/settle' => Http::response([
                'successful' => true,
                'response' => ['transactionId' => 'TXN-999'],
            ]),
            '*' => Http::response([], 200), // swallow StoqS/SMS/Telegram side effects
        ]);
        $order = $this->pendingOrder('TOK-OK');

        $this->get($this->callbackUrl(['paymentToken' => 'TOK-OK', 'state' => 'OK']))
            ->assertRedirect(route('checkout.success', $order->number));

        $order->refresh();
        $payment = $order->payment->fresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('TXN-999', $payment->ref_id);
        $this->assertNotNull($payment->paid_at);

        // The gateway must settle (capture) — a verify without settle would
        // leave the funds only authorised, not captured.
        Http::assertSent(fn ($req) => str_contains($req->url(), '/settle'));
    }

    public function test_already_paid_order_is_idempotent(): void
    {
        Http::fake();
        $order = $this->pendingOrder('TOK-DUP');
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        $this->get($this->callbackUrl(['paymentToken' => 'TOK-DUP', 'state' => 'OK']))
            ->assertRedirect(route('checkout.success', $order->number));

        // No re-verify on an already-finalised order.
        Http::assertNothingSent();
    }
}

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
 * The Zibal callback against the REAL ZibalGateway, with Zibal stubbed by Http::fake.
 * The point: a verify with no clear answer must never fail an order whose money may
 * have settled, and a second callback must never finalize the order twice.
 */
class ZibalCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY = 'https://gateway.zibal.ir/v1/verify';
    private const INQUIRY = 'https://gateway.zibal.ir/v1/inquiry';
    private const AMOUNT = 500000; // toman → 5,000,000 rial

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        PaymentMethod::where('key', 'zibal')->update(['is_active' => true, 'config' => ['merchant' => 'm-test']]);
    }

    private function pendingOrder(string $trackId): Order
    {
        $user = User::factory()->create(['phone' => '09121110000']);
        $order = Order::create([
            'number' => 'Z-'.$trackId, 'user_id' => $user->id, 'status' => Order::STATUS_PENDING,
            'subtotal' => self::AMOUNT, 'total' => self::AMOUNT,
            'customer_name' => 'تست', 'customer_phone' => '09121110000',
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'zibal', 'amount' => self::AMOUNT,
            'authority' => $trackId, 'status' => Payment::STATUS_PENDING,
        ]);

        return $order;
    }

    private function hitCallback(string $trackId, string $success = '1')
    {
        return $this->get(route('checkout.callback', ['method' => 'zibal']).'?'.http_build_query(['trackId' => $trackId, 'success' => $success]));
    }

    private function verified(int $result = 100, int $rial = self::AMOUNT * 10): array
    {
        return ['result' => $result, 'amount' => $rial, 'refNumber' => 778899, 'cardNumber' => '6037**1234'];
    }

    public function test_request_sends_the_order_number(): void
    {
        Http::fake(['*' => Http::response(['result' => 100, 'trackId' => 123])]);
        $gw = new \App\Services\Payment\ZibalGateway('m-test');

        $gw->request(1000, 'https://x.test/cb', 'd', ['order_id' => 'RC-1001']);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/request') && $r['orderId'] === 'RC-1001');
    }

    public function test_verified_payment_finalizes_the_order(): void
    {
        Http::fake([self::VERIFY => Http::response($this->verified()), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1001');

        $this->hitCallback('1001')->assertRedirect(route('checkout.success', $order->number));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame('778899', $order->payment->fresh()->ref_id);
    }

    public function test_cancel_at_the_gateway_fails_without_verifying(): void
    {
        Http::fake();
        $order = $this->pendingOrder('1002');

        $this->hitCallback('1002', '0')->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_FAILED, $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_not_paid_fails_the_order(): void
    {
        Http::fake([self::VERIFY => Http::response(['result' => 202]), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1003');

        $this->hitCallback('1003')->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_FAILED, $order->fresh()->status);
    }

    public function test_amount_mismatch_is_never_a_sale(): void
    {
        Http::fake([self::VERIFY => Http::response($this->verified(100, 1000)), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1004');

        $this->hitCallback('1004')->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_FAILED, $order->fresh()->status);
        $this->assertTrue($order->payment->fresh()->meta['amount_mismatch']);
    }

    public function test_lost_verify_settled_per_inquiry_finalizes(): void
    {
        Http::fake([
            self::VERIFY => Http::failedConnection(),
            self::INQUIRY => Http::response(['result' => 100, 'status' => 1, 'amount' => self::AMOUNT * 10, 'refNumber' => 5555]),
            '*' => Http::response([], 200),
        ]);
        $order = $this->pendingOrder('1005');

        $this->hitCallback('1005')->assertRedirect(route('checkout.success', $order->number));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame('5555', $order->payment->fresh()->ref_id);
    }

    public function test_lost_verify_paid_unverified_is_verified_again(): void
    {
        Http::fake([
            self::VERIFY => Http::sequence()->pushFailedConnection()->push($this->verified(201)),
            self::INQUIRY => Http::response(['result' => 100, 'status' => 2]),
            '*' => Http::response([], 200),
        ]);
        $order = $this->pendingOrder('1006');

        $this->hitCallback('1006')->assertRedirect(route('checkout.success', $order->number));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_no_answer_keeps_the_order_pending_and_a_retry_finalizes_it(): void
    {
        Http::fake([
            self::VERIFY => Http::sequence()->pushFailedConnection()->pushFailedConnection()->push($this->verified(201)),
            self::INQUIRY => Http::response(['result' => 100, 'status' => 2]),
            '*' => Http::response([], 200),
        ]);
        $order = $this->pendingOrder('1007');

        $this->hitCallback('1007')->assertRedirect(route('checkout.failed', $order->number));

        // Not failed: the money may have settled.
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $payment = $order->payment->fresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertNotEmpty($payment->meta['verify_unknown']);

        // The failed page offers to check again, at the same callback URL.
        $this->withoutVite()->actingAs($order->user)->get(route('checkout.failed', $order->number))
            ->assertOk()->assertSee(e($payment->meta['retry_url']), false);

        $this->hitCallback('1007')->assertRedirect(route('checkout.success', $order->number));
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_inquiry_down_keeps_the_order_pending(): void
    {
        Http::fake([self::VERIFY => Http::failedConnection(), self::INQUIRY => Http::failedConnection(), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1008');

        $this->hitCallback('1008')->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_second_callback_does_not_finalize_twice(): void
    {
        Http::fake([self::VERIFY => Http::response($this->verified()), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1009');

        $this->hitCallback('1009')->assertRedirect(route('checkout.success', $order->number));
        $this->hitCallback('1009')->assertRedirect(route('checkout.success', $order->number));

        // One verify only: the second callback saw the order paid under the lock.
        $verifies = collect(Http::recorded())->filter(fn ($p) => $p[0]->url() === self::VERIFY)->count();
        $this->assertSame(1, $verifies);
    }

    public function test_canceled_order_is_never_finalized(): void
    {
        Http::fake([self::VERIFY => Http::response($this->verified()), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1010');
        $order->update(['status' => Order::STATUS_CANCELED]);

        $this->hitCallback('1010')->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_CANCELED, $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_not_approved_after_an_unclear_verify_still_verifies(): void
    {
        Http::fake([self::VERIFY => Http::response($this->verified(201)), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1011');
        $order->payment->update(['meta' => ['verify_unknown' => true]]);

        $this->hitCallback('1011', '0')->assertRedirect(route('checkout.success', $order->number));

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_inquiry_internal_error_keeps_the_order_pending(): void
    {
        Http::fake([
            self::VERIFY => Http::failedConnection(),
            self::INQUIRY => Http::response(['result' => 100, 'status' => -2]),
            '*' => Http::response([], 200),
        ]);
        $order = $this->pendingOrder('1012');

        $this->hitCallback('1012')->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertTrue($order->payment->fresh()->meta['verify_unknown']);
    }
}

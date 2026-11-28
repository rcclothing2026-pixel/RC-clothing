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
        $user = User::factory()->create(['phone' => '0912'.str_pad($trackId, 7, '0', STR_PAD_LEFT)]);
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

    public function test_zibal_decline_after_an_unclear_verify_is_final(): void
    {
        // Zibal answers 201 on a repeat verify, so a 202 now proves the money never settled.
        Http::fake([self::VERIFY => Http::response(['result' => 202]), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1013');
        $order->payment->update(['meta' => ['verify_unknown' => true, 'retry_url' => 'https://x.test/r']]);

        $this->hitCallback('1013')->assertRedirect(route('checkout.failed', $order->number));

        $this->assertSame(Order::STATUS_FAILED, $order->fresh()->status);
        $this->assertSame(Payment::STATUS_FAILED, $order->payment->fresh()->status);
    }

    private function expectAlerts(int $times): void
    {
        $this->mock(\App\Services\Telegram\TelegramNotifier::class)
            ->shouldReceive('notifyAdmins')->times($times)->andReturn(1);
    }

    public function test_another_gateways_token_is_not_found(): void
    {
        Http::fake();
        $order = $this->pendingOrder('1014');
        $order->payment->update(['gateway' => 'zarinpal']);

        $this->hitCallback('1014')->assertRedirect(route('home'));

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_closed_order_with_an_unclear_verify_is_never_shown_as_not_charged(): void
    {
        Http::fake();
        $order = $this->pendingOrder('1015');
        $order->payment->update(['meta' => ['verify_unknown' => true, 'retry_url' => 'https://x.test/r']]);
        $order->update(['status' => Order::STATUS_CANCELED]);

        $this->hitCallback('1015')->assertRedirect(route('checkout.failed', $order->number));

        $this->withoutVite()->actingAs($order->user)->get(route('checkout.failed', $order->number))
            ->assertOk()->assertDontSee(__('was not paid. No amount has been charged to your account.'));
    }

    public function test_closed_order_alert_goes_out_once_and_never_for_a_refund(): void
    {
        Http::fake();
        $this->expectAlerts(1);
        $order = $this->pendingOrder('1018');
        $order->payment->update(['meta' => ['verify_unknown' => true]]);
        $order->update(['status' => Order::STATUS_CANCELED]);
        $refunded = $this->pendingOrder('1019');
        $refunded->payment->update(['status' => Payment::STATUS_REFUNDED, 'meta' => ['verify_unknown' => true]]);
        $refunded->update(['status' => Order::STATUS_CANCELED]);

        $this->hitCallback('1018');
        $this->hitCallback('1018'); // back button: no second alert
        $this->hitCallback('1019'); // staff already refunded: no alert

        Http::assertNothingSent();
    }

    /** Place a real checkout covered in full by gift card G-F; $race runs as the order is created. */
    private function placeCoveredByCard(\Closure $race): Order
    {
        $user = User::factory()->create(['phone' => '09120005566']);
        $address = \App\Models\Address::create([
            'user_id' => $user->id, 'recipient_name' => 'تست', 'phone' => '09120005566',
            'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان نمونه',
        ]);
        $shipping = \App\Models\ShippingMethod::first();
        $shipping->update(['cost_on_delivery' => true]);
        $variant = \App\Models\Product::has('variants')->first()->variants()->where('stock_qty', '>', 0)->first();
        \App\Models\GiftCard::create(['code' => 'G-F', 'initial_balance' => 100000000, 'balance' => 100000000, 'is_active' => true]);

        $this->actingAs($user);
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post(route('cart.gift.apply'), ['code' => 'G-F']);
        Order::created($race); // another order spends the card (or the code) meanwhile
        $this->post(route('checkout.place'), [
            'address_id' => (string) $address->id, 'shipping_method_id' => $shipping->id, 'payment_method' => 'zibal',
        ])->assertRedirect(route('cart.index'));

        return Order::where('user_id', $user->id)->latest()->first();
    }

    public function test_card_only_order_whose_card_was_spent_meanwhile_is_no_sale(): void
    {
        Http::fake();
        $order = $this->placeCoveredByCard(fn () => \App\Models\GiftCard::where('code', 'G-F')->update(['balance' => 10]));

        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertSame(Payment::STATUS_FAILED, $order->payment->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(10, \App\Models\GiftCard::where('code', 'G-F')->value('balance'));
    }

    public function test_card_only_order_whose_coupon_ran_out_meanwhile_is_no_sale(): void
    {
        Http::fake();
        $coupon = \App\Models\Coupon::create(['code' => 'C-F', 'type' => 'fixed', 'value' => 10, 'usage_limit' => 1, 'used_count' => 0, 'is_active' => true]);
        $order = $this->placeCoveredByCard(function (Order $o) use ($coupon) {
            $o->updateQuietly(['coupon_code' => 'C-F']);
            $coupon->update(['used_count' => 1]);
        });

        $this->assertSame(Order::STATUS_FAILED, $order->status);
        $this->assertSame(1, $coupon->fresh()->used_count);
        // Rolled back whole: the card was not drawn down either.
        $this->assertSame(100000000, \App\Models\GiftCard::where('code', 'G-F')->value('balance'));
    }

    public function test_paid_order_never_overdraws_a_gift_card_or_a_coupon_limit(): void
    {
        Http::fake([self::VERIFY => Http::response($this->verified()), '*' => Http::response([], 200)]);
        $this->expectAlerts(1); // one alert naming both
        $order = $this->pendingOrder('1016');
        // Another order already spent the card and the last coupon use.
        $card = \App\Models\GiftCard::create(['code' => 'G-1', 'initial_balance' => 1000, 'balance' => 100, 'is_active' => true]);
        $coupon = \App\Models\Coupon::create(['code' => 'C-1', 'type' => 'fixed', 'value' => 10, 'usage_limit' => 1, 'used_count' => 1]);
        $order->update(['gift_card_code' => 'G-1', 'gift_used' => 500, 'coupon_code' => 'C-1']);

        $this->hitCallback('1016')->assertRedirect(route('checkout.success', $order->number));

        // Paid (the money moved), but the card and the coupon are not driven past their limit,
        // and what was left on the card cannot be spent again.
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(0, $card->fresh()->balance);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_gift_card_and_coupon_are_drawn_down_normally(): void
    {
        Http::fake([self::VERIFY => Http::response($this->verified()), '*' => Http::response([], 200)]);
        $order = $this->pendingOrder('1017');
        $card = \App\Models\GiftCard::create(['code' => 'G-2', 'initial_balance' => 1000, 'balance' => 1000, 'is_active' => true]);
        $coupon = \App\Models\Coupon::create(['code' => 'C-2', 'type' => 'fixed', 'value' => 10, 'usage_limit' => 5, 'used_count' => 1]);
        $order->update(['gift_card_code' => 'G-2', 'gift_used' => 400, 'coupon_code' => 'C-2']);

        $this->hitCallback('1017')->assertRedirect(route('checkout.success', $order->number));

        $this->assertSame(600, $card->fresh()->balance);
        $this->assertSame(2, $coupon->fresh()->used_count);
    }

    public function test_zarinpal_and_snapppay_only_leave_no_answer_unknown(): void
    {
        Http::fake([
            '*zarinpal.com/*' => Http::sequence()->push([], 500)->pushFailedConnection()
                ->push(['errors' => ['code' => -51, 'message' => 'failed']], 400),
            'sp.test/api/online/v1/oauth/token' => Http::response(['access_token' => 't']),
            'sp.test/api/online/payment/v1/verify' => Http::sequence()->push(['successful' => true, 'response' => []])
                ->push(['successful' => false], 400),
            'sp.test/api/online/payment/v1/settle' => Http::response([], 503),
        ]);
        $zp = new \App\Services\Payment\ZarinpalGateway('m-test', false);
        $sp = new \App\Services\Payment\SnappPayGateway('https://sp.test', 'c', 's', 'u', 'p');

        $this->assertTrue($zp->verify('A1', 1000)['unknown'] ?? false);   // HTTP 500
        $this->assertTrue($zp->verify('A1', 1000)['unknown'] ?? false);   // no answer
        $declined = $zp->verify('A1', 1000);                               // a clear refusal
        $this->assertFalse($declined['success']);
        $this->assertArrayNotHasKey('unknown', $declined);

        $this->assertTrue($sp->verify('T1', 1000)['unknown'] ?? false);   // settle HTTP 503
        $declined = $sp->verify('T1', 1000);                               // verify refused
        $this->assertFalse($declined['success']);
        $this->assertArrayNotHasKey('unknown', $declined);
    }
}

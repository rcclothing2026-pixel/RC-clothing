<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** StoqS «امتیاز با تخفیف دیگر جمع نشود»: club points are not spent beside another discount. */
class LoyaltyStackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Place an order asking for 100 points (100 × 1000 toman) and return its loyalty discount,
     * or null when checkout refused the points (no order, the customer is told why).
     */
    private function placeWithPoints(array $loyalty, ?string $coupon = null, bool $onSale = false): ?int
    {
        config(['stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k']);
        Http::fake([
            'stoqs.test/api/v1/customers?*' => Http::response([
                'ok' => true,
                'customer' => ['id' => 'c1', 'loyalty_points' => 100],
                'loyalty' => $loyalty + ['toman_per_point' => 1000, 'min_redeem_points' => 0],
            ]),
            '*' => Http::response(['id' => 'x', 'data' => ['id' => 'x']]),
        ]);
        $user = User::factory()->create(['phone' => '09120003355']);
        $address = Address::create([
            'user_id' => $user->id, 'recipient_name' => 'تست', 'phone' => '09120003355',
            'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان نمونه',
        ]);
        $shipping = ShippingMethod::first();
        $product = Product::has('variants')->get()
            ->first(fn ($p) => \App\Services\Cart\Cart::isDiscountable($p) && $p->variants()->where('stock_qty', '>', 0)->exists());
        $variant = $product->variants()->where('stock_qty', '>', 0)->first();
        // query update: no model events, so no search-index sync in tests
        Product::whereKey($product->id)->update(['compare_at_price' => $onSale ? $variant->effectivePrice() + 500000 : null]);

        $this->actingAs($user);
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect();
        if ($coupon) {
            Coupon::create(['code' => $coupon, 'type' => 'fixed', 'value' => 10000, 'is_active' => true]);
            $this->post(route('cart.coupon.apply'), ['code' => $coupon]);
        }
        $this->get(route('checkout.index'))->assertOk();
        $res = $this->post(route('checkout.place'), [
            'address_id' => (string) $address->id,
            'shipping_method_id' => $shipping->id,
            'payment_method' => 'zarinpal',
            'redeem_points' => 100,
        ])->assertRedirect();
        if (str_contains((string) session('error'), 'امتیاز باشگاه با تخفیف دیگر جمع نمی‌شود')) {
            $this->assertSame(0, Order::where('user_id', $user->id)->count(), 'a refused redemption places no order');

            return null;
        }

        $order = Order::where('user_id', $user->id)->latest()->firstOrFail();
        if ($coupon) {
            $this->assertSame($coupon, $order->coupon_code, 'the coupon must really be on the order');
        }

        return (int) $order->loyalty_discount;
    }

    public function test_points_and_a_code_stack_while_the_setting_is_off(): void
    {
        $this->assertSame(100000, $this->placeWithPoints([], 'TENK'));
    }

    public function test_a_code_blocks_the_points_when_the_setting_is_on(): void
    {
        $this->assertNull($this->placeWithPoints(['redeem_alone' => 1, 'redeem_alone_sale' => 1], 'TENK'));
    }

    public function test_no_other_discount_keeps_the_points(): void
    {
        $this->assertSame(100000, $this->placeWithPoints(['redeem_alone' => 1, 'redeem_alone_sale' => 1]));
    }

    public function test_a_sale_price_blocks_the_points_only_when_it_counts(): void
    {
        $this->assertNull($this->placeWithPoints(['redeem_alone' => 1, 'redeem_alone_sale' => 1], null, true));
    }

    public function test_a_sale_price_is_ignored_when_the_admin_says_so(): void
    {
        $this->assertSame(100000, $this->placeWithPoints(['redeem_alone' => 1, 'redeem_alone_sale' => 0], null, true));
    }

    public function test_checkout_page_says_why_the_points_are_off(): void
    {
        config(['stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k']);
        Http::fake([
            'stoqs.test/api/v1/customers?*' => Http::response([
                'ok' => true, 'customer' => ['id' => 'c1', 'loyalty_points' => 100],
                'loyalty' => ['toman_per_point' => 1000, 'min_redeem_points' => 0, 'redeem_alone' => 1],
            ]),
            '*' => Http::response(['id' => 'x', 'data' => ['id' => 'x']]),
        ]);
        $user = User::factory()->create(['phone' => '09120003366']);
        $product = Product::has('variants')->get()
            ->first(fn ($p) => \App\Services\Cart\Cart::isDiscountable($p) && $p->variants()->where('stock_qty', '>', 0)->exists());
        $variant = $product->variants()->where('stock_qty', '>', 0)->first();
        $this->actingAs($user);
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        Coupon::create(['code' => 'TENK', 'type' => 'fixed', 'value' => 10000, 'is_active' => true]);
        $this->post(route('cart.coupon.apply'), ['code' => 'TENK']);
        $this->get(route('checkout.index'))->assertOk()
            ->assertSee('امتیاز باشگاه با تخفیف دیگر جمع نمی‌شود', false)
            ->assertDontSee('name="redeem_points"', false);
    }
}

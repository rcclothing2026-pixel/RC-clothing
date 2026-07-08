<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\IntegrationEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        // Seeded gateways have no credentials → DevGateway simulation is used.
    }

    public function test_login_via_sms_otp_creates_user(): void
    {
        $code = app(OtpService::class)->send('09121112233');
        $this->assertNotNull($code);

        $this->post(route('login.verify'), ['phone' => '09121112233', 'code' => $code])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['phone' => '09121112233']);
    }

    public function test_purchase_reports_sale_income_and_customer(): void
    {
        $user = User::factory()->create(['phone' => '09120001122']);
        $address = Address::create([
            'user_id' => $user->id, 'recipient_name' => 'تست', 'phone' => '09120001122',
            'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان نمونه',
        ]);
        $shipping = ShippingMethod::first();
        $variant = Product::has('variants')->first()
            ->variants()->where('stock_qty', '>', 0)->first();

        $this->actingAs($user);

        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect();

        $place = $this->post(route('checkout.place'), [
            'address_id' => (string) $address->id,
            'shipping_method_id' => $shipping->id,
            'payment_method' => 'zarinpal',
        ])->assertRedirect();

        // Follow the dev callback redirect.
        $this->get($place->headers->get('Location'))->assertRedirect();

        $order = Order::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertSame(Order::STATUS_PAID, $order->status);

        $this->assertSame(1, IntegrationEvent::where('type', IntegrationEvent::TYPE_SALE_CREATED)->count());
        $this->assertSame(1, IntegrationEvent::where('type', IntegrationEvent::TYPE_INCOME_RECORDED)->count());
        // customer_upserted is created on user creation (via UserObserver) and on order payment.
        $this->assertSame(2, IntegrationEvent::where('type', IntegrationEvent::TYPE_CUSTOMER_UPSERTED)->count());

        $customer = IntegrationEvent::where('type', IntegrationEvent::TYPE_CUSTOMER_UPSERTED)
            ->orderBy('id', 'desc')
            ->first();
        $this->assertNotNull($customer, 'Expected a customer_upserted event from reportPaidOrder');
        $this->assertArrayHasKey('lifetime', $customer->payload);
        $this->assertArrayHasKey('address', $customer->payload);
        $this->assertSame(1, $customer->payload['lifetime']['orders_count']);
    }

    public function test_each_gateway_completes_a_dev_purchase(): void
    {
        \App\Models\PaymentMethod::query()->update(['is_active' => true]);

        foreach (['zarinpal', 'zibal', 'snapppay'] as $gatewayKey) {
            $user = User::factory()->create();
            $address = Address::create([
                'user_id' => $user->id, 'recipient_name' => 'تست', 'phone' => '09120000001',
                'province' => 'تهران', 'city' => 'تهران', 'line' => 'نمونه',
            ]);
            $variant = Product::has('variants')->get()
                ->flatMap->variants->firstWhere('stock_qty', '>', 0);

            $this->actingAs($user);
            $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);

            $place = $this->post(route('checkout.place'), [
                'address_id' => (string) $address->id,
                'shipping_method_id' => ShippingMethod::first()->id,
                'payment_method' => $gatewayKey,
            ])->assertRedirect();

            $this->get($place->headers->get('Location'))->assertRedirect();

            $order = Order::where('user_id', $user->id)->latest()->first();
            $this->assertSame(Order::STATUS_PAID, $order->status, "gateway {$gatewayKey} should complete");
            $this->assertSame($gatewayKey, $order->payment->gateway);
        }
    }

    public function test_checkout_and_payment_settings_pages_render(): void
    {
        // Checkout page renders with a cart.
        $user = User::factory()->create();
        $variant = Product::has('variants')->first()->variants()->first();
        $this->actingAs($user);
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->get(route('checkout.index'))->assertOk()->assertSee('روش پرداخت');

        // Admin payment-gateway settings page renders.
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->get(route('admin.settings.payments'))
            ->assertOk()
            ->assertSee('زرین‌پال')
            ->assertSee('زیبال')
            ->assertSee('اسنپ‌پی');
    }

    public function test_admin_area_is_guarded(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();

        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('admin.dashboard'))->assertOk();
    }
}

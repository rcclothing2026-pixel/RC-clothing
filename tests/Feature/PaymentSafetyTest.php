<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A payment method without credentials must never approve an order on a live shop. */
class PaymentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function unconfigured(): PaymentMethod
    {
        config(['shop.dev_gateway' => false]);

        return PaymentMethod::create(['key' => 'zibal', 'label' => 'زیبال', 'is_active' => true, 'sandbox' => false, 'config' => []]);
    }

    public function test_an_unconfigured_gateway_is_not_usable_and_cannot_be_built(): void
    {
        $method = $this->unconfigured();
        $factory = app(PaymentGatewayFactory::class);

        $this->assertFalse($factory->usable($method));
        $this->expectException(\RuntimeException::class);
        $factory->make($method);
    }

    public function test_a_simulator_callback_is_refused(): void
    {
        $method = $this->unconfigured();

        $this->get(route('checkout.callback', ['method' => $method]).'?identifier=DEV-ABC&approved=1')
            ->assertRedirect(route('home'));
    }

    public function test_a_configured_gateway_stays_usable(): void
    {
        config(['shop.dev_gateway' => false]);
        $method = PaymentMethod::create(['key' => 'zibal', 'label' => 'زیبال', 'is_active' => true, 'sandbox' => false, 'config' => ['merchant' => 'abc']]);

        $this->assertTrue(app(PaymentGatewayFactory::class)->usable($method));
    }
}

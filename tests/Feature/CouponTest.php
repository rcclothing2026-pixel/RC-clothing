<?php

namespace Tests\Feature;

use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_percent_with_cap_and_min(): void
    {
        $c = Coupon::create([
            'code' => 'WELCOME10', 'type' => 'percent', 'value' => 10,
            'min_subtotal' => 500000, 'max_discount' => 200000, 'is_active' => true,
        ]);

        $this->assertSame(100000, $c->discountFor(1000000));      // 10%
        $this->assertSame(200000, $c->discountFor(3000000));      // capped
        $this->assertFalse($c->isValidFor(400000));               // below min
        $this->assertTrue($c->isValidFor(600000));
    }

    public function test_expired_and_inactive_are_invalid(): void
    {
        $expired = Coupon::create(['code' => 'OLD', 'type' => 'fixed', 'value' => 50000, 'expires_at' => now()->subDay(), 'is_active' => true]);
        $off = Coupon::create(['code' => 'OFF', 'type' => 'fixed', 'value' => 50000, 'is_active' => false]);

        $this->assertNotNull($expired->reasonInvalidFor(1000000));
        $this->assertNotNull($off->reasonInvalidFor(1000000));
    }

    public function test_free_shipping_type(): void
    {
        $f = Coupon::create(['code' => 'FREESHIP', 'type' => 'free_shipping', 'value' => 0, 'is_active' => true]);

        $this->assertTrue($f->isFreeShipping());
        $this->assertSame(0, $f->discountFor(1000000));
    }

    public function test_usage_limit_blocks_when_exhausted(): void
    {
        $c = Coupon::create(['code' => 'ONCE', 'type' => 'percent', 'value' => 10, 'usage_limit' => 1, 'used_count' => 1, 'is_active' => true]);

        $this->assertFalse($c->isValidFor(1000000));
    }
}

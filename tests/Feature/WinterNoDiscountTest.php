<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WinterNoDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function variant(string $catSlug, int $price): ProductVariant
    {
        $cat = Category::create(['name' => $catSlug, 'slug' => $catSlug, 'is_active' => true]);
        $p = Product::create(['name' => $catSlug.' item', 'slug' => $catSlug.'-item', 'price' => $price, 'category_id' => $cat->id, 'is_active' => true]);

        return ProductVariant::create(['product_id' => $p->id, 'size' => 'L', 'price' => $price, 'stock_qty' => 5, 'is_active' => true]);
    }

    public function test_coupons_skip_winter_items_and_respect_the_ceiling(): void
    {
        config(['scout.driver' => 'collection', 'shop.no_discount_categories' => ['nym-zyp'], 'shop.max_discount_percent' => 25]);
        $tee = $this->variant('ty-shrt', 4300000);
        $halfZip = $this->variant('nym-zyp', 8900000);

        $cart = app(Cart::class);
        $cart->add($tee->id);
        $cart->add($halfZip->id);
        $this->assertSame(4300000, $cart->discountableSubtotal());

        Coupon::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $cart->applyCoupon('TEN');
        $this->assertSame(430000, $cart->combinedDiscount());      // 10% of the tee only

        Coupon::create(['code' => 'BIG', 'type' => 'fixed', 'value' => 5000000, 'is_active' => true]);
        $cart->applyCoupon('BIG');
        $this->assertSame(1075000, $cart->combinedDiscount());     // capped at 25% of the tee
    }
}

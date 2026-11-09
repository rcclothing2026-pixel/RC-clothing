<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «Use alone»: an exclusive code or rule never adds to other discounts; the larger one counts. */
class DiscountExclusiveTest extends TestCase
{
    use RefreshDatabase;

    private Cart $cart;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection', 'shop.no_discount_categories' => [], 'shop.max_discount_percent' => 25]);
        $cat = Category::create(['name' => 'tee', 'slug' => 'tee-x', 'is_active' => true]);
        $p = Product::create(['name' => 'tee', 'slug' => 'tee-x-item', 'price' => 10000000, 'category_id' => $cat->id, 'is_active' => true]);
        $v = ProductVariant::create(['product_id' => $p->id, 'size' => 'L', 'price' => 10000000, 'stock_qty' => 5, 'is_active' => true]);
        $this->cart = app(Cart::class);
        $this->cart->add($v->id);
    }

    private function rule(string $name, int $percent, bool $exclusive = false): DiscountRule
    {
        $r = DiscountRule::create(['name' => $name, 'priority' => 1, 'conditions' => [], 'actions' => [['type' => 'cart_discount_percent', 'value' => $percent]],
            'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true, 'exclusive' => $exclusive]);
        $this->cart->setRuleContext([]);   // drop the cached rule results

        return $r;
    }

    private function code(string $code, int $percent, bool $exclusive = false): void
    {
        Coupon::create(['code' => $code, 'type' => 'percent', 'value' => $percent, 'is_active' => true, 'exclusive' => $exclusive]);
        $this->assertTrue($this->cart->applyCoupon($code)['ok']);
    }

    public function test_shared_code_and_rule_add_up_as_before(): void
    {
        $this->rule('ده درصد', 10);
        $this->code('SHARE5', 5);
        $this->assertSame(1500000, $this->cart->combinedDiscount());
        $this->assertSame('SHARE5', $this->cart->appliedCoupon()?->code);
        $this->assertNull($this->cart->discountNotice());
    }

    public function test_exclusive_code_loses_to_larger_rules_and_the_customer_is_told(): void
    {
        $this->rule('ده درصد', 10);
        $this->code('SOLO8', 8, true);
        $this->assertSame(1000000, $this->cart->combinedDiscount());
        $this->assertNull($this->cart->appliedCoupon());
        $this->assertSame(0, $this->cart->discount());
        $this->assertStringContainsString('SOLO8', $this->cart->discountNotice());
    }

    public function test_exclusive_code_wins_alone_when_larger(): void
    {
        $rule = $this->rule('ده درصد', 10);
        $this->code('SOLO20', 20, true);
        $this->assertSame(2000000, $this->cart->combinedDiscount());
        $this->assertSame('SOLO20', $this->cart->appliedCoupon()?->code);
        $this->assertSame([], $this->cart->ruleDiscountBreakdown());
        $this->assertStringContainsString($rule->name, $this->cart->discountNotice());
    }

    public function test_exclusive_rule_beats_shared_rule_and_code_when_larger(): void
    {
        $this->rule('ده درصد', 10);
        $solo = $this->rule('پانزده تنها', 15, true);
        $this->code('SHARE3', 3);
        $this->assertSame(1500000, $this->cart->combinedDiscount());
        $this->assertSame([$solo->id], array_column($this->cart->ruleDiscountBreakdown(), 'rule_id'));
        $this->assertNull($this->cart->appliedCoupon());
        $this->assertStringContainsString('SHARE3', $this->cart->discountNotice());
    }
}

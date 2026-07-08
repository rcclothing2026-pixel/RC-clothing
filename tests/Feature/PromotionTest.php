<?php
namespace Tests\Feature;
use App\Models\Promotion;
use App\Models\ProductVariant;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PromotionTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); }
    public function test_cart_percent(): void {
        Promotion::create(['name'=>'۱۰٪','type'=>'cart_percent','value'=>10,'is_active'=>true]);
        $v = ProductVariant::first();
        $lines = collect([['variant'=>$v,'quantity'=>2,'unit_price'=>500000,'line_total'=>1000000]]);
        $best = app(PromotionEngine::class)->bestFor($lines, 1000000);
        $this->assertSame(100000, $best['discount']);
    }
    public function test_buy_x_get_y(): void {
        Promotion::create(['name'=>'۲بخر۱','type'=>'buy_x_get_y','buy_qty'=>2,'get_qty'=>1,'is_active'=>true]);
        $v = ProductVariant::first();
        $lines = collect([['variant'=>$v,'quantity'=>3,'unit_price'=>200000,'line_total'=>600000]]);
        $best = app(PromotionEngine::class)->bestFor($lines, 600000);
        $this->assertSame(200000, $best['discount']); // 1 free unit
    }
}

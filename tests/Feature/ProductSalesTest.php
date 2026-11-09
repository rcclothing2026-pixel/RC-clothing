<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Item-only product sales, finished costs, the margin floor and the outlet, as NJ Focus drives them. */
class ProductSalesTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sales-key';

    /** @var array<string, Product> */
    private array $p = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ig_catalog.key' => self::KEY, 'scout.driver' => 'collection', 'shop.no_discount_categories' => ['winter-x'],
            'shop.max_discount_percent' => 25, 'shop.min_margin_percent' => 10, 'shop.outlet_max_percent' => 30]);
        $tee = Category::create(['name' => 'tee', 'slug' => 'tee-x', 'is_active' => true]);
        $winter = Category::create(['name' => 'winter', 'slug' => 'winter-x', 'is_active' => true]);
        foreach (['a' => [4300000, $tee], 'b' => [4300000, $tee], 'c' => [5000000, $tee], 'w' => [10000000, $winter]] as $k => [$price, $cat]) {
            $this->p[$k] = Product::create(['name' => "item $k", 'slug' => "item-$k", 'price' => $price, 'category_id' => $cat->id, 'is_active' => true]);
            ProductVariant::create(['product_id' => $this->p[$k]->id, 'size' => 'L', 'price' => $price, 'stock_qty' => 9, 'is_active' => true]);
        }
    }

    private function api(string $method, string $path, array $data = [])
    {
        return $this->withHeaders(['X-Catalog-Key' => self::KEY])->json($method, '/api/campaigns'.$path, $data);
    }

    /** Save the current plan plus $sales (and costs/outlet) the way NJ Focus does. */
    private function save(array $sales, array $extra = [])
    {
        $plan = $this->api('GET', '/editor')->assertOk()->json('plan');
        $plan['sales'] = $sales;

        return $this->api('PUT', '/editor', ['plan' => $plan] + $extra);
    }

    private function cart(string ...$keys): Cart
    {
        $cart = app(Cart::class);
        $cart->clear();
        foreach ($keys as $k) {
            $cart->add($this->p[$k]->variants()->first()->id);
        }
        $cart->setRuleContext([]);

        return $cart;
    }

    private function sale(array $over = []): array
    {
        return $over + ['key' => 'bf-sale', 'label' => 'بلک فرایدی', 'pct' => 20, 'count' => 'all', 'exclusive' => false,
            'products' => [['slug' => 'item-a', 'pct' => null], ['slug' => 'item-b', 'pct' => 10]], 'ladder' => [['n' => 2, 'pct' => 5]]];
    }

    public function test_a_sale_becomes_item_only_rules_and_discounts_only_the_chosen_products(): void
    {
        $this->save([$this->sale()], ['costs' => ['item-a' => 2600000]])->assertOk();

        $this->assertTrue(DiscountRule::where('description', 'campaign:bf-sale--p20')->value('is_active'));
        $this->assertTrue(DiscountRule::where('description', 'campaign:bf-sale--p10')->value('is_active'));
        $this->assertTrue(DiscountRule::where('description', 'campaign:bf-sale--n2')->value('is_active'));
        $this->assertSame(2600000, ProductCost::where('product_id', $this->p['a']->id)->value('cost'));

        // a (20%) + c (not in the sale): 2 pieces, so the 5% tier also lands, on a only.
        $this->assertSame(860000 + 215000, $this->cart('a', 'c')->combinedDiscount());
        // c alone: nothing.
        $this->assertSame(0, $this->cart('c')->combinedDiscount());

        $e = $this->api('GET', '/editor')->json();
        $this->assertSame('item-a', $e['plan']['sales'][0]['products'][0]['slug']);
        $this->assertSame(2600000, collect($e['catalog']['products'])->firstWhere('slug', 'item-a')['cost']);
        $this->assertTrue(collect($e['catalog']['products'])->firstWhere('slug', 'item-w')['winter']);
    }

    public function test_a_sale_that_breaks_the_margin_or_touches_winter_is_refused(): void
    {
        $r = $this->save([$this->sale()], ['costs' => ['item-a' => 3500000]])->assertStatus(422);
        $this->assertStringContainsString('حاشیه', implode(' ', $r->json('errors')));

        $r = $this->save([$this->sale(['products' => [['slug' => 'item-w', 'pct' => 10]]])])->assertStatus(422);
        $this->assertStringContainsString('زمستانی', implode(' ', $r->json('errors')));
        $this->assertSame(0, DiscountRule::where('description', 'like', 'campaign:bf-sale%')->count());
    }

    public function test_the_cart_never_discounts_below_the_cost_floor(): void
    {
        ProductCost::create(['product_id' => $this->p['a']->id, 'cost' => 3600000]);
        DiscountRule::create(['name' => 'all 25', 'priority' => 1, 'conditions' => [], 'actions' => [['type' => 'cart_discount_percent', 'value' => 25]],
            'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true]);
        // floor = ceil(3.6M / 0.9) = 4,000,000, so only 300,000 of the 1,075,000 may go.
        $this->assertSame(300000, $this->cart('a')->combinedDiscount());
        // a line without a cost keeps its full room.
        $this->assertSame(300000 + 1250000, $this->cart('a', 'c')->combinedDiscount());
    }

    public function test_a_use_alone_sale_counts_as_one_against_other_discounts(): void
    {
        $this->save([$this->sale(['exclusive' => true, 'pct' => 10, 'products' => [['slug' => 'item-a', 'pct' => null], ['slug' => 'item-b', 'pct' => null]]])])->assertOk();
        DiscountRule::create(['name' => 'shared 12', 'priority' => 1, 'conditions' => [], 'actions' => [['type' => 'cart_discount_percent', 'value' => 12]],
            'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true]);
        // sale: (10% + 5% tier) of 8.6M = 1,290,000 beats the shared 12% (1,032,000); both sale rules count.
        $cart = $this->cart('a', 'b');
        $this->assertSame(1290000, $cart->combinedDiscount());
        $this->assertCount(2, $cart->ruleDiscountBreakdown());
        $this->assertStringContainsString('بلک فرایدی', (string) $cart->discountNotice());
    }

    public function test_outlet_is_set_from_the_editor_and_respects_the_margin(): void
    {
        $r = $this->save([], ['costs' => ['item-c' => 3500000], 'outlet' => ['item-c' => 30]])->assertStatus(422);
        $this->assertStringContainsString('اوتلت', implode(' ', $r->json('errors')));

        $this->save([], ['outlet' => ['item-c' => 10, 'item-w' => 0]])->assertOk();
        $this->assertSame(4500000, (int) $this->p['c']->fresh()->price);
        $this->assertSame(10, collect($this->api('GET', '/editor')->json('catalog.products'))->firstWhere('slug', 'item-c')['outlet']);

        $this->save([], ['outlet' => ['item-c' => 0]])->assertOk();
        $this->assertSame(5000000, (int) $this->p['c']->fresh()->price);

        $this->save([], ['outlet' => ['item-w' => 10]])->assertStatus(422);
    }

    public function test_costs_api_reads_and_writes_by_slug(): void
    {
        $this->api('PUT', '/costs', ['costs' => ['item-a' => 2600000, 'item-b' => '2700000'], 'source' => 'stoqs'])->assertOk()
            ->assertJsonPath('costs.item-a', 2600000)->assertJsonPath('min_margin', 10);
        $this->assertSame('stoqs', ProductCost::where('product_id', $this->p['b']->id)->value('source'));
        $this->api('PUT', '/costs', ['costs' => ['item-a' => null]])->assertOk()->assertJsonMissingPath('costs.item-a');
        $this->api('PUT', '/costs', ['costs' => ['nope' => 1]])->assertStatus(422);
    }
}

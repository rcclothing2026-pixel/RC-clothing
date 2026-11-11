<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\IntegrationEvent;
use App\Models\Order;
use App\Models\OutletItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Cart\Cart;
use App\Services\StockKeeping\CatalogImporter;
use App\Services\StockKeeping\HttpStockKeepingClient;
use App\Services\StockKeeping\StoqsPromotions;
use App\Services\StockKeeping\StoqsSites;
use App\Support\CampaignPlan;
use App\Support\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * StoqS manages this site's promotions (Promotions API v2): window prices per size with the
 * list price struck through, StoqS's rules and codes in the cart, POST /promotions/evaluate at
 * checkout (eval «local» when StoqS is down), the outlet and the plan's discount sections
 * read-only, webhook / minute pulls with If-None-Match, acks and shop-set size prices.
 */
class StoqsManagedModeTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k',
            'stockkeeping.webhook_secret' => self::SECRET, 'stockkeeping.fulfillment_location' => 'warehouse:1',
            'app.url' => 'https://shop.test', 'scout.driver' => null,
        ]);
        Http::preventStrayRequests();
        // Where web traffic drives the scheduler (RunDueSchedule), keep it out of these
        // requests: the scheduled StoqS jobs would make calls of their own.
        \Illuminate\Support\Facades\Cache::lock('scheduler:web-kick', 600)->get();
        StoqsPromotions::forget();
        $this->product = Product::create([
            'name' => 'مانتو', 'slug' => 'manto-1', 'price' => 1_000_000, 'stockkeeping_id' => '1', 'is_active' => true,
        ]);
        ProductVariant::create(['product_id' => $this->product->id, 'sku' => '1001', 'size' => 'S', 'stock_qty' => 5, 'is_active' => true, 'stockkeeping_variant_id' => 11]);
        ProductVariant::create(['product_id' => $this->product->id, 'sku' => '1002', 'size' => 'M', 'stock_qty' => 5, 'is_active' => true, 'stockkeeping_variant_id' => 12]);
    }

    /** A /promotions payload: campaign 3 (plan 7 v4) prices S at 800,000 and M at 750,000 for an hour either side of now. */
    private function payload(array $over = []): array
    {
        $from = now()->subHour();
        $to = now()->addHour();

        return array_replace([
            'ok' => true, 'schema' => 2, 'version' => 4, 'managed' => true, 'promo_source' => 'stoqs', 'price_level' => 'size',
            'settings' => ['max_discount_percent' => 50, 'round_step' => 1000, 'round_mode' => 'down', 'round_offset' => 0, 'no_discount_category_ids' => []],
            'plans' => [['id' => 7, 'campaign_id' => 3, 'kind' => 'campaign', 'version' => 4, 'active' => true]],
            'windows' => [[
                'id' => 'c3', 'source' => 'campaign', 'campaign_id' => 3, 'plan_id' => 7, 'version' => 4, 'pct' => 20,
                'from' => $from->copy()->setTimezone('Asia/Tehran')->toAtomString(), 'to' => $to->copy()->setTimezone('Asia/Tehran')->toAtomString(),
                'from_utc' => $from->copy()->utc()->toAtomString(), 'to_utc' => $to->copy()->utc()->toAtomString(),
            ]],
            'variants' => [
                ['variant_ids' => [11], 'product_id' => 1, 'list_price' => 1_000_000, 'floor_price' => 500_000, 'prices' => [['c3', 800_000]], 'price_owner' => 'stoqs'],
                ['variant_ids' => [12], 'product_id' => 1, 'list_price' => 1_000_000, 'floor_price' => 500_000, 'prices' => [['c3', 750_000]], 'price_owner' => 'stoqs'],
            ],
            'outlet' => [],
            'rules' => [[
                'id' => 31, 'plan_id' => 7, 'label' => 'ده درصد', 'priority' => 1, 'conditions' => [],
                'actions' => [['type' => 'cart_discount_percent', 'value' => 10]],
                'apply_mode' => 'all', 'stack_mode' => 'all', 'exclusive' => false, 'enabled' => 1, 'channels' => 'web,brand,stores',
            ]],
            'codes' => [['id' => 41, 'code' => 'STQ5', 'type' => 'toman', 'value' => 50_000, 'enabled' => 1, 'individual_use' => false, 'channels' => 'web,brand,stores']],
            'categories' => [],
        ], $over);
    }

    private function manage(array $payload, array $row = []): void
    {
        DB::table('stoqs_promotions')->updateOrInsert(['id' => 1], $row + [
            'managed' => (bool) $payload['managed'], 'version' => $payload['version'], 'etag' => '"v'.$payload['version'].'"',
            'payload' => json_encode($payload), 'pulled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        StoqsPromotions::forget();
    }

    private function variant(string $size): ProductVariant
    {
        return ProductVariant::where('product_id', $this->product->id)->where('size', $size)->firstOrFail();
    }

    private function webhook(string $event, array $data)
    {
        $raw = json_encode(['event' => $event, 'brand_id' => 2, 'event_id' => 'e-'.uniqid(), 'occurred_at' => now()->toAtomString(), 'data' => $data]);

        return $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
        ], $raw);
    }

    /** The HTTP client as AppServiceProvider builds it (without the resilient wrapper). */
    private function client(): HttpStockKeepingClient
    {
        return new HttpStockKeepingClient(baseUrl: 'https://stoqs.test', apiKey: 'k', locations: [], fulfillmentLocation: 'warehouse:1');
    }

    private function cart(): Cart
    {
        app()->forgetInstance(Cart::class);
        StoqsPromotions::forget();

        return app(Cart::class);
    }

    // ── prices ──────────────────────────────────────────────────────────────

    public function test_window_prices_each_size_with_the_list_price_struck(): void
    {
        $this->manage($this->payload());
        StoqsPromotions::apply();

        $p = $this->product->fresh();
        $this->assertSame(750_000, $p->price, 'the product shows its lowest size price');
        $this->assertSame(1_000_000, $p->compare_at_price);
        if (Schema::hasColumn('products', 'stoqs_discount')) {
            $this->assertTrue((bool) $p->stoqs_discount);
        }
        $this->assertSame(800_000, $this->variant('S')->promo_price);
        $this->assertNull($this->variant('M')->promo_price);
        $this->assertSame(800_000, $this->variant('S')->effectivePrice());
        $this->assertSame(750_000, $this->variant('M')->effectivePrice());
    }

    public function test_a_window_ends_exactly_at_its_end(): void
    {
        $payload = $this->payload();
        $this->manage($payload);
        $end = \Illuminate\Support\Carbon::parse($payload['windows'][0]['to_utc'])->getTimestamp();

        StoqsPromotions::apply($end - 1);
        $this->assertSame(750_000, $this->product->fresh()->price);

        StoqsPromotions::apply($end);   // from <= t < to: the end itself is outside
        $p = $this->product->fresh();
        $this->assertSame(1_000_000, $p->price);
        $this->assertNull($p->compare_at_price);
        $this->assertNull($this->variant('S')->promo_price);
    }

    public function test_formula_price_worked_by_hand(): void
    {
        $s = ['max_discount_percent' => 30, 'round_step' => 10000, 'round_mode' => 'down', 'round_offset' => 0];
        // 25% off 1,234,000 = 925,500 → down to 920,000; the 30% cap is 863,800 → up to 870,000.
        $this->assertSame(920_000, StoqsPromotions::formulaPrice(1_234_000, 25, null, $s));
        // 40% would be 740,400 → 740,000, under the cap: the cap (870,000) holds.
        $this->assertSame(870_000, StoqsPromotions::formulaPrice(1_234_000, 40, null, $s));
        // A floor of 950,001 rounds up to 960,000 and holds.
        $this->assertSame(960_000, StoqsPromotions::formulaPrice(1_234_000, 25, 950_001, $s));
        // A floor above the list price: no discount at all.
        $this->assertNull(StoqsPromotions::formulaPrice(1_234_000, 25, 1_300_000, $s));
        $this->assertNull(StoqsPromotions::formulaPrice(1_234_000, 0, null, $s));
    }

    public function test_a_size_priced_in_the_shop_and_not_reported_gets_the_formula_price(): void
    {
        $m = $this->variant('M');
        $m->price = 900_000;   // set in the shop (stoqs_size_price null)
        $m->save();
        $payload = $this->payload();
        $payload['variants'][1] = ['variant_ids' => [12], 'product_id' => 1, 'list_price' => null, 'floor_price' => 500_000,
            'prices' => [['c3', null, 20]], 'price_owner' => 'shop', 'reported' => false];
        $this->manage($payload);
        StoqsPromotions::apply();

        // 20% off 900,000 = 720,000 (step 1,000 down); the cap (450,000) and floor (500,000) are lower.
        $this->assertSame(720_000, $this->variant('M')->promo_price);
        $this->assertSame(720_000, $this->variant('M')->effectivePrice());
        $this->assertSame(800_000, $this->product->fresh()->price, 'the product shows the sizes that follow it');
    }

    public function test_a_no_discount_category_takes_no_campaign_window_but_price_sheets_and_the_outlet_stay(): void
    {
        $payload = $this->payload([
            'categories' => ['9' => [1]],
            'outlet' => ['o5'],
            'windows' => array_merge($this->payload()['windows'], [
                ['id' => 'o5', 'source' => 'outlet', 'campaign_id' => null, 'plan_id' => 8, 'version' => 1, 'pct' => 10,
                    'from' => null, 'to' => null, 'from_utc' => null, 'to_utc' => null],
                ['id' => 'sp5', 'source' => 'price_sheet', 'campaign_id' => null, 'plan_id' => null, 'version' => null, 'pct' => 5,
                    'from' => null, 'to' => null, 'from_utc' => null, 'to_utc' => null],
            ]),
        ]);
        $payload['settings']['no_discount_category_ids'] = [9];
        $payload['variants'][0]['prices'][] = ['o5', 900_000];
        $payload['variants'][1]['prices'][] = ['sp5', 950_000];
        $this->manage($payload);
        StoqsPromotions::apply();

        // S: the 800,000 campaign window is skipped, the 900,000 outlet window holds;
        // M: the 750,000 campaign window is skipped, the 950,000 price sheet holds.
        $this->assertSame(900_000, $this->product->fresh()->price);
        $this->assertSame(900_000, $this->variant('S')->effectivePrice());
        $this->assertSame(950_000, $this->variant('M')->effectivePrice());

        $payload['settings']['outlet_respects_no_discount'] = true;
        $this->manage($payload);
        StoqsPromotions::apply();
        $this->assertSame(1_000_000, $this->variant('S')->effectivePrice());
        $this->assertSame(950_000, $this->variant('M')->effectivePrice(), 'a price sheet is not a campaign');
        $this->assertSame(1_000_000, $this->product->fresh()->compare_at_price);
    }

    public function test_catalog_import_takes_list_prices_and_applies_windows(): void
    {
        $this->manage($this->payload());
        app(CatalogImporter::class)->importProduct([
            'product_id' => 1, 'name' => 'مانتو', 'base_price' => 1_000_000, 'price' => 1_000_000, 'active' => true, 'web_published' => true,
            'sale_price' => 600_000, 'compare_at_price' => 1_000_000, 'on_sale' => true,
            'variants' => [
                ['variant_id' => 11, 'barcode' => '1001', 'size' => 'S', 'active' => true, 'price' => 1_000_000, 'sale_price' => 600_000],
                ['variant_id' => 12, 'barcode' => '1002', 'size' => 'M', 'active' => true, 'price' => 1_000_000, 'sale_price' => 600_000],
            ],
        ]);

        $p = $this->product->fresh();
        $this->assertSame(750_000, $p->price, 'the window price, not the catalog sale price');
        $this->assertSame(1_000_000, $p->compare_at_price);
        $this->assertSame(800_000, $this->variant('S')->promo_price);
    }

    // ── outlet and the plan's discount sections ─────────────────────────────

    public function test_the_outlet_is_stoqs_and_read_only(): void
    {
        $payload = $this->payload([
            'outlet' => ['o5'],
            'windows' => array_merge($this->payload()['windows'], [[
                'id' => 'o5', 'source' => 'outlet', 'campaign_id' => null, 'plan_id' => 8, 'version' => 1, 'pct' => 30,
                'from' => null, 'to' => null, 'from_utc' => null, 'to_utc' => null,
            ]]),
        ]);
        $payload['variants'][0]['prices'][] = ['o5', 700_000];
        $this->manage($payload);
        OutletItem::create(['product_id' => $this->product->id, 'percent' => 10, 'is_active' => true]);

        $this->assertSame([$this->product->id => 30], Outlet::percents());
        $this->assertSame([Outlet::MANAGED], Outlet::sync(['manto-1' => 20]));
        $this->assertSame(10, OutletItem::first()->percent, 'the shop\'s own outlet stays as it was');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.outlet.index'))->assertOk()->assertSee('StoqS');
        $this->actingAs($admin)->post(route('admin.outlet.store'), ['product_id' => $this->product->id, 'percent' => 20])
            ->assertSessionHasErrors(['percent' => Outlet::MANAGED]);
        $this->actingAs($admin)->delete(route('admin.outlet.destroy', OutletItem::first()))
            ->assertSessionHasErrors(['percent' => Outlet::MANAGED]);
        $this->assertSame(1, OutletItem::count());
    }

    public function test_the_plan_locks_rules_and_coupons(): void
    {
        $this->manage($this->payload());

        $this->assertSame([CampaignPlan::STOQS_LOCKED], CampaignPlan::createCoupon(['code' => 'NEW10', 'type' => 'percent', 'value' => 10]));
        $this->assertSame([CampaignPlan::STOQS_LOCKED], CampaignPlan::saveRule(null, ['label' => 'x']));
        $this->assertSame(0, Coupon::where('code', 'NEW10')->count());
        $this->assertSame(CampaignPlan::STOQS_SECTIONS, CampaignPlan::editorPayload()['locked']);
    }

    public function test_campaign_sync_writes_no_rules_or_coupons(): void
    {
        $this->manage($this->payload());
        $rules = DiscountRule::count();
        $coupons = Coupon::count();

        $this->artisan('campaigns:sync')->assertSuccessful();

        $this->assertSame($rules, DiscountRule::count());
        $this->assertSame($coupons, Coupon::count());
    }

    public function test_hello_says_size_prices_and_schema_2(): void
    {
        $this->manage($this->payload());

        $this->assertContains('size_prices', StoqsSites::capabilities());
        $this->assertContains('engine_schema_2', StoqsSites::capabilities());
    }

    // ── the cart ───────────────────────────────────────────────────────────

    public function test_the_cart_uses_stoqs_rules_and_codes_not_the_shops(): void
    {
        $this->manage($this->payload());
        StoqsPromotions::apply();
        Coupon::create(['code' => 'LOCAL10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        $this->post(route('cart.add'), ['variant_id' => $this->variant('S')->id, 'quantity' => 1]);
        $cart = $this->cart();
        $this->assertSame(800_000, $cart->subtotal());
        // StoqS's rule 31: 10% of 800,000.
        $this->assertSame(80_000, $cart->combinedDiscount());

        $this->assertFalse($cart->applyCoupon('LOCAL10')['ok'], 'the shop\'s own coupon does not count');
        $this->assertTrue($cart->applyCoupon('stq5')['ok']);
        $cart = $this->cart();
        $this->assertSame('STQ5', $cart->appliedCoupon()?->code);
        // 80,000 (rule) + 50,000 (code).
        $this->assertSame(130_000, $cart->combinedDiscount());
    }

    public function test_cart_and_checkout_pages_render_with_a_stoqs_code(): void
    {
        $this->seed();
        $this->manage($this->payload());
        StoqsPromotions::apply();
        $this->actingAs(User::factory()->create());
        $this->post(route('cart.add'), ['variant_id' => $this->variant('S')->id, 'quantity' => 1]);
        $this->post(route('cart.coupon.apply'), ['code' => 'STQ5'])->assertRedirect();

        $this->get(route('cart.index'))->assertOk()->assertSee('STQ5');
        $this->get(route('checkout.index'))->assertOk()->assertSee('STQ5');
    }

    // ── checkout ───────────────────────────────────────────────────────────

    private function placeOrder(): \Illuminate\Testing\TestResponse
    {
        $this->seed();
        $user = User::factory()->create(['phone' => '09120001122']);
        $address = Address::create([
            'user_id' => $user->id, 'recipient_name' => 'تست', 'phone' => '09120001122',
            'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان نمونه',
        ]);
        $this->actingAs($user);
        $this->post(route('cart.add'), ['variant_id' => $this->variant('S')->id, 'quantity' => 1])->assertRedirect();

        return $this->post(route('checkout.place'), [
            'address_id' => (string) $address->id, 'shipping_method_id' => ShippingMethod::first()->id, 'payment_method' => 'zarinpal',
        ]);
    }

    private function evaluateResponse(array $over = []): array
    {
        return array_replace([
            'ok' => true, 'version' => 4, 'subtotal' => 800_000, 'discount' => 80_000, 'cap' => 500_000, 'total' => 720_000,
            'lines' => [[
                'index' => 0, 'variant_id' => 11, 'qty' => 1, 'list_price' => 1_000_000, 'price' => 800_000, 'price_source' => 'campaign',
                'campaign_id' => 3, 'plan_id' => 7, 'plan_version' => 4, 'discount' => 80_000,
                'parts' => [['type' => 'rule', 'id' => 31, 'amount' => 80_000]],
            ]],
            'rules' => [['rule_id' => 31, 'applied' => true, 'amount' => 80_000, 'label' => 'ده درصد']],
            'codes' => [], 'shipping_free' => false, 'blocks' => [], 'dropped' => [], 'mismatch' => [],
        ], $over);
    }

    public function test_checkout_asks_stoqs_and_the_order_reports_plan_and_list_price(): void
    {
        $this->manage($this->payload());
        StoqsPromotions::apply();
        Http::fake([
            'stoqs.test/api/v1/promotions/evaluate' => Http::response($this->evaluateResponse()),
            '*' => Http::response(['ok' => true, 'id' => 'x', 'data' => ['id' => 'x'], 'batch_id' => 'b1']),
        ]);

        $this->placeOrder()->assertRedirect();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'promotions/evaluate')
            && $r['lines'][0]['variant_id'] === 11 && $r['lines'][0]['unit_price'] === 800_000
            && $r['lines'][0]['list_price'] === 1_000_000 && $r['lines'][0]['plan_id'] === 7 && $r['lines'][0]['plan_version'] === 4
            && $r['site_url'] === 'https://shop.test' && $r['customer_phone'] === '09120001122'
            && $r['context']['payment_method_key'] === 'zarinpal' && isset($r['context']['shipping_method_id']));

        $order = Order::latest('id')->first();
        $this->assertSame('stoqs', $order->promo_eval);
        $this->assertSame(4, $order->promo_version);
        $this->assertSame(80_000, (int) $order->discount);
        $this->assertSame([], (array) $order->applied_discount_rules);
        $item = $order->items->first();
        $this->assertSame(800_000, (int) $item->unit_price);
        $this->assertSame(1_000_000, $item->list_price);
        $this->assertEquals([
            ['type' => 'campaign', 'id' => 3, 'plan_id' => 7, 'version' => 4, 'amount' => 200_000, 'layer' => 'price'],
            ['type' => 'rule', 'id' => 31, 'plan_id' => 7, 'version' => 4, 'amount' => 80_000],
        ], $item->discounts);

        // The sale report: list price per line, when it was priced, for which site.
        $sale = app(\App\Services\StockKeeping\StockKeepingReporter::class)->buildSalePayload($order->fresh());
        $this->assertArrayHasKey('ordered_at', $sale);
        $this->assertArrayNotHasKey('eval', $sale);
        $this->assertSame('https://shop.test', $sale['site_url']);
        $this->assertSame(1_000_000, $sale['items'][0]['list_price']);

        Http::fake(['*' => Http::response(['batch_id' => 'b2'])]);
        $this->client()->reportSale($sale);
        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/api/v1/sales')) {
                return false;
            }
            $i = $r['items'][0];

            // unit_price goes back to the list price; StoqS takes the campaign part off again.
            return $i['unit_price'] === 1_000_000 && $i['list_price'] === 1_000_000 && $i['discount_amount'] === 280_000
                && $i['discounts'][0] == ['type' => 'campaign', 'id' => 3, 'amount' => 200_000, 'plan_id' => 7, 'version' => 4]
                && $i['discounts'][1] == ['type' => 'rule', 'id' => 31, 'amount' => 80_000, 'plan_id' => 7, 'version' => 4]
                && $r['site_url'] === 'https://shop.test' && isset($r['ordered_at']) && ! isset($r['eval']);
        });
    }

    public function test_checkout_sells_on_the_local_engine_when_stoqs_is_down(): void
    {
        $this->manage($this->payload());
        StoqsPromotions::apply();
        Http::fake([
            'stoqs.test/api/v1/promotions/evaluate' => Http::response('down', 503),
            '*' => Http::response(['ok' => true, 'id' => 'x', 'data' => ['id' => 'x']]),
        ]);

        $this->placeOrder()->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('local', $order->promo_eval);
        $this->assertSame(4, $order->promo_version);
        $this->assertSame(80_000, (int) $order->discount, 'the local engine gives the same rule');
        $sale = app(\App\Services\StockKeeping\StockKeepingReporter::class)->buildSalePayload($order->fresh());
        $this->assertSame('local', $sale['eval']);
    }

    public function test_a_price_stoqs_says_is_stale_sends_the_customer_back_to_the_cart(): void
    {
        $this->manage($this->payload());
        StoqsPromotions::apply();
        $newer = $this->payload(['version' => 5]);
        $newer['variants'][0]['prices'] = [['c3', 700_000]];
        Http::fake([
            'stoqs.test/api/v1/promotions/evaluate' => Http::response($this->evaluateResponse(['mismatch' => [['index' => 0, 'field' => 'unit_price', 'within_grace' => false]]])),
            'stoqs.test/api/v1/promotions*' => Http::response($newer, 200, ['ETag' => '"v5"']),
            '*' => Http::response(['ok' => true, 'id' => 'x', 'data' => ['id' => 'x']]),
        ]);

        $this->placeOrder()->assertRedirect(route('cart.index'));

        $this->assertSame(0, Order::count());
        $this->assertSame(700_000, $this->variant('S')->fresh()->effectivePrice());
    }

    // ── pulls ──────────────────────────────────────────────────────────────

    public function test_a_promotions_webhook_pulls_with_if_none_match_and_acks(): void
    {
        $this->manage($this->payload());
        Http::fake([
            'stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true]),
            'stoqs.test/api/v1/promotions*' => Http::response('', 304),
        ]);

        $this->webhook('promotions.changed', ['promo_version' => 4, 'reason' => 'applied'])->assertOk();

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/api/v1/promotions')
            && $r->header('If-None-Match') === ['"v4"'] && str_contains($r->url(), 'site_url='));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'promotions/ack') && $r['version'] === 4 && $r['applied'] === [7]);
        $this->assertSame(750_000, $this->product->fresh()->price, 'the windows were applied');
    }

    public function test_the_minute_sync_applies_reports_shop_sizes_and_acks_once(): void
    {
        $this->manage($this->payload(['version' => 3]));
        $m = $this->variant('M');
        $m->price = 900_000;   // set in the shop
        $m->save();
        $payload = $this->payload();
        Http::fakeSequence('stoqs.test/api/v1/promotions?*')
            ->push($payload, 200, ['ETag' => '"v4"'])
            ->push('', 304);
        Http::fake([
            'stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true]),
            'stoqs.test/api/v1/catalog/variants/*' => Http::response(['ok' => true]),
        ]);

        $this->artisan('stoqs:promotions-sync')->assertSuccessful();
        $this->artisan('stoqs:promotions-sync')->assertSuccessful();

        $this->assertSame(4, StoqsPromotions::version());
        $this->assertSame('"v4"', StoqsPromotions::row()->etag);
        $this->assertSame(800_000, $this->product->fresh()->price);
        $puts = Http::recorded(fn (Request $r) => $r->method() === 'PUT')->values();
        $this->assertCount(1, $puts, 'a shop-set size is reported once');
        $this->assertStringEndsWith('/catalog/variants/12/site-price', $puts[0][0]->url());
        $this->assertSame(['list_price' => 900_000, 'site_url' => 'https://shop.test'], $puts[0][0]->data());
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_ends_with($r->url(), 'promotions/ack')), 'one ack per version');
    }

    // ── what the customer saw (review 2026-11-11) ──────────────────────────

    /** STQ5 limited to one use per customer, already used by this customer elsewhere (StoqS knows, the shop does not). */
    private function limitedCode(): array
    {
        $payload = $this->payload();
        $payload['codes'][0]['per_customer_limit'] = 1;

        return $payload;
    }

    public function test_a_code_stoqs_refuses_for_this_customer_sends_them_back_to_the_cart(): void
    {
        $this->manage($this->limitedCode());
        StoqsPromotions::apply();
        Http::fake([
            'stoqs.test/api/v1/promotions/evaluate' => Http::sequence()->push($this->evaluateResponse([
                'codes' => [['code' => 'STQ5', 'valid' => false, 'applied' => false, 'reason' => 'customer_limit',
                    'message' => 'سقف استفادهٔ شما از این کد تکمیل شده است.', 'amount' => 0]],
            ]))->push($this->evaluateResponse()),
            '*' => Http::response(['ok' => true, 'id' => 'x', 'data' => ['id' => 'x']]),
        ]);
        $this->seed();
        $user = User::factory()->create(['phone' => '09120001122']);
        $address = Address::create(['user_id' => $user->id, 'recipient_name' => 'تست', 'phone' => '09120001122',
            'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان نمونه']);
        $this->actingAs($user);
        $this->post(route('cart.add'), ['variant_id' => $this->variant('S')->id, 'quantity' => 1]);
        $this->post(route('cart.coupon.apply'), ['code' => 'STQ5']);
        $this->assertSame(130_000, $this->cart()->combinedDiscount(), 'the shop knows no use of STQ5 by this customer');
        $place = fn () => $this->post(route('checkout.place'), [
            'address_id' => (string) $address->id, 'shipping_method_id' => ShippingMethod::first()->id, 'payment_method' => 'zarinpal',
        ]);

        $place()->assertRedirect(route('cart.index'))->assertSessionHas('error', fn ($m) => str_contains($m, 'STQ5') && str_contains($m, 'معتبر نیست'));
        $this->assertSame(0, Order::count(), 'nothing is charged before the customer sees the new amount');
        $cart = $this->cart();
        $this->assertNull($cart->appliedCoupon());
        $this->assertSame(80_000, $cart->combinedDiscount(), 'the cart now shows StoqS\'s answer');

        // Placing again: StoqS gives what the cart showed, so the order goes through at that amount.
        $place()->assertRedirect();
        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame(80_000, (int) $order->discount);
        $this->assertNull($order->coupon_code);
    }

    public function test_the_local_engine_counts_the_customers_own_uses_of_a_code(): void
    {
        $this->manage($this->limitedCode());
        StoqsPromotions::apply();
        $this->seed();
        $user = User::factory()->create(['phone' => '09120001122']);
        Order::forceCreate(array_intersect_key([
            'number' => 'OLD-1', 'user_id' => $user->id, 'status' => Order::STATUS_PAID, 'subtotal' => 1, 'shipping_cost' => 0,
            'discount' => 0, 'total' => 1, 'shipping_address' => [], 'coupon_code' => 'STQ5', 'paid_at' => now()->subDay(),
            'placed_at' => now()->subDay(), 'payment_method' => 'zarinpal',
        ], array_flip(Schema::getColumnListing('orders'))));
        $this->actingAs($user);
        $this->post(route('cart.add'), ['variant_id' => $this->variant('S')->id, 'quantity' => 1]);

        $r = $this->cart()->applyCoupon('STQ5');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('سقف استفاده', $r['message']);
    }

    public function test_a_cheaper_stoqs_answer_is_used_quietly_and_a_block_is_not(): void
    {
        $this->manage($this->payload());
        StoqsPromotions::apply();
        Http::fake([
            'stoqs.test/api/v1/promotions/evaluate' => Http::sequence()->push($this->evaluateResponse(['discount' => 100_000,
                'lines' => [array_replace($this->evaluateResponse()['lines'][0], ['discount' => 100_000, 'parts' => [['type' => 'rule', 'id' => 31, 'amount' => 100_000]]])]]))
                ->push($this->evaluateResponse(['blocks' => ['این سبد امروز فروخته نمی‌شود.']])),
            '*' => Http::response(['ok' => true, 'id' => 'x', 'data' => ['id' => 'x']]),
        ]);
        $this->placeOrder()->assertRedirect();
        $this->assertSame(100_000, (int) Order::latest('id')->first()->discount);

        Order::query()->delete();
        $this->post(route('cart.add'), ['variant_id' => $this->variant('S')->id, 'quantity' => 1]);
        $this->post(route('checkout.place'), [
            'address_id' => (string) Address::first()->id, 'shipping_method_id' => ShippingMethod::first()->id, 'payment_method' => 'zarinpal',
        ])->assertRedirect(route('cart.index'))->assertSessionHas('error', 'این سبد امروز فروخته نمی‌شود.');
        $this->assertSame(0, Order::count());
    }

    // ── own coupons, locks ─────────────────────────────────────────────────

    public function test_the_shops_own_coupon_sold_through_stoqs_is_counted_locally_too(): void
    {
        $local = Coupon::create(['code' => 'OWN10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        $local->forceFill(['stoqs_code_id' => 55])->saveQuietly();
        $payload = $this->payload();
        $payload['codes'][] = ['id' => 55, 'code' => 'OWN10', 'type' => 'percent', 'value' => 10, 'enabled' => 1, 'channels' => 'web',
            'owner' => 'key:3', 'external_id' => (string) $local->id];
        $payload['codes'][] = ['id' => 56, 'code' => 'THEIRS', 'type' => 'percent', 'value' => 10, 'enabled' => 1, 'channels' => 'web',
            'owner' => 'key:9', 'external_id' => '999'];
        $this->manage($payload);

        $this->assertSame($local->id, \App\Services\Discount\StoqsCart::ownCouponId('own10'));
        $this->assertNull(\App\Services\Discount\StoqsCart::ownCouponId('STQ5'), 'a StoqS code');
        $this->assertNull(\App\Services\Discount\StoqsCart::ownCouponId('THEIRS'), 'another key\'s code');
    }

    public function test_reset_editor_outlet_and_the_admin_discount_pages_are_locked(): void
    {
        $this->manage($this->payload());
        $this->assertSame([CampaignPlan::STOQS_LOCKED], CampaignPlan::reset());

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.coupons.index'))->assertOk()->assertSee(CampaignPlan::STOQS_LOCKED);
        $this->actingAs($admin)->get(route('admin.discount-rules.index'))->assertOk()->assertSee(CampaignPlan::STOQS_LOCKED);
        $this->actingAs($admin)->get(route('admin.coupons.create'))->assertRedirect(route('admin.coupons.index'));
        $this->actingAs($admin)->post(route('admin.coupons.store'), ['code' => 'NEWX', 'type' => 'percent', 'value' => 5])
            ->assertRedirect(route('admin.coupons.index'))->assertSessionHas('error', CampaignPlan::STOQS_LOCKED);
        $this->assertSame(0, Coupon::where('code', 'NEWX')->count());
        $this->actingAs($admin)->get(route('admin.discount-rules.create'))->assertRedirect(route('admin.discount-rules.index'));
    }

    // ── site prices, scopes, the switch ────────────────────────────────────

    public function test_site_prices_stop_at_a_rate_limit_and_retry_later(): void
    {
        $this->manage($this->payload());
        foreach (['S' => 910_000, 'M' => 920_000] as $size => $price) {
            $v = $this->variant($size);
            $v->price = $price;
            $v->save();
        }
        Http::fake(['stoqs.test/api/v1/catalog/variants/*' => Http::response(['ok' => false, 'error' => ['code' => 'rate_limited']], 429)]);

        $this->assertSame(['sent' => 0, 'failed' => 1, 'stopped' => 429], StoqsPromotions::reportSitePrices());
        $this->assertCount(1, Http::recorded());
        $this->assertTrue($this->variant('S')->site_price_retry_at->isFuture());
        StoqsPromotions::reportSitePrices();
        $this->assertStringEndsWith('/catalog/variants/12/site-price', Http::recorded()->values()[1][0]->url(), 'S waits; M is next');
        StoqsPromotions::reportSitePrices();
        $this->assertCount(2, Http::recorded(), 'both wait for their retry time');
    }

    public function test_site_prices_wait_for_catalog_write_and_the_promotions_scope_banner_clears(): void
    {
        $this->manage($this->payload());
        $m = $this->variant('M');
        $m->price = 900_000;
        $m->save();
        \App\Models\Setting::put('stockkeeping.missing_scopes', ['catalog:write' => now()->toIso8601String(), 'discounts:read' => now()->toIso8601String()]);
        Http::fake([
            'stoqs.test/api/v1/catalog/variants/*' => Http::response(['ok' => false, 'error' => ['code' => 'forbidden_scope']], 403),
            'stoqs.test/api/v1/promotions*' => Http::response('', 304),
        ]);

        StoqsPromotions::reportSitePrices();
        StoqsPromotions::reportSitePrices();
        $this->assertCount(1, Http::recorded(), 'one probe an hour while catalog:write is missing');

        StoqsPromotions::pull();
        $this->assertArrayNotHasKey('discounts:read', \App\Services\StockKeeping\StoqsApi::missingScopes());
        $this->assertArrayHasKey('catalog:write', \App\Services\StockKeeping\StoqsApi::missingScopes());
    }

    public function test_a_switch_imports_from_the_minute_job_and_keeps_hand_set_struck_prices(): void
    {
        DB::table('stoqs_promotions')->delete();
        StoqsPromotions::forget();
        $hand = Product::create(['name' => 'شلوار', 'slug' => 'shalvar', 'price' => 900_000, 'compare_at_price' => 1_200_000,
            'stockkeeping_id' => '2', 'is_active' => true]);
        ProductVariant::create(['product_id' => $hand->id, 'sku' => '2001', 'size' => 'L', 'stock_qty' => 2, 'is_active' => true, 'stockkeeping_variant_id' => 21]);
        $catalog = ['products' => [
            ['product_id' => 1, 'name' => 'مانتو', 'base_price' => 1_000_000, 'price' => 1_000_000, 'active' => true, 'web_published' => true,
                'variants' => [['variant_id' => 11, 'barcode' => '1001', 'size' => 'S', 'active' => true, 'price' => 1_000_000],
                    ['variant_id' => 12, 'barcode' => '1002', 'size' => 'M', 'active' => true, 'price' => 1_000_000]]],
            ['product_id' => 2, 'name' => 'شلوار', 'base_price' => 1_200_000, 'price' => 1_200_000, 'active' => true, 'web_published' => true,
                'variants' => [['variant_id' => 21, 'barcode' => '2001', 'size' => 'L', 'active' => true, 'price' => 1_200_000]]],
        ], 'has_more' => false];
        // A StoqS that answers by ETag: 304 for the version the shop has, else the payload.
        $live = $this->payload();
        Http::fake([
            'stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true]),
            'stoqs.test/api/v1/promotions*' => function (Request $r) use (&$live) {
                $etag = '"v'.$live['version'].'"';

                return $r->header('If-None-Match') === [$etag] ? Http::response('', 304) : Http::response($live, 200, ['ETag' => $etag]);
            },
            'stoqs.test/api/v1/catalog?*' => Http::response($catalog),
            '*' => Http::response(['ok' => true, 'products' => [], 'data' => [], 'has_more' => false]),
        ]);

        // The webhook only notes the switch: no catalog import inside it.
        $this->webhook('promotions.changed', ['promo_version' => 4, 'reason' => 'site_source'])->assertOk();
        $this->assertTrue(StoqsPromotions::managed());
        $this->assertTrue((bool) StoqsPromotions::row()->reimport_pending);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/api/v1/catalog?'));
        $this->assertArrayHasKey((string) $hand->id, json_decode(StoqsPromotions::row()->saved_prices, true));

        // The minute job: the import runs under the shared lock, then the windows.
        $this->artisan('stoqs:promotions-sync')->assertSuccessful();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/v1/catalog?'));
        $this->assertFalse((bool) StoqsPromotions::row()->reimport_pending);
        $this->assertSame(750_000, $this->product->fresh()->price);
        $this->assertSame(1_200_000, $hand->fresh()->price, 'managed: the list price, the hand-set struck price set aside');

        // Back to the shop: the struck price set by hand comes back after the import.
        $live = array_replace($this->payload(), ['managed' => false, 'version' => 5]);
        $this->webhook('promotions.changed', ['promo_version' => 5, 'reason' => 'site_source'])->assertOk();
        $this->assertFalse(StoqsPromotions::managed());
        $this->artisan('stoqs:promotions-sync')->assertSuccessful();
        $this->assertFalse((bool) StoqsPromotions::row()->reimport_pending);
        $h = $hand->fresh();
        $this->assertSame(900_000, $h->price);
        $this->assertSame(1_200_000, $h->compare_at_price);
    }

    public function test_a_switch_waits_while_the_hourly_import_holds_the_lock(): void
    {
        $this->manage($this->payload(), ['reimport_pending' => true]);
        $lock = StoqsPromotions::catalogLock();
        $this->assertTrue($lock->get());

        $this->assertFalse(StoqsPromotions::runPendingImport());
        $this->assertTrue((bool) StoqsPromotions::row()->reimport_pending);
        $lock->release();
    }

    public function test_pre_switch_check_lists_shop_sizes_struck_prices_and_scopes(): void
    {
        $m = $this->variant('M');
        $m->price = 900_000;
        $m->save();
        $this->product->forceFill(['compare_at_price' => 1_300_000])->save();
        \App\Models\Setting::put('stockkeeping.missing_scopes', ['catalog:write' => now()->toIso8601String()]);
        Http::fake(['stoqs.test/api/v1/promotions*' => Http::response(array_replace($this->payload(), ['managed' => false]), 200)]);

        $this->artisan('stoqs:pre-switch-check')
            ->expectsOutputToContain('Sizes priced in the shop (reported to StoqS as shop-set): 1')
            ->expectsOutputToContain('Struck prices set by hand (cleared while managed, restored after): 1')
            ->expectsOutputToContain('The key lacks: catalog:write')
            ->assertFailed();
    }
}

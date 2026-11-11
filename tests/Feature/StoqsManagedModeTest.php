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
            && $r['site_url'] === 'https://shop.test' && $r['customer_phone'] === '09120001122');

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
}

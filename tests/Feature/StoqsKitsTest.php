<?php

namespace Tests\Feature;

use App\Models\BundleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Cart\Cart;
use App\Services\Discount\StoqsCart;
use App\Services\StockKeeping\CatalogImporter;
use App\Services\StockKeeping\HttpStockKeepingClient;
use App\Services\StockKeeping\StockKeepingReporter;
use App\Services\StockKeeping\StoqsContent;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kits (bundles) and StoqS, shop side (5.2.130): a kit StoqS knows is priced from /promotions and is
 * one cart and sale line; a kit it does not know stays the shop's own (components). Returns and
 * cancels put the right thing back. StoqS-made links are adopted, never re-pointed, never duplicated.
 */
class StoqsKitsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'kit-secret';

    private Product $a;

    private Product $b;

    private Product $kit;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k',
            'stockkeeping.webhook_secret' => self::SECRET, 'stockkeeping.fulfillment_location' => 'warehouse:1',
            'app.url' => 'https://shop.test', 'scout.driver' => null,
        ]);
        Http::preventStrayRequests();
        Cache::lock('scheduler:web-kick', 600)->get();
        StoqsPromotions::forget();
        $this->a = $this->plain('p-a', 'الف', 1_000_000, '1', '1001', 11);
        $this->b = $this->plain('p-b', 'ب', 600_000, '2', '2001', 21);
        $this->kit = $this->makeKit('kit-1', 1_500_000, '9', 91);
    }

    private function plain(string $slug, string $name, int $price, ?string $skId, string $sku, ?int $svid): Product
    {
        $p = Product::create(['name' => $name, 'slug' => $slug, 'price' => $price, 'stockkeeping_id' => $skId, 'is_active' => true]);
        ProductVariant::create(['product_id' => $p->id, 'sku' => $sku, 'size' => 'M', 'stock_qty' => 10, 'is_active' => true, 'stockkeeping_variant_id' => $svid]);

        return $p;
    }

    /** A kit: placeholder variant, a ×1 and b ×2. $skId null = the shop's own kit. */
    private function makeKit(string $slug, int $price, ?string $skId, ?int $svid): Product
    {
        $k = Product::create(['name' => 'کیت '.$slug, 'slug' => $slug, 'price' => $price, 'stockkeeping_id' => $skId, 'is_bundle' => true, 'is_active' => true]);
        ProductVariant::create(['product_id' => $k->id, 'sku' => $svid ? '7'.$svid : null, 'size' => '—', 'stock_qty' => 0, 'is_active' => true, 'stockkeeping_variant_id' => $svid]);
        BundleItem::create(['bundle_product_id' => $k->id, 'product_variant_id' => $this->a->variants()->first()->id, 'quantity' => 1, 'position' => 0]);
        BundleItem::create(['bundle_product_id' => $k->id, 'product_variant_id' => $this->b->variants()->first()->id, 'quantity' => 2, 'position' => 1]);

        return $k;
    }

    private function placeholder(Product $k): ProductVariant
    {
        return $k->variants()->firstOrFail();
    }

    /** A /promotions payload: the kit (StoqS variant 91) at list 1,500,000; a 20% window prices it at 1,200,000 when asked. */
    private function payload(bool $window = false): array
    {
        $from = now()->subHour();
        $to = now()->addHour();

        return [
            'ok' => true, 'schema' => 3, 'version' => 4, 'managed' => true, 'promo_source' => 'stoqs', 'price_level' => 'size',
            'settings' => ['max_discount_percent' => 50, 'round_step' => 1000, 'round_mode' => 'down', 'round_offset' => 0, 'no_discount_category_ids' => []],
            'plans' => [['id' => 7, 'campaign_id' => 3, 'kind' => 'campaign', 'version' => 4, 'active' => true]],
            'windows' => [[
                'id' => 'c3', 'source' => 'campaign', 'campaign_id' => 3, 'plan_id' => 7, 'version' => 4, 'pct' => 20,
                'from' => $from->copy()->setTimezone('Asia/Tehran')->toAtomString(), 'to' => $to->copy()->setTimezone('Asia/Tehran')->toAtomString(),
                'from_utc' => $from->copy()->utc()->toAtomString(), 'to_utc' => $to->copy()->utc()->toAtomString(),
            ]],
            'variants' => [
                ['variant_ids' => [91], 'product_id' => 9, 'list_price' => 1_500_000, 'floor_price' => 1_000_000, 'prices' => $window ? [['c3', 1_200_000]] : [], 'price_owner' => 'stoqs'],
            ],
            'outlet' => [],
            'rules' => [[
                'id' => 31, 'plan_id' => 7, 'label' => 'ده درصد', 'priority' => 1, 'conditions' => [],
                'actions' => [['type' => 'cart_discount_percent', 'value' => 10]],
                'apply_mode' => 'all', 'stack_mode' => 'all', 'exclusive' => false, 'enabled' => 1, 'channels' => 'web,brand,stores',
            ]],
            'codes' => [], 'categories' => [],
        ];
    }

    private function manage(array $payload): void
    {
        DB::table('stoqs_promotions')->updateOrInsert(['id' => 1], [
            'managed' => true, 'version' => $payload['version'], 'etag' => '"v'.$payload['version'].'"',
            'payload' => json_encode($payload), 'pulled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        StoqsPromotions::forget();
    }

    private function webhook(string $event, array $data)
    {
        $raw = json_encode(['event' => $event, 'brand_id' => 2, 'event_id' => 'e-'.uniqid(), 'occurred_at' => now()->toAtomString(), 'data' => $data]);

        return $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
        ], $raw);
    }

    private function client(): HttpStockKeepingClient
    {
        return new HttpStockKeepingClient(baseUrl: 'https://stoqs.test', apiKey: 'k', locations: [], fulfillmentLocation: 'warehouse:1');
    }

    /** A StoqS catalogue row for a kit. */
    private function kitPayload(array $over = []): array
    {
        return $over + [
            'product_id' => 19, 'name' => 'کیت از StoqS', 'base_price' => 1_500_000, 'price' => 1_500_000, 'active' => true, 'is_bundle' => true,
            'variants' => [['variant_id' => 192, 'barcode' => '9091', 'size' => 'کیت', 'active' => true, 'price' => 1_500_000]],
            'bundle' => ['items' => [
                ['product_id' => 1, 'variant_id' => 11, 'barcode' => '1001', 'qty' => 1],
                ['product_id' => 2, 'variant_id' => 21, 'barcode' => '2001', 'qty' => 2],
            ]],
        ];
    }

    // ── adoption (A4) and the importer (K7) ─────────────────────────────────────

    public function test_a_kit_named_by_site_product_id_is_adopted_not_duplicated_and_takes_its_ids(): void
    {
        $own = $this->makeKit('kit-own', 1_400_000, null, null);
        $before = Product::count();

        $kit = app(CatalogImporter::class)->importProduct($this->kitPayload(['site_product_id' => (string) $own->id]));

        $this->assertSame($before, Product::count(), 'no duplicate');
        $this->assertSame($own->id, $kit->id);
        $this->assertSame('19', (string) $kit->fresh()->stockkeeping_id);
        $this->assertTrue((bool) $kit->fresh()->is_bundle);
        $this->assertSame(1, $kit->variants()->count(), 'the one placeholder variant');
        $ph = $this->placeholder($kit);
        $this->assertSame([192, '9091', 0], [(int) $ph->stockkeeping_variant_id, $ph->sku, (int) $ph->stock_qty]);
        $items = $kit->bundleItems()->get()->map(fn ($i) => [(int) $i->product_variant_id, (int) $i->quantity])->all();
        $this->assertSame([[(int) $this->a->variants()->first()->id, 1], [(int) $this->b->variants()->first()->id, 2]], $items);
        $this->assertSame(1_500_000, (int) $kit->fresh()->price);
    }

    public function test_a_new_kit_is_created_with_components_and_hidden_when_a_component_is_missing(): void
    {
        $made = app(CatalogImporter::class)->importProduct($this->kitPayload(['product_id' => 20]));
        $this->assertTrue((bool) $made->is_bundle);
        $this->assertTrue((bool) $made->is_active);
        $this->assertSame(2, $made->bundleItems()->count());

        $bad = $this->kitPayload(['product_id' => 21, 'name' => 'کیت ناقص', 'variants' => [['variant_id' => 92, 'barcode' => '9092', 'size' => 'کیت', 'active' => true]],
            'bundle' => ['items' => [['product_id' => 3, 'variant_id' => 31, 'barcode' => '3001', 'qty' => 1]]]]);
        $hidden = app(CatalogImporter::class)->importProduct($bad);
        $this->assertFalse((bool) $hidden->is_active);
        $this->assertSame(0, $hidden->bundleItems()->count());
    }

    public function test_a_plain_product_named_by_site_product_id_is_adopted_and_an_existing_link_is_never_repointed(): void
    {
        $local = $this->plain('p-c', 'پ', 500_000, null, '3001', null);
        $payload = ['product_id' => 30, 'name' => 'پ', 'base_price' => 500_000, 'price' => 500_000, 'active' => true, 'site_product_id' => (string) $local->id,
            'variants' => [['variant_id' => 31, 'barcode' => '3001', 'size' => 'M', 'active' => true]]];
        $count = Product::count();

        $p = app(CatalogImporter::class)->importProduct($payload);
        $this->assertSame($count, Product::count());
        $this->assertSame([$local->id, '30'], [$p->id, (string) $p->fresh()->stockkeeping_id]);

        // The same local product, now linked to 30, named by another StoqS product: not re-pointed.
        $other = app(CatalogImporter::class)->importProduct(['product_id' => 31, 'name' => 'چیز دیگر', 'base_price' => 1, 'active' => true, 'site_product_id' => (string) $local->id,
            'variants' => [['variant_id' => 41, 'barcode' => '4001', 'size' => 'L', 'active' => true]]]);
        $this->assertNotSame($local->id, $other->id);
        $this->assertSame('30', (string) $local->fresh()->stockkeeping_id);
    }

    public function test_the_product_linked_webhook_adopts_a_kit_and_never_repoints(): void
    {
        $own = $this->makeKit('kit-own', 1_400_000, null, null);
        $ph = $this->placeholder($own);
        $this->webhook('product.linked', ['product_id' => 29, 'variant_id' => 291, 'site_product_id' => (string) $own->id, 'site_variant_id' => (string) $ph->id, 'bundle' => true])->assertOk();
        $this->assertSame('29', (string) $own->fresh()->stockkeeping_id);
        $this->assertSame(291, (int) $ph->fresh()->stockkeeping_variant_id);

        $this->webhook('product.linked', ['product_id' => 77, 'variant_id' => 99, 'site_product_id' => (string) $own->id, 'site_variant_id' => (string) $ph->id, 'bundle' => true])->assertOk();
        $this->assertSame('29', (string) $own->fresh()->stockkeeping_id);
        $this->assertSame(291, (int) $ph->fresh()->stockkeeping_variant_id);
        $this->webhook('product.linked', ['product_id' => 5, 'site_product_id' => '99999'])->assertOk();   // no such product: nothing
    }

    public function test_campaign_sync_leaves_a_linked_kit_alone_and_still_prices_an_unlinked_one(): void
    {
        config(['campaigns.bundles' => [
            'kit-1' => ['name' => 'کیت ۱', 'off' => 10, 'items' => [['p-a', 1], ['p-b', 2]]],
            'kit-own' => ['name' => 'کیت خود', 'off' => 10, 'items' => [['p-a', 1], ['p-b', 2]]],
        ]]);
        $this->makeKit('kit-own', 1, null, null);
        $this->artisan('campaigns:sync')->assertSuccessful();

        $this->assertSame(1_500_000, (int) $this->kit->fresh()->price, 'linked: StoqS owns the price');
        $this->assertSame(2, $this->kit->bundleItems()->count());
        $this->assertSame(1_980_000, (int) Product::where('slug', 'kit-own')->value('price'), '10% off 2,200,000, rounded down to 10,000');
    }

    // ── pricing, cart, evaluate ────────────────────────────────────────────────

    public function test_a_linked_kit_is_priced_from_promotions_and_its_struck_price_is_display_only(): void
    {
        $this->manage($this->payload(window: true));
        StoqsPromotions::apply();
        $k = $this->kit->fresh();
        $this->assertSame([1_200_000, 1_500_000], [(int) $k->price, (int) $k->compare_at_price]);
        $this->assertSame(1_200_000, $this->placeholder($k)->effectivePrice());

        $this->manage($this->payload(window: false));
        StoqsPromotions::apply();
        $k = $this->kit->fresh();
        // no window: the kit sells at its list price; the struck price is what the parts cost today (1,000,000 + 2 x 600,000)
        $this->assertSame([1_500_000, 2_200_000], [(int) $k->price, (int) $k->compare_at_price]);
        $this->assertFalse((bool) $k->stoqs_discount);
        $this->assertSame(1_500_000, StoqsContent::basePrice($k), 'the list price is the kit price, not the struck sum');

        StoqsPromotions::apply();   // again: nothing drifts
        $this->assertSame([1_500_000, 2_200_000], [(int) $this->kit->fresh()->price, (int) $this->kit->fresh()->compare_at_price]);
    }

    public function test_an_unlinked_kit_is_left_to_the_shop(): void
    {
        $own = $this->makeKit('kit-own', 1_400_000, null, null);
        $this->manage($this->payload());
        StoqsPromotions::apply();
        $this->assertSame(1_400_000, (int) $own->fresh()->price);
        $this->assertNull($own->fresh()->compare_at_price);
        $this->assertSame([], StoqsCart::lines(collect([['variant' => $this->placeholder($own), 'quantity' => 1, 'unit_price' => 1_400_000, 'line_total' => 1_400_000]]))[0]);
    }

    public function test_the_cart_prices_a_linked_kit_as_an_engine_line_and_evaluate_agrees(): void
    {
        $this->manage($this->payload());
        StoqsPromotions::apply();
        $ph = $this->placeholder($this->kit->fresh());

        $cart = app(Cart::class);
        $cart->add($ph->id, 2);
        app()->forgetInstance(Cart::class);
        StoqsPromotions::forget();
        $cart = app(Cart::class);
        $this->assertSame(3_000_000, $cart->subtotal());
        $this->assertSame(300_000, $cart->combinedDiscount(), 'StoqS rule 31: 10% of the kit line');
        $this->assertTrue(Cart::isDiscountable($this->kit->fresh()), 'managed: the engine decides for a linked kit');

        $lines = $cart->lines();
        [$eng] = StoqsCart::lines($lines);
        $this->assertSame([91, 9, 2, 1_500_000], [$eng[0]['variant_id'], $eng[0]['product_id'], $eng[0]['qty'], $eng[0]['unit_price']]);

        $local = StoqsCart::evaluate($lines);
        Http::fake(['stoqs.test/api/v1/promotions/evaluate' => function (Request $r) use ($local) {
            return Http::response(['ok' => true, 'subtotal' => $local['subtotal'], 'discount' => $local['discount'], 'cap' => $local['cap'], 'surcharge' => 0,
                'total' => $local['total'], 'shipping_free' => false, 'blocks' => [], 'rules' => $local['rules'], 'codes' => [],
                'lines' => [['index' => 0, 'variant_id' => 91, 'qty' => 2, 'discount' => $local['lines'][0]['discount'], 'parts' => $local['lines'][0]['parts'], 'margin_ok' => true]],
                'version' => 4]);
        }]);
        $remote = StoqsCart::remote($lines);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'promotions/evaluate') && $r['lines'][0]['variant_id'] === 91 && $r['lines'][0]['qty'] === 2);
        $this->assertSame($local['discount'], $remote['discount']);
        $this->assertSame(300_000, $remote['discount']);
    }

    public function test_the_shop_managed_cart_still_never_discounts_a_kit(): void
    {
        $this->assertFalse(Cart::isDiscountable($this->kit->fresh()));
        $this->assertTrue(Cart::isDiscountable($this->a->fresh()));
    }

    // ── the sale report ────────────────────────────────────────────────────────

    private function order(Product $kit, int $qty = 2): Order
    {
        $v = $this->placeholder($kit);
        $o = Order::create(['number' => 'T-'.uniqid(), 'status' => 'paid', 'subtotal' => 3_000_000, 'shipping_cost' => 0, 'discount' => 0, 'total' => 3_000_000,
            'customer_name' => 'x', 'customer_phone' => '09120000000']);
        OrderItem::create(['order_id' => $o->id, 'product_id' => $kit->id, 'product_variant_id' => $v->id, 'name' => $kit->name, 'size' => '—',
            'sku' => $v->sku, 'stockkeeping_variant_id' => $v->stockkeeping_variant_id, 'unit_price' => 1_500_000, 'quantity' => $qty, 'line_total' => 1_500_000 * $qty]);

        return $o;
    }

    public function test_a_linked_kit_is_reported_as_one_line_with_its_shipped_components(): void
    {
        $sale = app(StockKeepingReporter::class)->buildSalePayload($this->order($this->kit)->fresh(), ['name' => 'x', 'phone' => '0912']);
        $this->assertCount(1, $sale['items']);
        Http::fake(['stoqs.test/api/v1/sales' => Http::response(['ok' => true, 'batch_id' => 'B1'], 201)]);
        $this->client()->reportSale($sale);

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/sales')) {
                return false;
            }
            $i = $r['items'];

            return count($i) === 1 && $i[0]['variant_id'] === 91 && $i[0]['quantity'] === 2 && $i[0]['unit_price'] === 1_500_000
                && $i[0]['components'] === [['variant_id' => 11, 'quantity' => 1], ['variant_id' => 21, 'quantity' => 2]];
        });
    }

    public function test_5_2_130_a_kit_with_a_component_StoqS_has_no_id_for_sends_no_components_key(): void
    {
        $this->b->variants()->update(['stockkeeping_variant_id' => null]);
        $sale = app(StockKeepingReporter::class)->buildSalePayload($this->order($this->kit)->fresh(), ['name' => 'x', 'phone' => '0912']);
        $this->assertCount(1, $sale['items']);
        $this->assertArrayNotHasKey('components', $sale['items'][0], 'never an empty list: StoqS uses its own definition');
        Http::fake(['stoqs.test/api/v1/sales' => Http::response(['ok' => true, 'batch_id' => 'B1'], 201)]);
        $this->client()->reportSale($sale);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sales') && $r['items'][0]['variant_id'] === 91 && ! array_key_exists('components', $r['items'][0]));
    }

    public function test_an_unlinked_kit_is_still_reported_as_its_components(): void
    {
        $own = $this->makeKit('kit-own', 1_500_000, null, null);
        $sale = app(StockKeepingReporter::class)->buildSalePayload($this->order($own)->fresh(), ['name' => 'x', 'phone' => '0912']);
        $this->assertCount(2, $sale['items']);
        $this->assertSame([2, 4], array_column($sale['items'], 'quantity'));
        $this->assertSame([11, 21], array_map('intval', array_column($sale['items'], 'stockkeeping_variant_id')));
    }

    // ── returns and cancels ────────────────────────────────────────────────────

    private function adjusts(): array
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), 'stock-adjust'))->map(fn ($p) => $p[0]['items'] ?? $p[0]->data()['items'])->values()->all();
    }

    private function receive(Order $o, ProductVariant $v, int $qty): void
    {
        $ret = ReturnRequest::create(['order_id' => $o->id, 'status' => 'requested', 'reason' => 't',
            'items' => [['product_variant_id' => $v->id, 'sku' => $v->sku, 'stockkeeping_variant_id' => $v->stockkeeping_variant_id, 'quantity' => $qty, 'name' => 'x']]]);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch(route('admin.returns.update', $ret), ['status' => 'received'])->assertRedirect();
    }

    public function test_a_return_of_an_unlinked_kit_restocks_its_components_locally_and_in_stoqs(): void
    {
        Http::fake(['stoqs.test/api/v1/stock-adjust' => Http::response(['ok' => true])]);
        $own = $this->makeKit('kit-own', 1_500_000, null, null);
        $this->receive($this->order($own)->fresh(), $this->placeholder($own), 2);

        $this->assertSame([[['variant_id' => 11, 'delta' => 2], ['variant_id' => 21, 'delta' => 4]]], $this->adjusts());
        $this->assertSame([12, 14], [(int) $this->a->variants()->first()->fresh()->stock_qty, (int) $this->b->variants()->first()->fresh()->stock_qty]);
        $this->assertSame(0, (int) $this->placeholder($own)->fresh()->stock_qty, 'the placeholder never holds stock');
    }

    public function test_a_return_of_a_linked_kit_sends_the_kit_variant(): void
    {
        Http::fake(['stoqs.test/api/v1/stock-adjust' => Http::response(['ok' => true])]);
        $o = $this->order($this->kit)->fresh();
        $this->receive($o, $this->placeholder($this->kit), 2);

        // 5.2.130: the kit line names its sale (the order number the sale was reported with)
        $this->assertSame([[['variant_id' => 91, 'delta' => 2, 'sale_ref' => $o->number]]], $this->adjusts());
        $this->assertSame([12, 14], [(int) $this->a->variants()->first()->fresh()->stock_qty, (int) $this->b->variants()->first()->fresh()->stock_qty], 'the shop\'s own component stock goes up too');
    }

    public function test_cancelling_a_paid_kit_order_restocks_the_same_way_and_plain_lines_are_unchanged(): void
    {
        Http::fake(['stoqs.test/api/v1/stock-adjust' => Http::response(['ok' => true])]);
        $admin = User::factory()->create(['is_admin' => true]);
        $own = $this->makeKit('kit-own', 1_500_000, null, null);

        $this->actingAs($admin)->patch(route('admin.orders.status', $this->order($own)), ['status' => 'canceled'])->assertRedirect();
        $linkedOrder = $this->order($this->kit, 1);
        $this->actingAs($admin)->patch(route('admin.orders.status', $linkedOrder), ['status' => 'canceled'])->assertRedirect();
        $plain = Order::create(['number' => 'T-plain', 'status' => 'paid', 'subtotal' => 1, 'shipping_cost' => 0, 'discount' => 0, 'total' => 1, 'customer_name' => 'x', 'customer_phone' => '09120000000']);
        $av = $this->a->variants()->first();
        OrderItem::create(['order_id' => $plain->id, 'product_id' => $this->a->id, 'product_variant_id' => $av->id, 'name' => 'الف', 'size' => 'M', 'sku' => '1001',
            'stockkeeping_variant_id' => 11, 'unit_price' => 1, 'quantity' => 3, 'line_total' => 3]);
        $this->actingAs($admin)->patch(route('admin.orders.status', $plain), ['status' => 'canceled'])->assertRedirect();

        $this->assertSame([
            [['variant_id' => 11, 'delta' => 2], ['variant_id' => 21, 'delta' => 4]],   // unlinked kit: components
            [['variant_id' => 91, 'delta' => 1, 'sale_ref' => $linkedOrder->number]],     // linked kit: the kit variant + its sale
            [['variant_id' => 11, 'delta' => 3]],                                          // plain line
        ], $this->adjusts());
    }

    // ── making a kit in StoqS ──────────────────────────────────────────────────

    public function test_a_shop_kit_is_created_in_stoqs_with_bundle_and_no_variants(): void
    {
        $own = $this->makeKit('kit-own', 1_400_000, null, null);
        Http::fake(['stoqs.test/api/v1/products' => Http::response(['ok' => true, 'product_id' => 55, 'variants' => [['variant_id' => 551, 'barcode' => '5501']]], 201)]);

        $r = StoqsContent::createInStoqs($own);

        $this->assertTrue($r['ok'], $r['message']);
        Http::assertSent(fn (Request $q) => str_ends_with($q->url(), '/products') && $q['base_price'] === 1_400_000 && ! isset($q['variants'])
            && $q['bundle']['items'] === [['variant_id' => 11, 'qty' => 1], ['variant_id' => 21, 'qty' => 2]]);
        $this->assertSame('55', (string) $own->fresh()->stockkeeping_id);
        $ph = $this->placeholder($own);
        $this->assertSame([551, '5501'], [(int) $ph->stockkeeping_variant_id, $ph->sku]);

        $unlinkedPart = $this->makeKit('kit-bad', 1, null, null);
        $this->a->variants()->update(['stockkeeping_variant_id' => null, 'sku' => null]);
        $this->assertFalse(StoqsContent::createInStoqs($unlinkedPart)['ok']);
    }
}

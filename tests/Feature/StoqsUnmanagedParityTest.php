<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OutletItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use App\Services\StockKeeping\CatalogImporter;
use App\Services\StockKeeping\HttpStockKeepingClient;
use App\Services\StockKeeping\StockKeepingReporter;
use App\Services\StockKeeping\StoqsPromotions;
use App\Services\StockKeeping\StoqsSites;
use App\Support\CampaignPlan;
use App\Support\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * When StoqS does not manage this site's promotions (no /promotions row yet, `managed: false`,
 * or the StoqS link switched off) the shop behaves exactly as before: its own coupons, rules,
 * outlet and catalog sale prices count, and the sale report carries nothing new. The rest of
 * the suite (written before Promotions API v2) runs in this mode too.
 */
class StoqsUnmanagedParityTest extends TestCase
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
        Coupon::create(['code' => 'LOCAL10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
    }

    /** A StoqS payload full of windows, rules and codes, but `managed: false`: none of it may count. */
    private function unmanagedRow(): void
    {
        $payload = [
            'ok' => true, 'schema' => 2, 'version' => 9, 'managed' => false,
            'settings' => ['max_discount_percent' => 50],
            'plans' => [['id' => 7, 'version' => 1]],
            'windows' => [['id' => 'c3', 'source' => 'campaign', 'campaign_id' => 3, 'plan_id' => 7, 'version' => 1, 'pct' => 50, 'from_utc' => null, 'to_utc' => null]],
            'variants' => [['variant_ids' => [11, 12], 'product_id' => 1, 'list_price' => 1_000_000, 'prices' => [['c3', 500_000]]]],
            'outlet' => ['c3'],
            'rules' => [['id' => 31, 'label' => 'x', 'conditions' => [], 'actions' => [['type' => 'cart_discount_percent', 'value' => 40]], 'enabled' => 1, 'channels' => 'web']],
            'codes' => [['id' => 41, 'code' => 'STQ5', 'type' => 'toman', 'value' => 50_000, 'enabled' => 1, 'channels' => 'web']],
        ];
        DB::table('stoqs_promotions')->updateOrInsert(['id' => 1], [
            'managed' => false, 'version' => 9, 'etag' => '"v9"', 'payload' => json_encode($payload), 'created_at' => now(), 'updated_at' => now(),
        ]);
        StoqsPromotions::forget();
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

    /** What the cart and its line discounts come to with LOCAL10 on one S. */
    private function cartSnapshot(): array
    {
        $cart = $this->cart();
        $cart->applyCoupon('LOCAL10');
        $cart = $this->cart();

        return [
            'subtotal' => $cart->subtotal(), 'discount' => $cart->combinedDiscount(), 'coupon' => $cart->appliedCoupon()?->code,
            'lines' => $cart->lineDiscounts(), 'stoqs_code' => $cart->applyCoupon('STQ5')['ok'],
        ];
    }

    public function test_no_row_and_managed_false_price_the_cart_identically(): void
    {
        $this->post(route('cart.add'), ['variant_id' => ProductVariant::where('size', 'S')->value('id'), 'quantity' => 1]);
        $this->assertFalse(StoqsPromotions::managed());
        $before = $this->cartSnapshot();
        $this->assertSame(1_000_000, $before['subtotal']);
        $this->assertSame('LOCAL10', $before['coupon']);
        $this->assertSame(100_000, $before['discount']);
        $this->assertFalse($before['stoqs_code']);

        $this->cart()->removeCoupon();
        $this->unmanagedRow();
        $this->assertFalse(StoqsPromotions::managed());
        StoqsPromotions::apply();
        $this->assertSame($before, $this->cartSnapshot());
        $this->assertNull(ProductVariant::where('size', 'S')->value('promo_price'));
        $this->assertSame(1_000_000, $this->product->fresh()->price);
    }

    public function test_a_managed_row_counts_for_nothing_while_the_stoqs_link_is_off(): void
    {
        DB::table('stoqs_promotions')->insert(['id' => 1, 'managed' => true, 'payload' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        StoqsPromotions::forget();
        $this->assertTrue(StoqsPromotions::managed());

        config(['stockkeeping.enabled' => false]);
        $this->assertFalse(StoqsPromotions::managed());
    }

    public function test_the_shops_outlet_and_plan_work_as_before(): void
    {
        $this->unmanagedRow();

        $this->assertSame([], Outlet::sync(['manto-1' => 20]));
        $this->assertSame([$this->product->id => 20], Outlet::percents());
        $p = $this->product->fresh();
        $this->assertSame(800_000, $p->price);
        $this->assertSame(1_000_000, $p->compare_at_price);

        $this->assertSame([], CampaignPlan::createCoupon(['code' => 'NEW10', 'type' => 'percent', 'value' => 10]));
        $this->assertSame([], CampaignPlan::editorPayload()['locked']);
        $this->assertSame(StoqsSites::CAPABILITIES, StoqsSites::capabilities());
    }

    public function test_the_catalog_import_sets_the_same_prices(): void
    {
        $import = function (): array {
            $this->product->forceFill(['price' => 1_000_000, 'compare_at_price' => null])->save();
            app(CatalogImporter::class)->importProduct([
                'product_id' => 1, 'name' => 'مانتو', 'base_price' => 1_000_000, 'price' => 1_000_000, 'active' => true, 'web_published' => true,
                'sale_price' => 800_000, 'compare_at_price' => 1_000_000, 'on_sale' => true,
                'variants' => [
                    ['variant_id' => 11, 'barcode' => '1001', 'size' => 'S', 'active' => true, 'price' => 1_000_000, 'sale_price' => 800_000],
                    ['variant_id' => 12, 'barcode' => '1002', 'size' => 'M', 'active' => true, 'price' => 900_000, 'sale_price' => null],
                ],
            ]);
            StoqsPromotions::forget();

            return [
                $this->product->fresh()->only(['price', 'compare_at_price']),
                ProductVariant::orderBy('id')->get(['price', 'stoqs_size_price', 'promo_price'])->toArray(),
            ];
        };

        $before = $import();
        $this->unmanagedRow();
        $this->assertSame($before, $import());
        $this->assertNull(ProductVariant::where('size', 'S')->value('promo_price'));
    }

    public function test_a_promotions_webhook_saying_unmanaged_changes_nothing(): void
    {
        Http::fake(['stoqs.test/api/v1/promotions*' => Http::response(['ok' => true, 'version' => 2, 'managed' => false, 'windows' => [], 'variants' => []], 200, ['ETag' => '"v2"'])]);
        $raw = json_encode(['event' => 'promotions.changed', 'event_id' => 'e1', 'occurred_at' => now()->toAtomString(), 'data' => ['promo_version' => 2, 'reason' => 'site_source']]);

        $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
        ], $raw)->assertOk();

        $this->assertFalse(StoqsPromotions::managed());
        $this->assertSame(1_000_000, $this->product->fresh()->price);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'promotions/ack'));
        Http::assertSentCount(1);
    }

    public function test_discounts_changed_does_not_pull_promotions(): void
    {
        Http::fake();
        $raw = json_encode(['event' => 'discounts.changed', 'event_id' => 'e2', 'occurred_at' => now()->toAtomString(), 'data' => []]);
        $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
        ], $raw)->assertOk();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'promotions'));
    }

    public function test_the_sale_report_carries_nothing_new(): void
    {
        $this->unmanagedRow();
        $order = Order::forceCreate([
            'number' => 'T-1', 'status' => Order::STATUS_PAID, 'subtotal' => 1_000_000, 'shipping_cost' => 0, 'discount' => 0, 'total' => 1_000_000,
            'shipping_address' => [], 'placed_at' => now(),
        ] + $this->orderDefaults());
        $order->items()->create([
            'product_variant_id' => ProductVariant::where('size', 'S')->value('id'), 'name' => 'مانتو', 'size' => 'S',
            'sku' => '1001', 'stockkeeping_variant_id' => 11, 'unit_price' => 1_000_000, 'quantity' => 1, 'line_total' => 1_000_000,
            'discounts' => [['type' => 'price_sheet', 'amount' => 100_000]],
        ] + $this->itemDefaults());

        $sale = app(StockKeepingReporter::class)->buildSalePayload($order->fresh());
        foreach (['ordered_at', 'eval', 'site_url'] as $k) {
            $this->assertArrayNotHasKey($k, $sale);
        }
        $this->assertArrayNotHasKey('list_price', $sale['items'][0]);

        Http::fake(['*' => Http::response(['batch_id' => 'b'])]);
        $this->client()->reportSale($sale);
        Http::assertSent(function (Request $r) {
            $i = $r['items'][0];

            return str_ends_with($r->url(), '/api/v1/sales') && ! isset($r['ordered_at'], $r['eval'], $r['site_url'])
                && ! isset($i['list_price']) && $i['unit_price'] === 1_100_000
                && $i['discounts'] === [['type' => 'price_sheet', 'amount' => 100_000]];
        });
    }

    /** Required order columns this shop's schema has beyond the common ones. */
    private function orderDefaults(): array
    {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('orders');

        return array_intersect_key(['user_id' => \App\Models\User::factory()->create()->id, 'payment_method' => 'zarinpal'], array_flip($cols));
    }

    private function itemDefaults(): array
    {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('order_items');

        return array_intersect_key(['product_id' => $this->product->id], array_flip($cols));
    }
}

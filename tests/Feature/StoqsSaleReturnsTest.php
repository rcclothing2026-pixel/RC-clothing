<?php

namespace Tests\Feature;

use App\Models\BundleItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\StockkeeepingLog;
use App\Models\User;
use App\Services\StockKeeping\StoqsSites;
use App\Services\Telegram\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * StoqS 5.2.133: refunds and cancels go through POST /sales/returns when hello advertised
 * `sale_returns`; otherwise the stock-adjust path is exactly as before.
 */
class StoqsSaleReturnsTest extends TestCase
{
    use RefreshDatabase;

    private Product $a;

    private Product $b;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k',
            'stockkeeping.fulfillment_location' => 'warehouse:1', 'app.url' => 'https://shop.test', 'scout.driver' => null,
        ]);
        Http::preventStrayRequests();
        $this->a = $this->plain('p-a', '1001', 11);
        $this->b = $this->plain('p-b', '2001', 21);
    }

    private function features(bool $on): void
    {
        Setting::put('stockkeeping.features', $on ? ['sale_returns'] : []);
    }

    private function plain(string $slug, string $sku, int $svid): Product
    {
        $p = Product::create(['name' => $slug, 'slug' => $slug, 'price' => 1000, 'stockkeeping_id' => (string) $svid, 'is_active' => true]);
        ProductVariant::create(['product_id' => $p->id, 'sku' => $sku, 'size' => 'M', 'stock_qty' => 10, 'is_active' => true, 'stockkeeping_variant_id' => $svid]);

        return $p;
    }

    private function kit(): Product
    {
        $k = Product::create(['name' => 'kit', 'slug' => 'kit', 'price' => 5000, 'stockkeeping_id' => '9', 'is_bundle' => true, 'is_active' => true]);
        ProductVariant::create(['product_id' => $k->id, 'sku' => '791', 'size' => '—', 'stock_qty' => 0, 'is_active' => true, 'stockkeeping_variant_id' => 91]);
        BundleItem::create(['bundle_product_id' => $k->id, 'product_variant_id' => $this->a->variants()->first()->id, 'quantity' => 1, 'position' => 0]);
        BundleItem::create(['bundle_product_id' => $k->id, 'product_variant_id' => $this->b->variants()->first()->id, 'quantity' => 2, 'position' => 1]);

        return $k;
    }

    private function order(Product $p, int $qty, string $number = 'T-100', string $status = 'paid'): Order
    {
        $o = Order::create(['number' => $number, 'status' => $status, 'subtotal' => 1, 'shipping_cost' => 0, 'discount' => 0, 'total' => 1, 'customer_name' => 'x', 'customer_phone' => '09120000000']);
        $v = $p->variants()->first();
        OrderItem::create(['order_id' => $o->id, 'product_id' => $p->id, 'product_variant_id' => $v->id, 'name' => 'x', 'size' => 'M', 'sku' => $v->sku,
            'stockkeeping_variant_id' => $v->stockkeeping_variant_id, 'unit_price' => 1, 'quantity' => $qty, 'line_total' => $qty]);

        return $o->fresh();
    }

    private function receive(Order $o, ProductVariant $v, int $qty): void
    {
        $ret = ReturnRequest::create(['order_id' => $o->id, 'status' => 'requested', 'reason' => 't',
            'items' => [['product_variant_id' => $v->id, 'sku' => $v->sku, 'stockkeeping_variant_id' => $v->stockkeeping_variant_id, 'quantity' => $qty, 'name' => 'x']]]);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch(route('admin.returns.update', $ret), ['status' => 'received'])->assertRedirect();
    }

    private function sent(string $suffix): array
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), $suffix))->map(fn ($p) => $p[0]->data())->values()->all();
    }

    private function ok(): void
    {
        Http::fake(['stoqs.test/api/v1/sales/returns' => Http::response(['ok' => true, 'idempotent' => false], 201), 'stoqs.test/api/v1/stock-adjust' => Http::response(['ok' => true])]);
    }

    public function test_hello_stores_the_features_and_supports_reads_them(): void
    {
        Http::fake(['stoqs.test/api/v1/sites/hello' => Http::sequence()->push(['ok' => true, 'features' => ['sale_returns']])->push(['ok' => true])]);
        $this->assertFalse(StoqsSites::supports('sale_returns'));
        StoqsSites::hello();
        $this->assertTrue(StoqsSites::supports('sale_returns'));
        $this->assertFalse(StoqsSites::supports('nonsense'));

        // the second answer is an older StoqS
        StoqsSites::hello();
        $this->assertFalse(StoqsSites::supports('sale_returns'));
    }

    public function test_a_received_return_goes_to_the_returns_api_and_not_to_stock_adjust(): void
    {
        $this->features(true);
        $this->ok();
        $o = $this->order($this->a, 3);
        $v = $this->a->variants()->first();
        $this->receive($o, $v, 2);

        $this->assertSame([[
            'sale_ref' => 'T-100', 'return_id' => 'RET-1', 'kind' => 'items',
            'lines' => [['variant_id' => 11, 'quantity' => 2, 'restock' => true]], 'reason' => 'refund',
        ]], $this->sent('sales/returns'));
        $this->assertSame([], $this->sent('stock-adjust'));
        $this->assertSame(12, (int) $v->fresh()->stock_qty, 'the shop keeps its own stock');
        $this->assertSame('ok', StockkeeepingLog::where('event_type', 'sale_return')->firstOrFail()->status);
    }

    public function test_a_cancelled_order_is_a_full_return(): void
    {
        $this->features(true);
        $this->ok();
        $o = $this->order($this->a, 3);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch(route('admin.orders.status', $o), ['status' => 'canceled'])->assertRedirect();

        $this->assertSame([['sale_ref' => 'T-100', 'return_id' => 'CANCEL-T-100', 'kind' => 'full', 'reason' => 'cancel']], $this->sent('sales/returns'));
        $this->assertSame([], $this->sent('stock-adjust'));
        $this->assertSame(13, (int) $this->a->variants()->first()->fresh()->stock_qty);
    }

    public function test_a_telegram_cancel_is_the_same_full_return(): void
    {
        $this->features(true);
        $this->ok();
        $o = $this->order($this->a, 1);
        $m = new \ReflectionMethod(TelegramNotifier::class, 'restockCancelled');
        $m->setAccessible(true);
        $m->invoke(app(TelegramNotifier::class), $o);

        $this->assertSame([['sale_ref' => 'T-100', 'return_id' => 'CANCEL-T-100', 'kind' => 'full', 'reason' => 'cancel']], $this->sent('sales/returns'));
        $this->assertSame([], $this->sent('stock-adjust'));
    }

    public function test_a_kit_item_is_one_placeholder_line_with_the_kit_count(): void
    {
        $this->features(true);
        $this->ok();
        $kit = $this->kit();
        $o = $this->order($kit, 2);
        $this->receive($o, $kit->variants()->first(), 2);

        $this->assertSame([['variant_id' => 91, 'quantity' => 2, 'restock' => true]], $this->sent('sales/returns')[0]['lines']);
        $this->assertSame([12, 14], [(int) $this->a->variants()->first()->fresh()->stock_qty, (int) $this->b->variants()->first()->fresh()->stock_qty]);
    }

    public function test_a_shop_fulfilled_order_is_still_sent(): void
    {
        config(['stockkeeping.fulfillment_location' => 'shop:3']);
        $this->features(true);
        $this->ok();
        $o = $this->order($this->a, 1);
        $this->receive($o, $this->a->variants()->first(), 1);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch(route('admin.orders.status', $this->order($this->b, 1, 'T-200')), ['status' => 'canceled'])->assertRedirect();

        $this->assertSame(['RET-1', 'CANCEL-T-200'], array_column($this->sent('sales/returns'), 'return_id'));
    }

    public function test_an_old_stoqs_keeps_the_stock_adjust_path_exactly(): void
    {
        $this->features(false);
        $this->ok();
        $o = $this->order($this->a, 3);
        $this->receive($o, $this->a->variants()->first(), 2);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch(route('admin.orders.status', $this->order($this->b, 1, 'T-200')), ['status' => 'canceled'])->assertRedirect();

        $this->assertSame([], $this->sent('sales/returns'));
        $this->assertSame([[['variant_id' => 11, 'delta' => 2]], [['variant_id' => 21, 'delta' => 1]]], array_column($this->sent('stock-adjust'), 'items'));
        $this->assertSame(['return_restock', 'cancel_restock'], StockkeeepingLog::whereIn('event_type', ['return_restock', 'cancel_restock'])->orderBy('id')->pluck('event_type')->all());
    }

    public function test_404_and_409_answers_are_logged_and_not_retried(): void
    {
        $this->features(true);
        Http::fake(['stoqs.test/api/v1/sales/returns' => Http::sequence()
            ->push(['ok' => false, 'error' => ['code' => 'sale_not_found', 'message' => 'no']], 404)
            ->push(['ok' => false, 'error' => ['code' => 'return_conflict', 'message' => 'diff']], 409)]);
        $o = $this->order($this->a, 3);
        $this->receive($o, $this->a->variants()->first(), 1);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->patch(route('admin.orders.status', $this->order($this->b, 1, 'T-200')), ['status' => 'canceled'])->assertRedirect();

        $this->assertCount(2, $this->sent('sales/returns'), 'one call each, no retry');
        $this->assertSame([], $this->sent('stock-adjust'), 'no fallback');
        $logs = StockkeeepingLog::where('event_type', 'sale_return')->orderBy('id')->get();
        $this->assertSame(['failed', 'failed'], $logs->pluck('status')->all());
        $this->assertStringContainsString('sale_not_found', $logs[0]->error);
        $this->assertStringContainsString('return_conflict', $logs[1]->error);
    }
}

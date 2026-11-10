<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\IntegrationEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutletItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\Cart;
use App\Services\StockKeeping\HttpStockKeepingClient;
use App\Services\StockKeeping\StockKeepingReporter;
use App\Services\StockKeeping\StoqsApi;
use App\Services\StockKeeping\StoqsContent;
use App\Services\StockKeeping\StoqsSites;
use App\Support\Margin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Content both ways with StoqS (StoqsContent), site links (StoqsSites), coupon
 * registration (StoqsCodes) and the discount breakdown on the sale report.
 */
class StoqsContentSyncTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private const API = 'https://stoqs.test/api/v1/';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k',
            'stockkeeping.webhook_secret' => self::SECRET, 'scout.driver' => null, 'app.url' => 'https://shop.test',
        ]);
        Storage::fake('public');
        Http::preventStrayRequests();   // nothing here may reach a real server
    }

    private function product(array $attrs = []): Product
    {
        $p = Product::create($attrs + ['name' => 'مانتو', 'slug' => 'manto-1', 'price' => 1_000_000, 'stockkeeping_id' => '1', 'is_active' => true]);
        ProductVariant::create(['product_id' => $p->id, 'sku' => '1001', 'size' => 'S', 'stock_qty' => 3, 'is_active' => true, 'stockkeeping_variant_id' => 11]);
        ProductVariant::create(['product_id' => $p->id, 'sku' => '1002', 'size' => 'M', 'stock_qty' => 3, 'is_active' => true, 'stockkeeping_variant_id' => 12]);

        return $p;
    }

    /** web_product_payload shape (v5.2.111). */
    private function payload(array $over = []): array
    {
        return $over + [
            'product_id' => 1, 'name' => 'مانتو نو', 'base_price' => 1_000_000, 'price' => 1_000_000,
            'description' => '<p>توضیح StoqS</p>', 'short_description' => '<p>خلاصهٔ <b>StoqS</b></p>',
            'active' => true, 'web_published' => true, 'sale_price' => null, 'compare_at_price' => null,
            'content_updated_at' => '2026-11-10T10:00:00+03:30',
            'images' => [
                ['id' => 501, 'url' => 'https://stoqs.test/m/a.jpg', 'alt' => 'جلو', 'position' => 0, 'sha256' => str_repeat('a', 64)],
                ['id' => 502, 'url' => 'https://stoqs.test/m/b.jpg', 'alt' => 'پشت', 'position' => 1, 'sha256' => str_repeat('b', 64)],
            ],
            'variants' => [
                ['variant_id' => 11, 'barcode' => '1001', 'size' => 'S', 'active' => true, 'price' => 1_000_000, 'price_override' => false, 'sale_price' => null, 'floor_price' => 700_000],
                ['variant_id' => 12, 'barcode' => '1002', 'size' => 'M', 'active' => true, 'price' => 1_200_000, 'price_override' => true, 'sale_price' => null, 'floor_price' => 700_000],
            ],
        ];
    }

    private function webhook(string $event, array $data, string $secret = self::SECRET)
    {
        $raw = json_encode(['event' => $event, 'event_id' => 'e1', 'brand_id' => 2, 'occurred_at' => now()->toAtomString(), 'data' => $data]);

        return $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_STOQS_EVENT' => $event,
            'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, $secret),
        ], $raw);
    }

    private function fakeImages(): void
    {
        Http::fake(['stoqs.test/m/*' => Http::response('JPEGDATA', 200, ['Content-Type' => 'image/jpeg'])]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /** The admin form as it posts for $p, with $over on top. */
    private function form(Product $p, array $over = []): array
    {
        return $over + [
            'name' => $p->name, 'summary' => $p->summary, 'description' => $p->description, 'price' => $p->price,
            'is_active' => 1, 'stoqs_sync_mode' => $p->stoqs_sync_mode,
            'variants' => $p->variants->map(fn ($v) => ['id' => $v->id, 'size' => $v->size, 'sku' => $v->sku, 'price' => $v->price,
                'stock_qty' => $v->stock_qty, 'is_active' => 1, 'stockkeeping_variant_id' => $v->stockkeeping_variant_id])->all(),
        ];
    }

    public function test_ping_answers_pong_and_a_bad_signature_is_401(): void
    {
        $this->webhook('ping', ['message' => 'hi', 'webhook_id' => 3])
            ->assertOk()->assertExactJson(['ok' => true, 'pong' => true, 'site' => 'racketclub']);
        $this->webhook('ping', ['message' => 'hi'], 'wrong')->assertStatus(401);
        $this->webhook('discounts.changed', [])->assertOk();
        $this->webhook('something.new', [])->assertOk();
    }

    public function test_photos_are_not_pulled_while_pull_images_is_off(): void
    {
        $this->fakeImages();
        $this->assertFalse((bool) config('stockkeeping.pull_images'));   // the default
        $p = $this->product();
        $a = $p->images()->create(['path' => '/storage/products/a.jpg', 'position' => 1, 'is_primary' => true]);
        $b = $p->images()->create(['path' => '/storage/products/b.jpg', 'position' => 2]);

        $this->webhook('product.content_changed', ['product_id' => 1, 'fields' => ['images'], 'product' => $this->payload()])->assertOk();
        app(\App\Services\StockKeeping\CatalogImporter::class)->importProduct($this->payload(), true);   // as «دریافت از StoqS»

        $p->refresh();
        $this->assertSame('مانتو نو', $p->name);   // text still arrives
        $this->assertSame([$a->id, $b->id], $p->images()->orderBy('position')->pluck('id')->all());
        $this->assertSame(0, $p->images()->whereNotNull('stoqs_media_id')->count());
        $this->assertCount(0, Http::recorded(fn (Request $r) => str_contains($r->url(), '/m/')));
    }

    public function test_content_changed_imports_text_prices_and_photos_in_stoqs_order(): void
    {
        config(['stockkeeping.pull_images' => true]);
        $this->fakeImages();
        $p = $this->product(['summary' => 'خلاصهٔ قدیمی']);
        $local = $p->images()->create(['path' => '/storage/products/own.jpg', 'alt' => 'own', 'position' => 1]);

        $this->webhook('product.content_changed', ['product_id' => 1, 'fields' => ['name', 'images'], 'product' => $this->payload()])->assertOk();

        $p->refresh();
        $this->assertSame('مانتو نو', $p->name);
        $this->assertSame('<p>توضیح StoqS</p>', $p->description);
        $this->assertSame('خلاصهٔ StoqS', $p->summary);
        $this->assertSame(1_200_000, ProductVariant::where('sku', '1002')->first()->price);   // per-size price
        $this->assertNull(ProductVariant::where('sku', '1001')->first()->price);
        $this->assertSame(700_000, $p->stoqs_floor_price);
        $album = $p->images()->orderBy('position')->get();
        $this->assertSame([501, 502, null], $album->pluck('stoqs_media_id')->all());   // StoqS order, shop-only photo last
        $this->assertTrue($album[0]->is_primary);
        $this->assertSame($local->id, $album[2]->id);
        $this->assertSame(0, IntegrationEvent::where('type', StoqsContent::TYPE)->count());   // nothing echoed back

        // Again with 502 removed and 501 unchanged: 501 is reused, 502 leaves the album.
        $this->webhook('product.content_changed', ['product_id' => 1, 'fields' => ['images'],
            'product' => $this->payload(['images' => [$this->payload()['images'][0]]])])->assertOk();
        $this->assertSame([501, null], $p->images()->orderBy('position')->pluck('stoqs_media_id')->all());
        $this->assertSame($album[0]->id, $p->images()->where('stoqs_media_id', 501)->value('id'));
        $this->assertCount(2, Http::recorded(fn (Request $r) => str_contains($r->url(), '/m/')));   // two downloads in total
    }

    public function test_stock_only_and_ignore_keep_content(): void
    {
        $p = $this->product(['stoqs_sync_mode' => 'stock_only']);
        $this->webhook('product.content_changed', ['product_id' => 1, 'product' => $this->payload(['images' => []])])->assertOk();
        $this->assertSame('مانتو', $p->fresh()->name);

        $p->update(['stoqs_sync_mode' => 'ignore']);
        $this->webhook('stock.changed', ['barcode' => '1001', 'new_stock' => 0])->assertOk();
        $this->assertSame(3, ProductVariant::where('sku', '1001')->first()->stock_qty);
    }

    public function test_admin_edit_is_queued_and_delivered_with_if_match(): void
    {
        $this->fakeImages();
        $p = $this->product();
        StoqsContent::remember($p, $this->payload(['name' => 'مانتو', 'description' => '', 'short_description' => '']));
        Http::fake(['stoqs.test/api/v1/products/1' => Http::response(['ok' => true, 'product' => $this->payload(['name' => 'مانتو تابستانی', 'content_updated_at' => '2026-11-10T11:00:00+03:30'])])]);

        $this->actingAs($this->admin())->put(route('admin.products.update', $p), $this->form($p->fresh('variants'), ['name' => 'مانتو تابستانی']))
            ->assertRedirect();

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->url() === self::API.'products/1'
            && $r['name'] === 'مانتو تابستانی' && $r['if_match'] === '2026-11-10T10:00:00+03:30');
        $this->assertSame('sent', IntegrationEvent::where('type', StoqsContent::TYPE)->first()->status);
        $this->assertSame('2026-11-10T11:00:00+03:30', $p->fresh()->stoqs_meta['p'][1]['at']);
    }

    public function test_size_price_edit_patches_the_variant(): void
    {
        $p = $this->product();
        StoqsContent::remember($p, $this->payload());
        Http::fake(['stoqs.test/api/v1/variants/*' => Http::response(['ok' => true, 'variant' => []])]);

        $form = $this->form($p->fresh('variants'));
        $form['variants'][0]['price'] = 900_000;
        $this->actingAs($this->admin())->put(route('admin.products.update', $p), $form)->assertRedirect();

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->url() === self::API.'variants/11' && $r['price'] === 900_000);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'products/1'));
    }

    public function test_a_pending_shop_edit_is_not_overwritten_by_an_import(): void
    {
        $p = $this->product();
        StoqsContent::enqueue($p, ['name']);   // waiting (e.g. StoqS was down)
        $this->webhook('product.content_changed', ['product_id' => 1, 'product' => $this->payload(['images' => []])])->assertOk();
        $this->assertSame('مانتو', $p->fresh()->name);
        $this->assertSame('<p>توضیح StoqS</p>', $p->fresh()->description);   // other fields still arrive
    }

    public function test_409_merges_three_way_and_stoqs_wins_where_both_changed(): void
    {
        $p = $this->product(['description' => 'شرح سایت', 'summary' => 'خلاصه سایت']);
        StoqsContent::remember($p, $this->payload(['name' => 'مانتو', 'description' => 'شرح قدیم', 'short_description' => 'خلاصه قدیم']));
        $theirs = $this->payload(['name' => 'مانتو', 'description' => 'شرح StoqS', 'short_description' => 'خلاصه قدیم', 'content_updated_at' => '2026-11-10T12:00:00+03:30']);
        Http::fake(['stoqs.test/api/v1/products/1' => Http::sequence()
            ->push(['ok' => false, 'error' => ['code' => 'conflict', 'message' => 'changed', 'product' => $theirs]], 409)
            ->push(['ok' => true, 'product' => ['short_description' => 'خلاصه سایت'] + $theirs])]);

        $ev = StoqsContent::enqueue($p, ['description', 'short_description']);
        $this->assertTrue(StoqsContent::send($ev));

        // Second PATCH: only the field StoqS did not touch, with the new if_match.
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && ($r['if_match'] ?? '') === '2026-11-10T12:00:00+03:30'
            && $r['short_description'] === 'خلاصه سایت' && ! isset($r['description']));
        $p->refresh();
        $this->assertSame('شرح StoqS', $p->description);   // both changed → StoqS wins
        $this->assertStringContainsString('توضیحات', $p->stoqs_meta['note']['text']);
        $this->assertSame(0, IntegrationEvent::where('type', StoqsContent::TYPE)->where('status', 'pending')->count());   // no echo queued
    }

    public function test_photos_go_up_removed_ones_are_deleted_and_order_is_sent(): void
    {
        $this->assertFalse((bool) config('stockkeeping.pull_images'));   // pushes work with pulling off
        $p = $this->product();
        StoqsContent::remember($p, $this->payload());
        $p->forceFill(['stoqs_meta' => array_replace_recursive($p->stoqs_meta, ['p' => [1 => ['m' => [501, 502]]]])])->saveQuietly();
        Storage::disk('public')->put('products/new.jpg', 'IMG');
        $p->images()->create(['path' => '/storage/products/new.jpg', 'alt' => 'نو', 'position' => 1]);
        $keep = $p->images()->create(['path' => '/storage/products/x.jpg', 'position' => 2]);
        $keep->forceFill(['stoqs_media_id' => 501])->save();
        Http::fake([
            'stoqs.test/api/v1/products/1/media/order' => Http::response(['ok' => true, 'images' => [['id' => 900], ['id' => 501]]]),
            'stoqs.test/api/v1/products/1/media' => Http::response(['ok' => true, 'image' => ['id' => 900, 'sha256' => str_repeat('c', 64)]], 201),
            'stoqs.test/api/v1/media/*' => Http::response(['ok' => true]),
        ]);

        $this->assertTrue(StoqsContent::send(StoqsContent::enqueue($p, ['images'])));

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'products/1/media' && $r->isMultipart());
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::API.'media/502');
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['ids'] === [900, 501]);
        $this->assertSame([900, 501], $p->fresh()->stoqs_meta['p'][1]['m']);
    }

    public function test_create_in_stoqs_links_sizes_by_returned_ids(): void
    {
        $p = Product::create(['name' => 'تیشرت', 'slug' => 'tee', 'price' => 500_000, 'is_active' => true, 'summary' => 'نخی']);
        $a = ProductVariant::create(['product_id' => $p->id, 'sku' => 'T-1', 'size' => 'L', 'stock_qty' => 1, 'is_active' => true]);
        $b = ProductVariant::create(['product_id' => $p->id, 'size' => 'XL', 'price' => 550_000, 'stock_qty' => 1, 'is_active' => true]);
        Http::fake(['stoqs.test/api/v1/products' => Http::response(['ok' => true, 'product_id' => 77,
            'variants' => [['variant_id' => 701, 'barcode' => 'T-1'], ['variant_id' => 702, 'barcode' => '2000000007025']]], 201)]);

        $this->actingAs($this->admin())->post(route('admin.products.stoqs', $p), ['stoqs_action' => 'create'])->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'products' && $r['base_price'] === 500_000 && $r['short_description'] === 'نخی'
            && $r['variants'][0] === ['barcode' => 'T-1', 'size' => 'L'] && $r['variants'][1]['price'] === 550_000 && $r['web_published'] === true);
        $this->assertSame('77', $p->fresh()->stockkeeping_id);
        $this->assertSame(701, (int) $a->fresh()->stockkeeping_variant_id);
        $this->assertSame('2000000007025', $b->fresh()->sku);
    }

    public function test_missing_scope_parks_the_row_logs_once_and_does_not_retry(): void
    {
        $p = $this->product();
        Http::fake(['stoqs.test/*' => Http::response(['ok' => false, 'error' => ['code' => 'forbidden_scope', 'message' => 'This key is not authorised for scope: catalog:write']], 403)]);

        $this->assertFalse(StoqsContent::send(StoqsContent::enqueue($p, ['name'])));
        $this->assertSame('blocked', IntegrationEvent::where('type', StoqsContent::TYPE)->first()->status);
        $this->assertArrayHasKey('catalog:write', StoqsApi::missingScopes());

        app(StockKeepingReporter::class)->flushPending();   // the 5-minute flush skips blocked rows
        Http::assertSentCount(1);
        $this->assertSame(1, \App\Models\StockkeeepingLog::where('ref', 'catalog:write')->count());

        $this->actingAs($this->admin())->get(route('admin.settings.integration'))->assertOk()->assertSee('منتظر دسترسی');
    }

    public function test_sites_hello_and_links(): void
    {
        $p = $this->product();
        Product::create(['name' => 'فقط سایت', 'slug' => 'only', 'price' => 1, 'is_active' => true]);
        Http::fake(['stoqs.test/api/v1/sites/hello' => Http::response(['ok' => true, 'site_id' => 4]),
            'stoqs.test/api/v1/sites/links' => Http::response(['ok' => true, 'count' => 1])]);

        StoqsSites::sync();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'sites/hello') && $r['kind'] === 'laravel' && $r['url'] === 'https://shop.test');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'sites/links')
            && $r['links'] === [['product_id' => 1, 'site_product_id' => (string) $p->id, 'url' => 'https://shop.test/product/manto-1']]);
    }

    public function test_coupons_are_registered_updated_and_deleted_in_stoqs(): void
    {
        Http::fake(['stoqs.test/api/v1/discounts/codes' => Http::response(['ok' => true, 'code' => ['id' => 55]], 201),
            'stoqs.test/api/v1/discounts/codes/55' => Http::response(['ok' => true, 'code' => ['id' => 55]])]);

        $c = Coupon::create(['code' => 'YALDA', 'type' => 'percent', 'value' => 15, 'is_active' => true, 'max_discount' => 300_000]);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['code'] === 'YALDA' && $r['type'] === 'percent'
            && $r['value'] === 15 && $r['channels'] === ['web'] && $r['external_id'] === (string) $c->id);
        $this->assertSame(55, (int) $c->fresh()->stoqs_code_id);

        $c->fresh()->update(['used_count' => 3]);   // usage is not content
        $c->fresh()->update(['value' => 20]);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['value'] === 20);
        $c->fresh()->delete();
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'codes/55'));
        Http::assertSentCount(3);
    }

    public function test_sale_report_carries_line_discounts_and_codes(): void
    {
        Http::fake(['stoqs.test/api/v1/discounts/*' => Http::response(['ok' => true, 'code' => ['id' => 1]])]);
        $cat = Category::create(['name' => 'tee', 'slug' => 'tee', 'is_active' => true]);
        $a = Product::create(['name' => 'A', 'slug' => 'a', 'price' => 1_000_000, 'category_id' => $cat->id, 'is_active' => true]);
        $b = Product::create(['name' => 'B', 'slug' => 'b', 'price' => 800_000, 'compare_at_price' => 1_000_000, 'category_id' => $cat->id, 'is_active' => true]);
        OutletItem::create(['product_id' => $b->id, 'percent' => 20, 'is_active' => true]);
        $va = ProductVariant::create(['product_id' => $a->id, 'sku' => 'A1', 'size' => 'L', 'stock_qty' => 5, 'is_active' => true, 'stockkeeping_variant_id' => 21]);
        $vb = ProductVariant::create(['product_id' => $b->id, 'sku' => 'B1', 'size' => 'L', 'stock_qty' => 5, 'is_active' => true, 'stockkeeping_variant_id' => 22]);
        Coupon::create(['code' => 'TEN', 'type' => 'fixed', 'value' => 100_000, 'is_active' => true]);
        DiscountRule::create(['name' => 'all 5', 'priority' => 1, 'conditions' => [], 'actions' => [['type' => 'cart_discount_percent', 'value' => 5]],
            'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true]);

        $cart = app(Cart::class);
        $cart->add($va->id, 2);
        $cart->add($vb->id);
        $cart->applyCoupon('TEN');
        $lines = $cart->lineDiscounts();

        $this->assertSame([['type' => 'code', 'code' => 'TEN', 'amount' => 100_000], ['type' => 'rule', 'amount' => 100_000]], $lines[$va->id]);
        $this->assertSame([['type' => 'price_sheet', 'amount' => 200_000]], $lines[$vb->id]);

        $order = Order::create(['number' => 'CH-T-1', 'status' => 'paid', 'subtotal' => 2_800_000, 'shipping_cost' => 0, 'discount' => 200_000,
            'coupon_code' => 'TEN', 'total' => 2_600_000, 'customer_name' => 'x', 'customer_phone' => '09120000000']);
        foreach ([[$va, 2, 1_000_000], [$vb, 1, 800_000]] as [$v, $q, $u]) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $v->product_id, 'product_variant_id' => $v->id, 'name' => 'x', 'size' => 'L',
                'sku' => $v->sku, 'stockkeeping_variant_id' => $v->stockkeeping_variant_id, 'unit_price' => $u, 'quantity' => $q,
                'line_total' => $u * $q, 'discounts' => $lines[$v->id]]);
        }
        $sale = app(StockKeepingReporter::class)->buildSalePayload($order->fresh(), ['name' => 'x', 'phone' => '0912']);
        Http::fake(['stoqs.test/api/v1/sales' => Http::response(['ok' => true, 'batch_id' => 'B1'], 201)]);
        (new HttpStockKeepingClient('https://stoqs.test', 'k', ['warehouse:1']))->reportSale($sale);

        Http::assertSent(function (Request $r) {
            if (! str_ends_with($r->url(), '/sales')) {
                return false;
            }
            [$ia, $ib] = $r['items'];

            return $r['codes'] === [['code' => 'TEN', 'amount' => 100_000]]
                && $ia['unit_price'] === 1_000_000 && $ia['discount_amount'] === 200_000 && count($ia['discounts']) === 2
                && $ib['unit_price'] === 1_000_000 && $ib['discount_amount'] === 200_000 && $ib['discounts'][0]['type'] === 'price_sheet';
        });
    }

    public function test_stoqs_floor_is_the_floor_without_a_local_cost(): void
    {
        $p = Product::create(['name' => 'F', 'slug' => 'f', 'price' => 1_000_000, 'is_active' => true]);
        $p->forceFill(['stoqs_floor_price' => 900_000])->saveQuietly();
        $this->assertSame(900_000, Margin::floorOf($p->id));
        \App\Models\ProductCost::create(['product_id' => $p->id, 'cost' => 450_000]);
        request()->attributes->remove('margin.floors');
        request()->attributes->remove('margin.costs');
        $this->assertSame(500_000, Margin::floorOf($p->id));   // local cost wins: ceil(450k / 0.9)
    }
}

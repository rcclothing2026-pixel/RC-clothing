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

    private function webhook(string $event, array $data, string $secret = self::SECRET, ?string $id = null, ?string $at = null)
    {
        $raw = json_encode(['event' => $event, 'event_id' => $id ?? (string) \Illuminate\Support\Str::uuid(), 'brand_id' => 2,
            'occurred_at' => $at ?? now()->toAtomString(), 'data' => $data]);

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
        $form['variants'][0]['price'] = 1_100_000;
        $this->actingAs($this->admin())->put(route('admin.products.update', $p), $form)->assertRedirect();

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r->url() === self::API.'variants/11' && $r['price'] === 1_100_000);
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
        $this->assertSame('dead', IntegrationEvent::where('type', StoqsContent::TYPE)->first()->status);   // content rows stop for good
        $this->assertArrayHasKey('catalog:write', StoqsApi::missingScopes());

        app(StockKeepingReporter::class)->flushPending();   // the 5-minute flush skips blocked rows
        Http::assertSentCount(1);
        $this->assertSame(1, \App\Models\StockkeeepingLog::where('ref', 'catalog:write')->count());

        $this->actingAs($this->admin())->get(route('admin.settings.integration'))->assertOk()->assertSee('متوقف')->assertSee('حذف');
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

    public function test_only_live_outbox_rows_hold_a_pull_and_spent_ones_go_dead(): void
    {
        $p = $this->product();
        $import = fn (bool $force = false) => app(\App\Services\StockKeeping\CatalogImporter::class)->importProduct($this->payload(['images' => []]), $force);
        $reset = fn () => $p->forceFill(['name' => 'مانتو'])->saveQuietly();
        $ev = StoqsContent::enqueue($p, ['name', 'price']);

        $import();
        $this->assertSame('مانتو', $p->fresh()->name);   // a pending shop edit holds
        $import(true);
        $this->assertSame('مانتو نو', $p->fresh()->name);   // «دریافت از StoqS» ignores it

        foreach ([['status' => 'failed', 'attempts' => 5], ['status' => 'failed', 'attempts' => 1, 'created_at' => now()->subHours(25)],
            ['status' => 'blocked'], ['status' => 'dead']] as $state) {
            $reset();
            $ev->forceFill($state + ['attempts' => 0, 'created_at' => now()])->save();
            $import();
            $this->assertSame('مانتو نو', $p->fresh()->name, json_encode($state));
        }

        // The 5th failure ends it: «dead», no more retries, listed with «حذف».
        Http::fake(['stoqs.test/*' => Http::response(['ok' => false, 'error' => ['code' => 'server_error', 'message' => 'down']], 500)]);
        $ev->forceFill(['status' => 'failed', 'attempts' => 4, 'created_at' => now(), 'available_at' => now()])->save();
        $this->assertFalse(StoqsContent::send($ev->fresh()));
        $this->assertSame('dead', $ev->fresh()->status);
        app(StockKeepingReporter::class)->flushPending();
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'stoqs.test/api/v1/products')));
        $this->actingAs($this->admin())->get(route('admin.settings.integration'))->assertOk()->assertSee('متوقف')->assertSee('حذف');
    }

    public function test_push_sends_the_list_price_not_the_outlet_price(): void
    {
        $p = $this->product(['price' => 800_000, 'compare_at_price' => 1_000_000]);
        OutletItem::create(['product_id' => $p->id, 'percent' => 20, 'is_active' => true]);
        $p->variants()->where('sku', '1001')->update(['price' => 800_000]);   // a marked-down size
        StoqsContent::remember($p, $this->payload(['base_price' => 900_000]));
        Http::fake(['stoqs.test/api/v1/products/1' => Http::response(['ok' => true, 'product' => $this->payload(['base_price' => 900_000])]),
            'stoqs.test/*' => Http::response(['ok' => true])]);

        StoqsContent::send(StoqsContent::enqueueAll($p->fresh()));

        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), 'products/1') && ($r['base_price'] ?? null) === 1_000_000);
        Http::assertNotSent(fn (Request $r) => str_contains($r->body(), '800000'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), 'variants/11'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'variants/12') && $r['price'] === null);
    }

    public function test_size_prices_set_by_stoqs_follow_stoqs_and_shop_ones_stay(): void
    {
        $p = $this->product();
        $import = fn (array $over = []) => app(\App\Services\StockKeeping\CatalogImporter::class)->importProduct($this->payload($over + ['images' => []]));
        $v = fn (string $sku) => ProductVariant::where('sku', $sku)->first();
        $v('1001')->update(['price' => 950_000]);   // set by the shop admin

        $import();   // StoqS: M (1002) has its own 1.2M
        $this->assertSame(950_000, $v('1001')->price);
        $this->assertSame(1_200_000, $v('1002')->price);
        $this->assertSame(1_200_000, $v('1002')->stoqs_size_price);

        Http::fake(['stoqs.test/api/v1/catalog*' => Http::response(['products' => [$this->payload()], 'has_more' => false])]);
        $this->artisan('stoqs:size-price-diff')->expectsOutputToContain('1 size price(s) differ.')->assertSuccessful();
        $this->assertSame(950_000, $v('1001')->price);   // read-only

        // StoqS drops M's own price and raises the product to 1.5M: M follows the product.
        $follow = ['base_price' => 1_500_000, 'price' => 1_500_000, 'variants' => [
            ['variant_id' => 11, 'barcode' => '1001', 'size' => 'S', 'active' => true, 'price' => 1_500_000, 'price_override' => false, 'sale_price' => null],
            ['variant_id' => 12, 'barcode' => '1002', 'size' => 'M', 'active' => true, 'price' => 1_500_000, 'price_override' => false, 'sale_price' => null],
        ]];
        $import($follow);
        $this->assertSame(1_500_000, (int) $p->fresh()->price);
        $this->assertNull($v('1002')->price);
        $this->assertNull($v('1002')->stoqs_size_price);
        $this->assertSame(950_000, $v('1001')->price);   // the admin's price survives

        // A new StoqS size price lands on a StoqS-owned size, but not while a shop price edit is unsent.
        StoqsContent::enqueue($p, ['price']);
        $import();
        $this->assertNull($v('1002')->price);
        IntegrationEvent::query()->update(['status' => 'sent']);
        $import();
        $this->assertSame(1_200_000, $v('1002')->price);
    }

    public function test_an_overlapping_copy_is_skipped_and_a_failed_event_can_be_retried(): void
    {
        $this->product();
        \Illuminate\Support\Facades\DB::table('stoqs_webhook_events')->insert(['event_id' => 'busy', 'subject' => '', 'created_at' => now()]);   // another copy is running
        $this->webhook('product.content_changed', ['product_id' => 1, 'product' => $this->payload(['images' => []])], id: 'busy')
            ->assertOk()->assertSee('duplicate');
        $this->assertSame('مانتو', Product::first()->name);

        $this->mock(\App\Services\StockKeeping\CatalogImporter::class)->shouldReceive('importProduct')->andThrow(new \RuntimeException('db down'));
        $this->webhook('product.content_changed', ['product_id' => 1, 'product' => $this->payload(['images' => []])], id: 'flaky')->assertOk();
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('stoqs_webhook_events')->where('event_id', 'flaky')->count());   // claim released
        $this->app->forgetInstance(\App\Services\StockKeeping\CatalogImporter::class);
        $this->app->offsetUnset(\App\Services\StockKeeping\CatalogImporter::class);
        $this->webhook('product.content_changed', ['product_id' => 1, 'product' => $this->payload(['images' => []])], id: 'flaky')->assertOk();
        $this->assertSame('مانتو نو', Product::first()->name);   // the retry went through
    }

    public function test_line_bound_rules_are_spread_before_the_coupon(): void
    {
        $x = Product::create(['name' => 'X', 'slug' => 'x', 'price' => 900_000, 'is_active' => true]);
        $y = Product::create(['name' => 'Y', 'slug' => 'y', 'price' => 100_000, 'is_active' => true]);
        $line = fn ($p) => ['variant' => ProductVariant::create(['product_id' => $p->id, 'sku' => 'L'.$p->id, 'size' => 'L', 'stock_qty' => 5, 'is_active' => true])->load('product'),
            'quantity' => 1, 'unit_price' => (int) $p->price, 'line_total' => (int) $p->price];
        $lines = collect([$line($x), $line($y)]);
        $free = DiscountRule::create(['name' => 'Y free', 'priority' => 1, 'conditions' => [['type' => 'cart_has_products', 'product_ids' => [$y->id]]],
            'actions' => [], 'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true]);
        $cart = \Mockery::mock(Cart::class)->makePartial();
        $cart->shouldReceive('lines')->andReturn($lines);
        $cart->shouldReceive('appliedCoupon')->andReturn(new Coupon(['code' => 'TEN']));
        $cart->shouldReceive('discount')->andReturn(100_000);   // 10% of 1M
        $cart->shouldReceive('ruleDiscountBreakdown')->andReturn([['rule_id' => $free->id, 'amount' => 100_000]]);
        $cart->shouldReceive('combinedDiscount')->andReturn(200_000);

        $out = $cart->lineDiscounts();

        $this->assertSame([['type' => 'code', 'code' => 'TEN', 'amount' => 100_000]], $out[$lines[0]['variant']->id]);
        $this->assertSame([['type' => 'rule', 'amount' => 100_000]], $out[$lines[1]['variant']->id]);
    }

    public function test_base_price_is_the_list_price_after_a_sale_or_outlet_ends(): void
    {
        $p = Product::create(['name' => 'O', 'slug' => 'o', 'price' => 1_000_000, 'is_active' => true]);
        $this->assertSame([], \App\Support\Outlet::sync(['o' => 20]));
        $this->assertSame(1_000_000, StoqsContent::basePrice($p->fresh()));   // outlet on: the struck price
        $this->assertSame(800_000, (int) $p->fresh()->price);
        $this->assertSame([], \App\Support\Outlet::sync(['o' => 0]));
        $this->assertSame(1_000_000, StoqsContent::basePrice($p->fresh()));   // outlet off: price restored
        $this->assertNull($p->fresh()->compare_at_price);
        $p->fresh()->update(['price' => 1_200_000, 'compare_at_price' => 1_000_000]);   // a stale struck price below the price
        $this->assertSame(1_200_000, StoqsContent::basePrice($p->fresh()));
    }

    public function test_webhook_replays_and_older_events_are_skipped(): void
    {
        $this->product();
        $send = fn (string $id, string $at, string $name) => $this->webhook('product.content_changed',
            ['product_id' => 1, 'product' => $this->payload(['name' => $name, 'images' => []])], id: $id, at: $at)->assertOk();

        $send('a', '2026-11-10T10:00:00+03:30', 'نام ۱');
        $this->assertSame('نام ۱', Product::first()->name);
        $send('a', '2026-11-10T10:00:00+03:30', 'نام تکراری');   // a retry of the same event
        $send('b', '2026-11-10T09:59:00+03:30', 'نام قدیمی');   // arrived late
        $this->assertSame('نام ۱', Product::first()->name);
        $send('c', '2026-11-10T10:01:00+03:30', 'نام ۲');
        $this->assertSame('نام ۲', Product::first()->name);
        $this->assertSame(2, \Illuminate\Support\Facades\DB::table('stoqs_webhook_events')->where('subject', 'p:1')->count());
    }

    public function test_line_discounts_never_exceed_a_line(): void
    {
        $a = Product::create(['name' => 'A', 'slug' => 'a', 'price' => 100_000, 'is_active' => true]);
        $b = Product::create(['name' => 'B', 'slug' => 'b', 'price' => 100_000, 'is_active' => true]);
        $line = fn ($p) => ['variant' => ProductVariant::create(['product_id' => $p->id, 'sku' => 'X'.$p->id, 'size' => 'L', 'stock_qty' => 5, 'is_active' => true])->load('product'),
            'quantity' => 1, 'unit_price' => 100_000, 'line_total' => 100_000];
        $lines = collect([$line($a), $line($b)]);
        $onB = DiscountRule::create(['name' => 'B only', 'priority' => 1, 'conditions' => [['type' => 'cart_has_products', 'product_ids' => [$b->id]]],
            'actions' => [], 'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true]);
        $all = DiscountRule::create(['name' => 'all', 'priority' => 2, 'conditions' => [], 'actions' => [], 'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true]);
        $cart = \Mockery::mock(Cart::class)->makePartial();
        $cart->shouldReceive('lines')->andReturn($lines);
        $cart->shouldReceive('appliedCoupon')->andReturn(null);
        $cart->shouldReceive('ruleDiscountBreakdown')->andReturn([['rule_id' => $onB->id, 'amount' => 90_000], ['rule_id' => $all->id, 'amount' => 100_000]]);
        $cart->shouldReceive('combinedDiscount')->andReturn(190_000);

        $out = $cart->lineDiscounts();

        // B had 10k of room left after the first rule: the rest of its share moved to A, nothing dropped.
        $this->assertSame([['type' => 'rule', 'amount' => 90_000]], $out[$lines[0]['variant']->id]);
        $this->assertSame([['type' => 'rule', 'amount' => 90_000], ['type' => 'rule', 'amount' => 10_000]], $out[$lines[1]['variant']->id]);
    }

    public function test_unsent_coupons_are_queued_once_and_a_low_price_warns(): void
    {
        Http::fake(['stoqs.test/api/v1/discounts/*' => Http::response(['ok' => false, 'error' => ['code' => 'forbidden_scope', 'message' => 'discounts:write']], 403),
            'stoqs.test/api/v1/sites/*' => Http::response(['ok' => true, 'count' => 0])]);
        $c = Coupon::create(['code' => 'YALDA', 'type' => 'percent', 'value' => 15, 'is_active' => true]);
        StoqsSites::sync();
        StoqsSites::sync();
        $rows = IntegrationEvent::where('type', \App\Services\StockKeeping\StoqsCodes::TYPE)->get();
        $this->assertSame(1, $rows->filter(fn ($e) => (int) $e->payload['coupon_id'] === $c->id)->count());
        $this->assertSame(Coupon::count(), $rows->count());   // one row per coupon (seeded ones too)

        $p = $this->product(['stockkeeping_id' => null]);
        \App\Models\ProductCost::create(['product_id' => $p->id, 'cost' => 950_000]);   // floor ≈ 1,055,556
        $this->actingAs($this->admin())->put(route('admin.products.update', $p), $this->form($p->fresh('variants')))
            ->assertRedirect()->assertSessionHas('success')->assertSessionHas('warning');
        $this->assertSame(1_000_000, (int) $p->fresh()->price);   // saved anyway
    }
}

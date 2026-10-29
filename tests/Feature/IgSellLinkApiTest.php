<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/ig/sell-links and GET /api/ig/sell-links/{code} — purchase links
 * made by the Instagram DM responder. Pinned: the key gate, the response
 * contract, the link filling the cart exactly like an admin-made one, merging,
 * the 422s for anything not buyable, the status as the link is opened, and the
 * admin «لینک‌های من» list with a link that has no creator.
 */
class IgSellLinkApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-ig-catalog-key';

    private Product $hoodie;

    private ProductVariant $m;

    private ProductVariant $xl;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'scout.driver' => 'null', // product saves must not call Meilisearch
            'services.ig_catalog.key' => self::KEY,
            'app.url' => 'https://shop.test',
        ]);
        $category = Category::create(['name' => 'هودی', 'slug' => 'hoodies']);
        $this->hoodie = Product::create([
            'category_id' => $category->id, 'name' => 'هودی ساده', 'slug' => 'plain-hoodie',
            'price' => 2_000_000, 'is_active' => true,
        ]);
        $this->m = $this->hoodie->variants()->create(['sku' => 'ph-m', 'size' => 'M', 'color' => 'مشکی', 'stock_qty' => 9, 'is_active' => true]);
        $this->xl = $this->hoodie->variants()->create(['sku' => 'ph-xl', 'size' => 'XL', 'stock_qty' => 2, 'price' => 2_300_000, 'is_active' => true]);
    }

    private function create(array $body)
    {
        return $this->withHeaders(['X-Catalog-Key' => self::KEY])->postJson('/api/ig/sell-links', $body);
    }

    private function linkStatus(string $code)
    {
        return $this->withHeaders(['X-Catalog-Key' => self::KEY])->getJson('/api/ig/sell-links/'.$code);
    }

    private function variant(array $product, array $variant): ProductVariant
    {
        $p = Product::create(array_merge([
            'category_id' => $this->hoodie->category_id, 'name' => 'کالای دیگر', 'slug' => 'p-'.uniqid(),
            'price' => 1_000_000, 'is_active' => true,
        ], $product));

        return $p->variants()->create(array_merge(['sku' => $p->slug.'-1', 'size' => 'L', 'stock_qty' => 5, 'is_active' => true], $variant));
    }

    public function test_missing_or_wrong_key_is_a_404(): void
    {
        $body = ['items' => [['variant_id' => $this->m->id, 'qty' => 1]]];
        $link = SellLink::create(['code' => 'ABCDE', 'items' => [['variant_id' => $this->m->id, 'qty' => 1]], 'expires_at' => now()->addDay()]);

        $this->postJson('/api/ig/sell-links', $body)->assertNotFound();
        $this->withHeaders(['X-Catalog-Key' => 'wrong'])->postJson('/api/ig/sell-links', $body)->assertNotFound();
        $this->getJson('/api/ig/sell-links/'.$link->code)->assertNotFound();
        $this->withHeaders(['X-Catalog-Key' => 'wrong'])->getJson('/api/ig/sell-links/'.$link->code)->assertNotFound();

        config(['services.ig_catalog.key' => '']);
        $this->create($body)->assertNotFound();

        $this->assertSame(1, SellLink::count());
    }

    public function test_creates_a_link_that_fills_the_cart(): void
    {
        $res = $this->create([
            'items' => [['variant_id' => $this->m->id, 'qty' => 2], ['variant_id' => $this->xl->id, 'qty' => 1]],
            'handle' => 'some.one',
            'hours' => 24,
        ])->assertCreated();

        $code = $res->json('code');
        $link = SellLink::where('code', $code)->firstOrFail();

        $this->assertSame(['code', 'url', 'expires_at', 'units', 'total_toman', 'items'], array_keys($res->json()));
        // The storefront's link (APP_URL), whatever host the call came in on.
        $this->assertSame('https://shop.test/b/'.$code, $res->json('url'));
        $this->assertStringEndsWith('/b/'.$code, $link->url());
        $this->assertSame($link->expires_at->toIso8601String(), $res->json('expires_at'));
        $this->assertSame(3, $res->json('units'));
        $this->assertSame(2 * 2_000_000 + 2_300_000, $res->json('total_toman'));
        $this->assertSame([
            ['variant_id' => $this->m->id, 'product_id' => $this->hoodie->id, 'name' => 'هودی ساده', 'label' => 'مشکی / M',
                'size' => 'M', 'color' => 'مشکی', 'qty' => 2, 'price_toman' => 2_000_000],
            ['variant_id' => $this->xl->id, 'product_id' => $this->hoodie->id, 'name' => 'هودی ساده', 'label' => 'XL',
                'size' => 'XL', 'color' => null, 'qty' => 1, 'price_toman' => 2_300_000],
        ], $res->json('items'));

        // Stored exactly like an admin-made link, minus the admin.
        $this->assertSame([['variant_id' => $this->m->id, 'qty' => 2], ['variant_id' => $this->xl->id, 'qty' => 1]], $link->items);
        $this->assertNull($link->created_by);
        $this->assertNull($link->coupon_code);
        $this->assertSame('@some.one · NJ Focus', $link->customer_handle);
        $this->assertTrue($link->expires_at->between(now()->addHours(23), now()->addHours(25)));
        $this->assertSame('sent', $link->status());

        fwrite(STDERR, "\nIG_SELL_LINK_201 ".json_encode($res->json(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $this->get('/b/'.$code)->assertOk()->assertSee('سبد خرید شما آماده است')->assertSee('هودی ساده');
        $this->assertSame([$this->m->id => 2, $this->xl->id => 1], session('cart'));
        $this->assertSame($link->id, session('sell_link_id'));
    }

    public function test_duplicate_variants_are_merged_and_capped(): void
    {
        $res = $this->create(['items' => [
            ['variant_id' => $this->m->id, 'qty' => 3],
            ['variant_id' => $this->xl->id, 'qty' => 1],
            ['variant_id' => $this->m->id, 'qty' => 1],
        ]])->assertCreated();

        $this->assertSame([[$this->m->id, 4], [$this->xl->id, 1]], array_map(fn ($i) => [$i['variant_id'], $i['qty']], $res->json('items')));
        $this->assertSame(5, $res->json('units'));
        $this->assertSame(4 * 2_000_000 + 2_300_000, $res->json('total_toman'));

        $link = SellLink::where('code', $res->json('code'))->firstOrFail();
        $this->assertSame([['variant_id' => $this->m->id, 'qty' => 4], ['variant_id' => $this->xl->id, 'qty' => 1]], $link->items);
        // No handle → just the source; no hours → 3 days.
        $this->assertSame('NJ Focus', $link->customer_handle);
        $this->assertTrue($link->expires_at->between(now()->addHours(71), now()->addHours(73)));

        $res = $this->create(['items' => [['variant_id' => $this->m->id, 'qty' => 3], ['variant_id' => $this->m->id, 'qty' => 3]], 'handle' => null])
            ->assertCreated();
        $this->assertSame([['variant_id' => $this->m->id, 'qty' => 5]], SellLink::where('code', $res->json('code'))->first()->items);
        $this->assertSame(10_000_000, $res->json('total_toman'));
    }

    public function test_unbuyable_variants_are_refused_by_id(): void
    {
        $soldOut = $this->variant([], ['stock_qty' => 0]);
        $offSize = $this->variant([], ['is_active' => false]);
        $offProduct = $this->variant(['is_active' => false], []);
        $free = $this->variant(['price' => 0], []);
        $external = $this->variant(['external_enabled' => true, 'external_url' => 'https://seller.example/x'], []);
        $toggleOnly = $this->variant(['external_enabled' => true], []); // no URL → sold here

        $refused = function (array $items, int $variantId, string $text) {
            $res = $this->create(['items' => $items])->assertStatus(422);
            $this->assertSame(['message', 'variant_id'], array_keys($res->json()));
            $this->assertSame($variantId, $res->json('variant_id'));
            $this->assertStringContainsString($text, $res->json('message'));
        };

        $refused([['variant_id' => $soldOut->id, 'qty' => 1]], $soldOut->id, 'تمام شده');
        $refused([['variant_id' => $this->m->id, 'qty' => 1], ['variant_id' => $this->xl->id, 'qty' => 3]], $this->xl->id, 'فقط ۲ عدد');
        // Merging can push a line past the stock too.
        $refused([['variant_id' => $this->xl->id, 'qty' => 2], ['variant_id' => $this->xl->id, 'qty' => 1]], $this->xl->id, 'فقط ۲ عدد');
        $refused([['variant_id' => $offSize->id, 'qty' => 1]], $offSize->id, 'فروخته نمی‌شود');
        $refused([['variant_id' => $offProduct->id, 'qty' => 1]], $offProduct->id, 'فروخته نمی‌شود');
        $refused([['variant_id' => $free->id, 'qty' => 1]], $free->id, 'قیمت');
        $refused([['variant_id' => 999999, 'qty' => 1]], 999999, 'پیدا نشد');
        $refused([['variant_id' => $external->id, 'qty' => 1]], $external->id, 'سایت فروشنده');

        $this->assertSame(0, SellLink::count());
        $this->create(['items' => [['variant_id' => $toggleOnly->id, 'qty' => 1]]])->assertCreated();
    }

    public function test_bad_shapes_are_validation_errors(): void
    {
        $item = ['variant_id' => $this->m->id, 'qty' => 1];
        $invalid = fn (array $body, string $field) => $this->create($body)->assertStatus(422)->assertJsonValidationErrors($field);

        $invalid([], 'items');
        $invalid(['items' => []], 'items');
        $invalid(['items' => array_fill(0, 6, $item)], 'items');
        $invalid(['items' => ['a' => $item]], 'items');
        $invalid(['items' => [['variant_id' => $this->m->id, 'qty' => 6]]], 'items.0.qty');
        $invalid(['items' => [['variant_id' => $this->m->id, 'qty' => 0]]], 'items.0.qty');
        $invalid(['items' => [['variant_id' => 'abc', 'qty' => 1]]], 'items.0.variant_id');
        $invalid(['items' => [['variant_id' => $this->m->id]]], 'items.0.qty');
        $invalid(['items' => [$item + ['price' => 1]]], 'items.0');
        $invalid(['items' => [$item], 'hours' => 48], 'hours');
        $invalid(['items' => [$item], 'handle' => str_repeat('a', 61)], 'handle');
        $invalid(['items' => [$item], 'handle' => ['x']], 'handle');
        $invalid(['items' => [$item], 'coupon' => 'OFF50'], 'coupon');

        $this->assertSame(0, SellLink::count());
    }

    public function test_status_goes_from_sent_to_opened_after_the_visit(): void
    {
        $code = $this->create(['items' => [['variant_id' => $this->m->id, 'qty' => 1]], 'hours' => 72])->assertCreated()->json('code');
        $link = SellLink::where('code', $code)->firstOrFail();

        $res = $this->linkStatus($code)->assertOk();
        $this->assertSame(['code', 'url', 'status', 'opened_at', 'open_count', 'order_number', 'expires_at', 'created_at'], array_keys($res->json()));
        $this->assertSame([$code, 'https://shop.test/b/'.$code, 'sent', null, 0, null], [
            $res->json('code'), $res->json('url'), $res->json('status'), $res->json('opened_at'), $res->json('open_count'), $res->json('order_number'),
        ]);
        $this->assertSame($link->expires_at->toIso8601String(), $res->json('expires_at'));
        $this->assertSame($link->created_at->toIso8601String(), $res->json('created_at'));
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));

        $this->get('/b/'.$code)->assertOk();

        $res = $this->linkStatus(strtolower($code))->assertOk();
        $this->assertSame('opened', $res->json('status'));
        $this->assertSame(1, $res->json('open_count'));
        $this->assertNotNull($res->json('opened_at'));

        $this->travel(73)->hours();
        $this->assertSame('expired', $this->linkStatus($code)->json('status'));
    }

    public function test_unknown_code_is_a_404(): void
    {
        $this->linkStatus('ZZZZZ')->assertNotFound();
        $this->linkStatus('ab')->assertNotFound();
    }

    public function test_admin_links_list_copes_with_no_creator(): void
    {
        $code = $this->create(['items' => [['variant_id' => $this->m->id, 'qty' => 2]], 'handle' => 'buyer'])->assertCreated()->json('code');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->getJson(route('admin.sell.links'))->assertOk()
            ->assertJsonPath('links.0.code', $code)
            ->assertJsonPath('links.0.handle', '@buyer · NJ Focus')
            ->assertJsonPath('links.0.units', 2)
            ->assertJsonPath('links.0.status', 'sent')
            ->assertJsonPath('today.created', 1);
        $this->actingAs($admin)->get(route('admin.sell.index'))->assertOk();
    }

    public function test_creating_has_its_own_rate_limit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->create([])->assertStatus(422);
        }
        $this->create(['items' => [['variant_id' => $this->m->id, 'qty' => 1]]])->assertStatus(429);

        // Reads keep their own budget.
        $this->withHeaders(['X-Catalog-Key' => self::KEY])->getJson('/api/ig/catalog')->assertOk();
        $this->linkStatus('ZZZZZ')->assertNotFound();
    }
}

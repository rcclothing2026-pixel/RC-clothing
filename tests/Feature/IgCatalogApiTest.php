<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /api/ig/* — the live price & stock feed the Instagram DM responder quotes
 * from. Pinned: the key gate (404 without it), the numbers equal the product
 * page / cart, hidden products and sizes stay out, no internal field leaks,
 * Persian search, alternatives.
 */
class IgCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-ig-catalog-key';

    private Category $hoodies;

    private Category $tees;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'scout.driver' => 'null', // product saves must not call Meilisearch
            'services.ig_catalog.key' => self::KEY,
            'app.url' => 'https://shop.test',
        ]);
        $this->hoodies = Category::create(['name' => 'هودی', 'slug' => 'hoodies']);
        $this->tees = Category::create(['name' => 'تی‌شرت', 'slug' => 'tees']);
    }

    /** @param  list<array<string, mixed>>  $variants */
    private function product(array $attrs = [], array $variants = [['size' => 'M', 'stock_qty' => 3]]): Product
    {
        $p = Product::create(array_merge([
            'category_id' => $this->hoodies->id,
            'name' => 'هودی ساده',
            'slug' => 'p-'.uniqid(),
            'price' => 2_000_000,
            'is_active' => true,
        ], $attrs));
        foreach ($variants as $i => $v) {
            $p->variants()->create(array_merge(['sku' => $p->slug.'-'.$i, 'is_active' => true], $v));
        }

        return $p->fresh();
    }

    private function api(string $path)
    {
        return $this->withHeaders(['X-Catalog-Key' => self::KEY])->getJson('/api/ig'.$path);
    }

    public function test_missing_or_wrong_key_or_unset_env_is_a_404(): void
    {
        $this->product();

        $this->getJson('/api/ig/catalog')->assertNotFound();
        $this->withHeaders(['X-Catalog-Key' => 'wrong'])->getJson('/api/ig/catalog')->assertNotFound();
        $this->withHeaders(['X-Catalog-Key' => 'wrong'])->getJson('/api/ig/search?q=x')->assertNotFound();
        $this->getJson('/api/ig/products/1')->assertNotFound();

        // Same body as a route that does not exist at all.
        $this->assertSame(
            ['message' => 'The route api/ig/catalog could not be found.'],
            $this->getJson('/api/ig/catalog')->json(),
        );

        $ok = $this->api('/catalog')->assertOk();
        $this->assertStringContainsString('no-store', (string) $ok->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $ok->headers->get('X-LiteSpeed-Cache-Control'));

        config(['services.ig_catalog.key' => '']);
        $this->api('/catalog')->assertNotFound();
        $this->withHeaders(['X-Catalog-Key' => ''])->getJson('/api/ig/catalog')->assertNotFound();
    }

    public function test_sale_price_equals_what_the_product_page_shows(): void
    {
        $p = $this->product(['name' => 'هودی حراج', 'slug' => 'sale-hoodie', 'price' => 1_800_000, 'compare_at_price' => 2_400_000]);

        $row = $this->api('/products/sale-hoodie')->assertOk()->json('data');
        $this->assertSame(1_800_000, $row['price_toman']);
        $this->assertSame(2_400_000, $row['regular_price_toman']);
        $this->assertTrue($row['on_sale']);
        $this->assertSame(25, $row['discount_percent']);

        $this->get('/product/sale-hoodie')->assertOk()
            ->assertSee(Money::toman($row['price_toman']))
            ->assertSee(Money::toman($row['regular_price_toman']));

        // A struck price at or below the price is not a sale on the page either.
        $p->update(['compare_at_price' => 1_800_000]);
        $row = $this->api('/products/'.$p->id)->assertOk()->json('data');
        $this->assertFalse($row['on_sale']);
        $this->assertNull($row['regular_price_toman']);
        $this->assertSame(1_800_000, $row['price_toman']);
    }

    public function test_variant_price_is_what_the_cart_charges(): void
    {
        $p = $this->product(['slug' => 'sizes', 'price' => 2_000_000], [
            ['size' => 'M', 'stock_qty' => 2],                       // follows the product price
            ['size' => 'XL', 'stock_qty' => 1, 'price' => 2_300_000], // own price
            ['size' => 'S', 'stock_qty' => 0],                       // sold out
        ]);

        $row = $this->api('/products/sizes')->assertOk()->json('data');
        $v = collect($row['variants'])->keyBy('size');

        foreach (ProductVariant::where('product_id', $p->id)->get() as $model) {
            $this->assertSame($model->effectivePrice(), $v[$model->size]['price_toman'], "size {$model->size}");
        }
        $this->assertSame(2_000_000, $v['M']['price_toman']);
        $this->assertSame(2_300_000, $v['XL']['price_toman']);
        $this->assertSame([2, true], [$v['M']['stock_qty'], $v['M']['in_stock']]);
        $this->assertSame([0, false], [$v['S']['stock_qty'], $v['S']['in_stock']]);
        $this->assertTrue($row['in_stock']);
        $this->assertSame(2_000_000, $row['price_toman']);
        $this->assertSame('https://shop.test/product/sizes', $row['url']);

        $this->get('/product/sizes')->assertOk()->assertSee(Money::toman($row['price_toman']));
    }

    public function test_inactive_products_and_sizes_are_left_out(): void
    {
        $live = $this->product(['slug' => 'live']);
        $hidden = $this->product(['slug' => 'hidden', 'name' => 'هودی مخفی', 'is_active' => false]);
        $onlyOff = $this->product(['slug' => 'only-off'], [['size' => 'L', 'stock_qty' => 9, 'is_active' => false]]);

        $slugs = array_column($this->api('/catalog')->assertOk()->json('data'), 'slug');
        $this->assertEqualsCanonicalizing(['live', 'only-off'], $slugs);

        $this->api('/products/hidden')->assertNotFound();
        $this->api('/products/'.$hidden->id)->assertNotFound();
        $this->api('/alternatives/'.$hidden->id)->assertNotFound();
        $this->assertSame([], $this->api('/search?q='.urlencode('مخفی'))->json('data'));

        // An inactive size can't be bought, so it neither shows nor counts as stock.
        $row = $this->api('/products/'.$onlyOff->id)->json('data');
        $this->assertSame([], $row['variants']);
        $this->assertFalse($row['in_stock']);
        $this->assertTrue($this->api('/products/'.$live->id)->json('data.in_stock'));
    }

    public function test_only_whitelisted_fields_are_returned(): void
    {
        $p = $this->product(['slug' => 'secret', 'stockkeeping_id' => 'SK-99887766', 'stock_synced_at' => now()],
            [['size' => 'M', 'stock_qty' => 4, 'stockkeeping_variant_id' => 'SKV-55443322', 'weight' => 777]]);
        $p->images()->create(['path' => '/storage/products/a.webp', 'is_primary' => true, 'position' => 0]);

        $row = $this->api('/products/secret')->assertOk()->json('data');
        $this->assertSame([
            'id', 'slug', 'name', 'name_en', 'category', 'url', 'external_url', 'image', 'price_toman', 'regular_price_toman',
            'on_sale', 'discount_percent', 'in_stock', 'variants', 'updated_at', 'stock_synced_at',
        ], array_keys($row));
        $this->assertSame(['id', 'label', 'size', 'color', 'stock_qty', 'in_stock', 'price_toman'], array_keys($row['variants'][0]));
        $this->assertSame('https://shop.test/storage/products/a.webp', $row['image']);
        $this->assertNotNull($row['stock_synced_at']);

        foreach (['/catalog', '/products/secret', '/search?q=secret', '/alternatives/secret'] as $path) {
            $body = $this->api($path)->assertOk()->getContent();
            foreach (['SK-99887766', 'SKV-55443322', 'secret-0', 'stockkeeping', 'sku', 'weight', 'views', 'compare_at_price'] as $needle) {
                $this->assertStringNotContainsString($needle, $body, "{$path} leaks {$needle}");
            }
        }
    }

    public function test_persian_search_folds_arabic_letters_zwnj_and_digits(): void
    {
        // Stored with Arabic ي/ك and a ZWNJ, as old imports often are.
        $this->product(['slug' => 'black-tee', 'name' => 'تي‌شرت مشكي ۲۰۲۵', 'category_id' => $this->tees->id]);
        $this->product(['slug' => 'grey-hoodie', 'name' => 'هودی طوسی']);
        $this->product(['slug' => 'white-tee', 'name' => 'تیشرت سفید', 'category_id' => $this->tees->id], [['size' => 'M', 'stock_qty' => 0]]);

        $find = fn (string $q) => array_column($this->api('/search?q='.urlencode($q))->assertOk()->json('data'), 'slug');

        $this->assertSame(['black-tee'], $find('تیشرت مشکی'));
        $this->assertSame(['black-tee'], $find('تی شرت مشکی'));
        $this->assertSame(['black-tee'], $find('قیمت تی‌شرت مشکی 2025 چنده'));
        // Both tees; the in-stock one first.
        $this->assertSame(['black-tee', 'white-tee'], $find('تیشرت'));
        $this->assertSame(['grey-hoodie'], $find('Grey Hoodie'));
        $this->assertSame([], $find('zzzz'));
        $this->assertSame([], $find(''));

        for ($i = 0; $i < 12; $i++) {
            $this->product(['name' => 'هودی شماره '.$i]);
        }
        $this->assertCount(10, $find('هودی'));
    }

    public function test_english_name_and_buy_from_seller_link(): void
    {
        $this->product(['slug' => 'og', 'name' => 'اورگریپ', 'name_en' => 'Pro Overgrip',
            'external_enabled' => true, 'external_url' => 'https://seller.example/og']);
        $this->product(['slug' => 'toggle-only', 'external_enabled' => true]); // no URL → sold here

        $rows = $this->api('/search?q='.urlencode('overgrip'))->assertOk()->json('data');
        $this->assertSame(['og'], array_column($rows, 'slug'));
        $this->assertSame(['اورگریپ', 'Pro Overgrip'], [$rows[0]['name'], $rows[0]['name_en']]);
        $this->assertSame('https://seller.example/og', $rows[0]['external_url']);
        $this->assertSame(['og'], array_column($this->api('/search?q='.urlencode('اورگریپ'))->json('data'), 'slug'));
        $this->assertNull($this->api('/products/toggle-only')->json('data.external_url'));
    }

    public function test_alternatives_are_in_stock_same_category_close_in_price(): void
    {
        $src = $this->product(['slug' => 'src', 'price' => 2_000_000], [['size' => 'M', 'stock_qty' => 0]]);
        $this->product(['slug' => 'close', 'price' => 2_100_000]);
        $this->product(['slug' => 'near', 'price' => 1_700_000]);
        $this->product(['slug' => 'too-dear', 'price' => 4_000_000]);
        $this->product(['slug' => 'sold-out', 'price' => 2_000_000], [['size' => 'M', 'stock_qty' => 0]]);
        $this->product(['slug' => 'hidden', 'price' => 2_000_000, 'is_active' => false]);
        $this->product(['slug' => 'a-tee', 'price' => 2_000_000, 'category_id' => $this->tees->id]);

        $res = $this->api('/alternatives/'.$src->id)->assertOk();
        $this->assertSame(['close', 'near'], array_column($res->json('data'), 'slug'));
        $this->assertSame('src', $res->json('product.slug'));
        $this->assertFalse($res->json('product.in_stock'));

        $this->assertSame(['close'], array_column($this->api('/alternatives/src?limit=1')->json('data'), 'slug'));
        $this->api('/alternatives/999999')->assertNotFound();
    }
}

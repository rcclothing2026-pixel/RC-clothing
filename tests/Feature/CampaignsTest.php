<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CampaignsTest extends TestCase
{
    use RefreshDatabase;

    private array $cats = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection']);
    }

    private function item(string $slug, string $cat, int $price, int $stock = 50, ?string $color = null): ProductVariant
    {
        $this->cats[$cat] ??= Category::create(['name' => $cat, 'slug' => $cat, 'is_active' => true]);
        $p = Product::firstOrCreate(['slug' => $slug], ['name' => $slug, 'price' => $price, 'category_id' => $this->cats[$cat]->id, 'is_active' => true]);
        $p->categories()->syncWithoutDetaching([$this->cats[$cat]->id]);

        return ProductVariant::create(['product_id' => $p->id, 'size' => 'FREE SIZE', 'color' => $color, 'price' => $price, 'stock_qty' => $stock, 'is_active' => true]);
    }

    public function test_bundles_are_priced_off_their_parts_and_not_discounted_again(): void
    {
        $this->item('aadd-aorgryp-rakt-klab-super-tacky-1480', 'loazm-ganby', 680_000, 900, 'مشکی');
        $this->item('aadd-aorgryp-rakt-klab-super-dry-1482', 'loazm-ganby', 680_000, 900, 'آبی');
        $this->item('klah-rakt-klab-9', 'klah', 2_100_000, 20, 'ذغالی');
        $this->item('gorab-rakt-klab-122', 'mtfrkh', 680_000, 40, 'سفید');
        $this->item('gorab-rakt-klab-122', 'mtfrkh', 680_000, 30, 'مشکی');
        $this->item('tot-bg-rakt-klab-121', 'mtfrkh', 3_600_000);
        $this->item('holh-mykro-faybr-rakt-klab-764', 'holh', 5_300_000);

        $this->artisan('campaigns:sync')->assertSuccessful();
        $this->artisan('campaigns:sync')->assertSuccessful();

        $kit = Product::where('slug', 'kit-padel-starter')->with('bundleItems.variant')->firstOrFail();
        $this->assertTrue($kit->is_bundle);
        $this->assertSame(4_820_000, (int) $kit->compare_at_price);
        $this->assertSame(4_280_000, (int) $kit->price);                       // 11% off, rounded down to 10k
        $this->assertTrue($kit->bundleItems->pluck('variant.color')->contains('مشکی'));     // the socks colour asked for
        $this->assertSame(1, Product::where('slug', 'kit-padel-starter')->count());
        $this->assertSame(6, Product::where('is_bundle', true)->count());
        $this->assertSame(20, $kit->bundleAvailableQty());                     // limited by the 20 caps

        $this->travelTo(Carbon::parse('2026-11-25 12:00', 'Asia/Tehran'));     // Black Friday: spend ≥ 9M → 8%
        $cart = app(Cart::class);
        $cart->add($kit->variants()->first()->id, 3);                          // 12.84M of bundles only
        $this->assertSame(0, $cart->combinedDiscount());

        $this->get('/shop?shelf=kits')->assertOk()->assertSee('کیت شروع پدل');
    }

    public function test_team_order_discounts_only_tees(): void
    {
        $tee = $this->item('tyshrt-imperial-1479', 'tyshrt', 5_800_000, 100);
        $cap = $this->item('klah-rakt-klab-9', 'klah', 2_100_000);
        $this->artisan('campaigns:sync');
        $this->travelTo(Carbon::parse('2026-12-10 12:00', 'Asia/Tehran'));

        $cart = app(Cart::class);
        $cart->add($tee->id, 10);
        $cart->add($cap->id, 1);
        $this->assertSame(8_700_000, $cart->combinedDiscount());               // 15% of 58M of tees, cap untouched
        $this->assertNotNull(DiscountRule::where('description', 'campaign:team-20')->first());
    }
}

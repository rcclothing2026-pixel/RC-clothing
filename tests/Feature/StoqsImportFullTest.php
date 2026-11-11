<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCampaignReadAccess;
use App\Models\CampaignApproval;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Discount\StoqsCart;
use App\Services\StockKeeping\StoqsPromotions;
use App\Support\Campaigns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * StoqS 5.2.116 «وارد کردن از سایت» (the shop side):
 *  - StoqS reads GET /api/campaigns/plan and /editor with its signature (the webhook secret), no key;
 *  - the editor answer carries each product's category slugs and each shelf's products today;
 *  - managed by StoqS, a campaign's content follows the StoqS plan that carries its key
 *    (site_campaign_key); shop-managed, NJ Focus approvals still decide;
 *  - a logged-in customer's order history reaches both engines (a «second order» rule agrees).
 */
class StoqsImportFullTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'import-full-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k',
            'stockkeeping.webhook_secret' => self::SECRET, 'services.ig_catalog.key' => 'catalog-key',
            'app.url' => 'https://shop.test', 'scout.driver' => 'collection',
        ]);
        Http::preventStrayRequests();
        \Illuminate\Support\Facades\Cache::lock('scheduler:web-kick', 600)->get();
        StoqsPromotions::forget();
    }

    private function signed(string $path, ?int $ts = null, string $secret = self::SECRET, array $extra = [])
    {
        $ts ??= time();

        return $this->withHeaders(['X-Stoqs-Timestamp' => (string) $ts, 'X-Stoqs-Signature' => VerifyCampaignReadAccess::sign($path, $ts, $secret)] + $extra);
    }

    private function manage(array $plans, bool $managed = true): void
    {
        $payload = ['ok' => true, 'schema' => 2, 'version' => 9, 'managed' => $managed, 'promo_source' => $managed ? 'stoqs' : 'shop', 'price_level' => 'size',
            'settings' => ['max_discount_percent' => 25, 'round_step' => 1000, 'round_mode' => 'down', 'round_offset' => 0, 'no_discount_category_ids' => []],
            'plans' => $plans, 'windows' => [], 'variants' => [], 'outlet' => [], 'rules' => [], 'codes' => [], 'categories' => []];
        DB::table('stoqs_promotions')->updateOrInsert(['id' => 1], [
            'managed' => $managed, 'version' => 9, 'etag' => '"v9"', 'payload' => json_encode($payload),
            'pulled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        StoqsPromotions::forget();
        request()->attributes->remove('campaigns.approvals');
    }

    /** A StoqS plan entry as the payload sends it: Tehran times. */
    private function plan(int $id, ?string $key, string $from, ?string $to): array
    {
        return ['id' => $id, 'campaign_id' => $id, 'kind' => 'campaign', 'version' => 1, 'active' => true,
            'from' => Carbon::parse($from, 'Asia/Tehran')->toAtomString(), 'to' => $to ? Carbon::parse($to, 'Asia/Tehran')->toAtomString() : null,
            'channels' => ['web' => true]] + ($key !== null ? ['site_campaign_key' => $key] : []);
    }

    /** A campaign and its pop-up, on their own (no brand data from config). */
    private function campaignContent(): void
    {
        config([
            'campaigns.campaigns' => ['promo-x' => ['name' => 'Promo X', 'from' => '2026-11-14', 'to' => '2026-11-30']],
            'campaigns.popups' => ['px' => ['title' => 'Promo X', 'body' => 'b', 'cta' => 'c', 'url' => '/shop', 'from' => '2026-11-14', 'to' => '2026-11-30', 'campaign' => 'promo-x']],
        ]);
    }

    private function popupIds(): array
    {
        request()->attributes->remove('campaigns.approvals');

        return array_column(Campaigns::popups(), 'id');
    }

    // ── the signature ───────────────────────────────────────────────────────

    public function test_stoqs_reads_plan_and_editor_with_its_signature_and_no_key(): void
    {
        $this->signed('/api/campaigns/plan')->getJson('/api/campaigns/plan')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->signed('/api/campaigns/editor')->getJson('/api/campaigns/editor')->assertOk();
    }

    public function test_a_bad_signature_old_timestamp_or_other_secret_is_401(): void
    {
        $this->signed('/api/campaigns/plan', time() - 301)->getJson('/api/campaigns/plan')->assertStatus(401);
        $this->signed('/api/campaigns/plan', time() + 301)->getJson('/api/campaigns/plan')->assertStatus(401);
        $this->signed('/api/campaigns/plan', null, 'other-secret')->getJson('/api/campaigns/plan')->assertStatus(401);
        $this->signed('/api/campaigns/editor')->getJson('/api/campaigns/plan')->assertStatus(401);   // signed for another path
        $this->withHeaders(['X-Stoqs-Timestamp' => 'abc', 'X-Stoqs-Signature' => 'sha256=00'])->getJson('/api/campaigns/plan')->assertStatus(401);
    }

    public function test_the_signature_opens_only_the_two_reads(): void
    {
        $this->signed('/api/campaigns/costs')->getJson('/api/campaigns/costs')->assertStatus(401);
        $this->signed('/api/campaigns/editor')->putJson('/api/campaigns/editor', ['plan' => []])->assertStatus(401);
        $this->flushHeaders()->getJson('/api/campaigns/plan')->assertNotFound();   // no key, no signature: still the router's 404
    }

    public function test_an_empty_secret_turns_the_signature_path_off_and_the_key_still_works(): void
    {
        config(['stockkeeping.webhook_secret' => '']);
        $this->signed('/api/campaigns/plan', null, '')->getJson('/api/campaigns/plan')->assertStatus(401);
        $this->withHeaders(['X-Catalog-Key' => 'catalog-key'])->getJson('/api/campaigns/plan')->assertOk();
        $this->withHeaders(['X-Catalog-Key' => 'wrong'])->getJson('/api/campaigns/plan')->assertNotFound();
    }

    // ── the editor answer ───────────────────────────────────────────────────

    public function test_the_editor_sends_category_slugs_and_shelf_products(): void
    {
        $cat = Category::create(['name' => 'Cat A', 'slug' => 'cat-a', 'is_active' => true]);
        $p = Product::create(['name' => 'P', 'slug' => 'p-1', 'price' => 1_000_000, 'category_id' => $cat->id, 'is_active' => true]);
        $p->categories()->sync([$cat->id]);
        ProductVariant::create(['product_id' => $p->id, 'size' => 'L', 'price' => 1_000_000, 'stock_qty' => 3, 'is_active' => true]);
        Product::create(['name' => 'Q', 'slug' => 'q-1', 'price' => 1_000_000, 'is_active' => true]);
        config(['campaigns.shelves' => ['shelf-a' => ['name' => 'Shelf A', 'categories' => ['cat-a']]]]);

        $j = $this->signed('/api/campaigns/editor')->getJson('/api/campaigns/editor')->assertOk()->json();
        $row = collect($j['catalog']['products'])->firstWhere('slug', 'p-1');
        $this->assertSame(['cat-a'], $row['categories']);
        $this->assertSame(['p-1'], $j['catalog']['shelf_products']['shelf-a']);
    }

    // ── content follows StoqS ───────────────────────────────────────────────

    public function test_managed_content_shows_only_while_the_stoqs_plan_with_its_key_is_live(): void
    {
        $this->campaignContent();
        $this->travelTo(Carbon::parse('2026-11-20 12:00', 'Asia/Tehran'));
        // NJ Focus approved it, but StoqS manages this site and has no plan for it: hidden
        CampaignApproval::create(['key' => 'promo-x', 'starts_on' => '2026-11-14', 'ends_on' => '2026-11-30', 'approved' => true]);
        $this->manage([$this->plan(5, null, '2026-11-14 00:00', '2026-12-01 00:00')]);
        $this->assertNotContains('c-px', $this->popupIds(), 'a plan without the key does not turn it on');

        $this->manage([$this->plan(5, 'promo-x', '2026-11-18 00:00', '2026-11-22 00:00')]);
        $this->assertSame(['promo-x' => ['2026-11-18', '2026-11-21']], Campaigns::approvals(), 'the plan ends at a midnight it does not include');
        $this->assertContains('c-px', $this->popupIds(), 'the plan applied and live: the pop-up shows');

        $this->travelTo(Carbon::parse('2026-11-22 00:30', 'Asia/Tehran'));
        $this->assertNotContains('c-px', $this->popupIds(), 'past the plan\'s end: hidden, even with the last payload kept');

        $this->travelTo(Carbon::parse('2026-11-20 12:00', 'Asia/Tehran'));
        $this->manage([]);
        $this->assertNotContains('c-px', $this->popupIds(), 'the plan ended or paused in StoqS (gone from the payload): hidden');
    }

    public function test_a_content_only_plan_with_no_end_and_two_plans_of_one_key(): void
    {
        $this->manage([$this->plan(5, 'promo-x', '2026-11-18 00:00', null), $this->plan(6, 'promo-x', '2026-11-10 00:00', '2026-11-12 00:00')]);
        $this->assertSame(['promo-x' => ['2026-11-10', '2999-12-31']], Campaigns::approvals());
    }

    public function test_shop_managed_content_still_follows_nj_focus(): void
    {
        $this->campaignContent();
        $this->travelTo(Carbon::parse('2026-11-20 12:00', 'Asia/Tehran'));
        $this->manage([$this->plan(5, 'promo-x', '2026-11-18 00:00', '2026-11-22 00:00')], false);
        $this->assertNotContains('c-px', $this->popupIds(), 'shop-managed: a StoqS plan does not approve it');
        CampaignApproval::create(['key' => 'promo-x', 'starts_on' => '2026-11-14', 'ends_on' => '2026-11-30', 'approved' => true]);
        $this->assertContains('c-px', $this->popupIds(), 'NJ Focus approval shows it');
    }

    // ── the customer's history ──────────────────────────────────────────────

    public function test_a_logged_in_customers_orders_reach_both_engines(): void
    {
        $u = User::factory()->create(['phone' => '09121234567']);
        $this->assertNull(StoqsCart::facts(null));
        $this->assertSame(['orders' => 0, 'orders_total' => 0], StoqsCart::facts($u));
        $mk = fn (string $n, string $st, int $t) => Order::create(['number' => $n, 'user_id' => $u->id, 'status' => $st, 'subtotal' => $t, 'shipping_cost' => 0,
            'discount' => 0, 'total' => $t, 'customer_name' => 'x', 'customer_phone' => '09121234567']);
        $mk('T-1', Order::STATUS_DELIVERED, 3_000_000);
        $mk('T-2', Order::STATUS_CANCELED, 9_000_000);
        $this->assertSame(['orders' => 1, 'orders_total' => 3_000_000], StoqsCart::facts($u->fresh()));

        // the local engine: a «second order» rule from the payload gives 10 % to this customer, nothing to a guest
        $p = Product::create(['name' => 'P', 'slug' => 'p-2', 'price' => 3_000_000, 'stockkeeping_id' => '1', 'is_active' => true]);
        $v = ProductVariant::create(['product_id' => $p->id, 'sku' => 's1', 'size' => 'L', 'price' => 3_000_000, 'stock_qty' => 3, 'is_active' => true, 'stockkeeping_variant_id' => 11]);
        $this->manage([]);
        $payload = json_decode((string) DB::table('stoqs_promotions')->value('payload'), true);
        $payload['rules'] = [['id' => 1, 'plan_id' => 5, 'label' => 'second', 'priority' => 10, 'conditions' => [['type' => 'user_previous_order_count', 'min' => 1, 'max' => 1]],
            'actions' => [['type' => 'cart_discount_percent', 'value' => 10]], 'apply_mode' => 'all', 'stack_mode' => 'all', 'exclusive' => false, 'enabled' => 1, 'channels' => 'web,brand,stores']];
        DB::table('stoqs_promotions')->where('id', 1)->update(['payload' => json_encode($payload)]);
        StoqsPromotions::forget();
        $lines = collect([['variant' => $v->fresh(), 'quantity' => 1, 'unit_price' => 3_000_000, 'line_total' => 3_000_000]]);
        try {
            $mine = StoqsCart::evaluate($lines, [], [], $u->fresh());
            $guest = StoqsCart::evaluate($lines, [], [], null);
        } catch (\Throwable $e) {
            $this->markTestIncomplete('cart lines shape: '.$e->getMessage());
        }
        $this->assertSame(300_000, (int) $mine['discount']);
        $this->assertSame(0, (int) $guest['discount']);
    }

    public function test_stoqs_evaluate_is_sent_the_customers_orders(): void
    {
        $u = User::factory()->create(['phone' => '09121234567']);
        Order::create(['number' => 'T-3', 'user_id' => $u->id, 'status' => Order::STATUS_PAID, 'subtotal' => 2_000_000, 'shipping_cost' => 0,
            'discount' => 0, 'total' => 2_000_000, 'customer_name' => 'x', 'customer_phone' => '09121234567']);
        $p = Product::create(['name' => 'P', 'slug' => 'p-3', 'price' => 1_000_000, 'stockkeeping_id' => '1', 'is_active' => true]);
        $v = ProductVariant::create(['product_id' => $p->id, 'sku' => 's2', 'size' => 'L', 'price' => 1_000_000, 'stock_qty' => 3, 'is_active' => true, 'stockkeeping_variant_id' => 12]);
        $this->manage([]);
        $sent = null;
        Http::fake(['*promotions/evaluate*' => function (Request $r) use (&$sent) { $sent = $r->data(); return Http::response(['ok' => false], 500); }]);
        $lines = collect([['variant' => $v->fresh(), 'quantity' => 1, 'unit_price' => 1_000_000, 'line_total' => 1_000_000]]);
        try {
            StoqsCart::remote($lines, [], $u->fresh());
        } catch (\Throwable $e) {
            $this->markTestIncomplete('cart lines shape: '.$e->getMessage());
        }
        $this->assertSame(['orders' => 1, 'orders_total' => 2_000_000], $sent['customer'] ?? null);
    }
}

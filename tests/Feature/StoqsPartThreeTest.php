<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\Cart;
use App\Services\Discount\StoqsCart;
use App\Services\StockKeeping\StoqsPromoSource;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Part 3 of the campaign planner, shop side (all in managed mode): engine schema 3 line flags
 * (outlet stacking modes, cap-exempt), the formula for exempt windows, the public label and the
 * outlet note on the item, and the two-way site manager switch (admin, webhook, minute pull).
 */
class StoqsPartThreeTest extends TestCase
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
        \Illuminate\Support\Facades\Cache::lock('scheduler:web-kick', 600)->get();
        StoqsPromotions::forget();
        $this->product = Product::create([
            'name' => 'مانتو', 'slug' => 'manto-1', 'price' => 1_000_000, 'stockkeeping_id' => '1', 'is_active' => true,
        ]);
        ProductVariant::create(['product_id' => $this->product->id, 'sku' => '1001', 'size' => 'S', 'stock_qty' => 5, 'is_active' => true, 'stockkeeping_variant_id' => 11]);
        ProductVariant::create(['product_id' => $this->product->id, 'sku' => '1002', 'size' => 'M', 'stock_qty' => 5, 'is_active' => true, 'stockkeeping_variant_id' => 12]);
    }

    private function win(string $id, string $source, int $pct, array $extra = []): array
    {
        return array_replace([
            'id' => $id, 'source' => $source, 'campaign_id' => 3, 'plan_id' => 7, 'version' => 4, 'pct' => $pct,
            'from' => null, 'to' => null, 'from_utc' => null, 'to_utc' => null,
        ], $extra);
    }

    /** A schema-3 payload: S has a 50% window «c3» (500,000), M a 20% one «c4» (800,000); list 1,000,000, floor 500,000, cap 25%. */
    private function payload(array $over = []): array
    {
        return array_replace([
            'ok' => true, 'schema' => 3, 'version' => 4, 'managed' => true, 'promo_source' => 'stoqs', 'price_level' => 'size',
            'promo_source_seq' => 0,
            'settings' => ['max_discount_percent' => 25, 'round_step' => 1000, 'round_mode' => 'down', 'round_offset' => 0, 'no_discount_category_ids' => []],
            'plans' => [['id' => 7, 'campaign_id' => 3, 'kind' => 'campaign', 'version' => 4, 'active' => true]],
            'windows' => [$this->win('c3', 'campaign', 50), $this->win('c4', 'campaign', 20)],
            'variants' => [
                ['variant_ids' => [11], 'product_id' => 1, 'list_price' => 1_000_000, 'floor_price' => 500_000, 'prices' => [['c3', 500_000]], 'price_owner' => 'stoqs'],
                ['variant_ids' => [12], 'product_id' => 1, 'list_price' => 1_000_000, 'floor_price' => 500_000, 'prices' => [['c4', 800_000]], 'price_owner' => 'stoqs'],
            ],
            'outlet' => [], 'rules' => [], 'codes' => [], 'categories' => [],
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

    private function cart(): Cart
    {
        app()->forgetInstance(Cart::class);
        StoqsPromotions::forget();

        return app(Cart::class);
    }

    private function webhook(string $event, array $data)
    {
        $raw = json_encode(['event' => $event, 'brand_id' => 2, 'event_id' => 'e-'.uniqid(), 'occurred_at' => now()->toAtomString(), 'data' => $data]);

        return $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
        ], $raw);
    }

    private function addToCart(string ...$sizes): void
    {
        foreach ($sizes as $s) {
            $this->post(route('cart.add'), ['variant_id' => $this->variant($s)->id, 'quantity' => 1]);
        }
    }

    // ── cap-exempt ─────────────────────────────────────────────────────────

    public function test_formula_skips_the_cap_and_the_floor_for_an_exempt_window(): void
    {
        $s = ['max_discount_percent' => 25, 'round_step' => 1000, 'round_mode' => 'down', 'round_offset' => 0];
        $this->assertSame(750_000, StoqsPromotions::formulaPrice(1_000_000, 50, 600_000, $s), 'the cap and the floor hold');
        $this->assertSame(500_000, StoqsPromotions::formulaPrice(1_000_000, 50, 600_000, $s, true), 'exempt: the percent as typed');
        $this->assertNull(StoqsPromotions::formulaPrice(1_000_000, 0, null, $s, true));
    }

    public function test_a_shop_priced_size_in_an_exempt_window_takes_its_own_percent(): void
    {
        $m = $this->variant('M');
        $m->price = 900_000;
        $m->save();
        $row = ['variant_ids' => [12], 'product_id' => 1, 'list_price' => null, 'floor_price' => 500_000,
            'prices' => [['c3', null, 50]], 'price_owner' => 'shop', 'reported' => false];
        $this->manage($this->payload(['variants' => [$row]]));
        StoqsPromotions::apply();
        $this->assertSame(675_000, $this->variant('M')->effectivePrice(), 'without the flag: the 25% cap');

        $this->manage($this->payload(['variants' => [$row + ['cap_exempt' => ['c3']]]]));
        StoqsPromotions::apply();
        $this->assertSame(450_000, $this->variant('M')->effectivePrice(), 'exempt: 50% of 900,000, below the cap and the floor');
    }

    public function test_an_exempt_window_sells_at_its_percent_and_its_cart_line_is_uncapped(): void
    {
        $rule = [['id' => 31, 'plan_id' => 7, 'label' => 'چهل درصد', 'priority' => 1, 'conditions' => [],
            'actions' => [['type' => 'cart_discount_percent', 'value' => 40]], 'apply_mode' => 'all', 'stack_mode' => 'all',
            'exclusive' => false, 'enabled' => 1, 'channels' => 'web,brand,stores']];

        // Without the flag: the 50% window is already past the 25% cap, so the rule gives nothing.
        $this->manage($this->payload(['rules' => $rule]));
        StoqsPromotions::apply();
        $this->addToCart('S');
        $cart = $this->cart();
        $this->assertSame(500_000, $cart->subtotal());
        $this->assertSame(0, $cart->combinedDiscount());

        // Exempt: the line's room is its whole total; 40% of 500,000 comes off.
        $row = $this->payload()['variants'][0] + ['cap_exempt' => ['c3']];
        $this->manage($this->payload(['rules' => $rule, 'variants' => [$row, $this->payload()['variants'][1]]]));
        $cart = $this->cart();
        [$eng] = StoqsCart::lines($cart->lines());
        $this->assertTrue($eng[0]['cap_exempt']);
        $this->assertSame(200_000, $cart->combinedDiscount());
        $this->assertSame(500_000, $cart->discountCap(), 'its whole total is added to the cap');
    }

    // ── the outlet stacking modes on the cart line ─────────────────────────

    private function outletPayload(string $mode): array
    {
        $p = $this->payload(['outlet' => ['o1'], 'windows' => [$this->win('o1', 'outlet', 10, ['campaign_id' => null, 'plan_id' => 8]), $this->win('c4', 'campaign', 20)]]);
        $p['variants'][0] = ['variant_ids' => [11], 'product_id' => 1, 'list_price' => 1_000_000, 'floor_price' => 500_000,
            'prices' => [['o1', 900_000]], 'price_owner' => 'stoqs', 'outlet_stack' => $mode, 'outlet_note' => 'یادداشت '.$mode];
        $p['variants'][1]['prices'] = [];   // M sells at its list price

        return $p;
    }

    public static function modes(): array
    {
        return [
            'outlet_only' => ['outlet_only', false, false],
            'count_in_tiers' => ['count_in_tiers', false, true],
            'lower_wins' => ['lower_wins', true, true],
            'stack' => ['stack', true, true],
        ];
    }

    #[DataProvider('modes')]
    public function test_a_line_in_the_outlet_takes_its_modes_flags(string $mode, bool $discountable, bool $counts): void
    {
        $this->manage($this->outletPayload($mode));
        $this->addToCart('S', 'M');
        [$eng] = StoqsCart::lines($this->cart()->lines());

        $this->assertSame($discountable, $eng[0]['discountable'], "$mode: discountable");
        $this->assertSame($counts, $eng[0]['count_in_tiers'] ?? true, "$mode: count_in_tiers");
        $this->assertTrue($eng[1]['discountable'], 'a line outside the outlet is untouched');
        $this->assertArrayNotHasKey('count_in_tiers', $eng[1]);
        $this->assertArrayNotHasKey('cap_exempt', $eng[0]);
    }

    public function test_an_outlet_row_whose_window_has_ended_is_not_in_the_outlet(): void
    {
        $p = $this->outletPayload('outlet_only');
        $p['windows'][0]['to_utc'] = now()->subHour()->utc()->toAtomString();
        $this->manage($p);
        $this->addToCart('S');
        [$eng] = StoqsCart::lines($this->cart()->lines());
        $this->assertTrue($eng[0]['discountable']);
    }

    public function test_a_no_discount_category_stays_non_discountable_in_any_mode(): void
    {
        $p = $this->outletPayload('lower_wins');
        $p['categories'] = ['9' => [1]];
        $p['settings']['no_discount_category_ids'] = [9];
        $this->manage($p);
        $this->addToCart('S');
        [$eng] = StoqsCart::lines($this->cart()->lines());
        $this->assertFalse($eng[0]['discountable']);
    }

    public function test_an_outlet_only_line_is_not_counted_by_a_piece_count_rule(): void
    {
        $rule = [['id' => 31, 'plan_id' => 7, 'label' => 'دو قطعه', 'priority' => 1,
            'conditions' => [['type' => 'cart_item_count', 'min' => 2]],
            'actions' => [['type' => 'cart_discount_percent', 'value' => 10]], 'apply_mode' => 'all', 'stack_mode' => 'all',
            'exclusive' => false, 'enabled' => 1, 'channels' => 'web,brand,stores']];
        $this->addToCart('S', 'M');

        $p = $this->outletPayload('lower_wins');
        $p['rules'] = $rule;
        $this->manage($p);
        $this->assertGreaterThan(0, $this->cart()->combinedDiscount(), 'two pieces count');

        $p = $this->outletPayload('outlet_only');
        $p['rules'] = $rule;
        $this->manage($p);
        $this->assertSame(0, $this->cart()->combinedDiscount(), 'the outlet-only piece is not counted: one piece');
    }

    // ── label and note on the item ─────────────────────────────────────────

    public function test_the_label_and_the_outlet_note_show_on_the_product_page_and_the_cart(): void
    {
        $this->seed();
        $p = $this->outletPayload('stack');
        $p['windows'][0]['label'] = 'جشنوارهٔ زمستانی';
        $this->manage($p);
        StoqsPromotions::apply();

        $this->get(route('product.show', $this->product))->assertOk()
            ->assertSee('جشنوارهٔ زمستانی')->assertSee('یادداشت stack');
        $this->addToCart('S');
        $this->get(route('cart.index'))->assertOk()->assertSee('جشنوارهٔ زمستانی')->assertSee('یادداشت stack');
    }

    public function test_an_empty_label_and_a_row_outside_the_outlet_show_nothing(): void
    {
        $this->seed();
        $this->manage($this->payload());   // windows without a label, no outlet note
        StoqsPromotions::apply();
        $this->assertSame(['label' => '', 'note' => ''], StoqsPromotions::promoText($this->product->fresh('variants')));
        $this->get(route('product.show', $this->product))->assertOk()->assertDontSee('text-[11px] leading-5');
    }

    public function test_nothing_shows_when_the_shop_runs_its_own_promotions(): void
    {
        $p = $this->outletPayload('stack');
        $p['managed'] = false;
        $p['windows'][0]['label'] = 'جشنوارهٔ زمستانی';
        $this->manage($p);
        $this->assertSame(['label' => '', 'note' => ''], StoqsPromotions::promoText($this->product->fresh('variants')));
    }

    // ── the switch ─────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'name' => 'مدیر فروشگاه']);
    }

    private function guard(bool $stopAll): array
    {
        return ['to' => 'stoqs', 'shop_stops' => ['count' => 2, 'names' => ['قانون زمستان', 'WELCOME']],
            'stoqs_starts' => ['count' => $stopAll ? 0 : 1, 'plans' => $stopAll ? [] : [['id' => 7, 'name' => 'کمپین پاییز', 'from' => null, 'to' => null]]],
            'stoqs_stops' => ['count' => 0, 'plans' => []], 'stop_all' => $stopAll, 'warning' => $stopAll ? 'همهٔ تخفیف‌های فعلی سایت متوقف می‌شود' : null];
    }

    /** StoqS for the admin flow: dry run → guard, confirm → ok seq 5; GET /promotions → managed, seq 5. */
    private function fakeSwitch(bool $stopAll = false, int $putStatus = 200): void
    {
        $managedPayload = $this->payload(['managed' => true, 'promo_source_seq' => 5, 'version' => 9]);
        Http::fake([
            'stoqs.test/api/v1/promotions/source' => function (Request $r) use ($stopAll, $putStatus) {
                if ($putStatus === 409) {
                    return Http::response(['ok' => false, 'error' => 'stale', 'source' => 'stoqs', 'seq' => 5, 'changed_at' => now()->toAtomString(), 'by' => 'علی'], 409);
                }
                if (! empty($r['dry_run'])) {
                    return Http::response(['ok' => true, 'dry_run' => true, 'source' => 'shop', 'seq' => 4, 'to' => 'stoqs', 'guard' => $this->guard($stopAll)]);
                }

                return Http::response(['ok' => true, 'source' => 'stoqs', 'seq' => 5, 'changed_at' => now()->toAtomString(), 'by' => 'مدیر فروشگاه', 'origin' => 'shop', 'guard' => $this->guard($stopAll)]);
            },
            'stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true]),
            'stoqs.test/api/v1/promotions*' => Http::response($managedPayload, 200, ['ETag' => '"v9"']),
        ]);
    }

    private function putsWithConfirm(): int
    {
        return Http::recorded(fn (Request $r) => $r->method() === 'PUT' && ! empty($r['confirm']))->count();
    }

    public function test_the_admin_page_shows_the_switch_state_and_history(): void
    {
        $this->manage($this->payload(['managed' => false]), ['promo_source_seq' => 4, 'promo_source_by' => 'علی', 'promo_source_ack_pending' => true]);
        $this->actingAs($this->admin())->get(route('admin.settings.integration'))->assertOk()
            ->assertSee('مدیریت با فروشگاه')->assertSee('مدیریت با StoqS')->assertSee('علی')
            ->assertSee('در انتظار تأیید StoqS')->assertSee('تاریخچه');
        $this->actingAs($this->admin())->get(route('admin.settings.promo-source.history'))->assertOk();
    }

    public function test_from_the_shop_admin_dry_run_then_guard_page_then_confirm_flips_after_stoqs_says_ok(): void
    {
        $this->manage($this->payload(['managed' => false]), ['promo_source_seq' => 4]);
        $this->fakeSwitch(stopAll: true);
        $admin = $this->admin();

        // Click: a dry run only; the guard page lists what stops and the strong warning.
        $this->actingAs($admin)->post(route('admin.settings.promo-source.prepare'), ['to' => 'stoqs'])->assertOk()
            ->assertSee('قانون زمستان')->assertSee('WELCOME')
            ->assertSee('همهٔ تخفیف‌های فعلی سایت متوقف می‌شود')->assertSee('confirm_stop_all');
        $this->assertFalse(StoqsPromotions::managed());
        $this->assertSame(0, $this->putsWithConfirm());
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r['dry_run'] === true && $r['seq'] === 4 && $r['source'] === 'stoqs'
            && $r['by'] === 'مدیر فروشگاه' && isset($r['own_discounts']['rules']));

        // Refused without the ticks, and nothing is sent to StoqS.
        $this->actingAs($admin)->post(route('admin.settings.promo-source.confirm'), ['to' => 'stoqs'])->assertOk()->assertSee('تأیید را تیک بزنید');
        $this->actingAs($admin)->post(route('admin.settings.promo-source.confirm'), ['to' => 'stoqs', 'confirm' => 1])->assertOk()
            ->assertSee('تیک «همهٔ تخفیف‌های فعلی سایت متوقف می‌شود»');
        $this->assertSame(0, $this->putsWithConfirm());
        $this->assertFalse(StoqsPromotions::managed());

        // Both ticks: StoqS answers ok, then the shop flips, stores the seq and logs it.
        $this->actingAs($admin)->post(route('admin.settings.promo-source.confirm'), ['to' => 'stoqs', 'confirm' => 1, 'confirm_stop_all' => 1, 'note' => 'شروع کمپین'])
            ->assertRedirect(route('admin.settings.integration'))->assertSessionHas('success');
        $this->assertSame(1, $this->putsWithConfirm());
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && ! empty($r['confirm']) && $r['confirm_stop_all'] === true && $r['note'] === 'شروع کمپین');
        StoqsPromotions::forget();
        $this->assertTrue(StoqsPromotions::managed());
        $s = StoqsPromoSource::state();
        $this->assertSame(5, $s['seq']);
        $this->assertSame('stoqs', $s['source']);
        $this->assertSame('مدیر فروشگاه', $s['by']);
        $this->assertFalse($s['pending'], 'the shop made this change itself: nothing to confirm to StoqS');
        $log = DB::table('stoqs_promo_source_log')->first();
        $this->assertSame(['shop', 'stoqs', 'shop_admin', 5, 1], [$log->from_source, $log->to_source, $log->origin, (int) $log->seq, (int) $log->confirmed]);
        $this->assertTrue((bool) StoqsPromotions::row()->reimport_pending, 'the flip path ran: content and prices come back through the import');
        $this->actingAs($admin)->get(route('admin.settings.promo-source.history'))->assertOk()->assertSee('مدیریت فروشگاه');
    }

    public function test_no_stop_all_warning_when_stoqs_has_plans_to_start(): void
    {
        $this->manage($this->payload(['managed' => false]), ['promo_source_seq' => 4]);
        $this->fakeSwitch(stopAll: false);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.settings.promo-source.prepare'), ['to' => 'stoqs'])->assertOk()
            ->assertSee('کمپین پاییز')->assertDontSee('confirm_stop_all');
        $this->actingAs($admin)->post(route('admin.settings.promo-source.confirm'), ['to' => 'stoqs', 'confirm' => 1])
            ->assertRedirect(route('admin.settings.integration'));
        StoqsPromotions::forget();
        $this->assertTrue(StoqsPromotions::managed());
    }

    public function test_a_stale_seq_409_refreshes_the_state_and_says_so(): void
    {
        $this->manage($this->payload(['managed' => false]), ['promo_source_seq' => 4]);
        $this->fakeSwitch(putStatus: 409);

        $this->actingAs($this->admin())->post(route('admin.settings.promo-source.prepare'), ['to' => 'stoqs'])
            ->assertRedirect(route('admin.settings.integration'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'وضعیت در StoqS تغییر کرده است') && str_contains($m, 'مدیریت با StoqS'));
        StoqsPromotions::forget();
        $this->assertSame(5, StoqsPromoSource::state()['seq']);
        $this->assertTrue(StoqsPromotions::managed(), 'StoqS already manages the site: the shop follows');
        $this->assertSame(0, $this->putsWithConfirm());
    }

    public function test_from_stoqs_by_webhook_applies_at_once_and_acks_with_own_discounts(): void
    {
        DiscountRule::create(['name' => 'قانون ویژه', 'conditions' => [], 'actions' => [], 'is_active' => true]);
        DiscountRule::create(['name' => 'قانون خاموش', 'conditions' => [], 'actions' => [], 'is_active' => false]);
        Coupon::create(['code' => 'OWN10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
        Coupon::create(['code' => 'OLD10', 'type' => 'percent', 'value' => 10, 'is_active' => true, 'expires_at' => now()->subDay()]);
        $this->manage($this->payload(['managed' => true]), ['promo_source_seq' => 2]);
        Http::fake([
            'stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true, 'source_seq_acked' => 3]),
            'stoqs.test/api/v1/promotions*' => Http::response($this->payload(['managed' => false, 'promo_source' => 'shop', 'promo_source_seq' => 3, 'version' => 10]), 200, ['ETag' => '"v10"']),
        ]);

        $this->webhook('promotions.source_changed', ['source' => 'shop', 'seq' => 3, 'changed_at' => now()->toAtomString(), 'by' => 'علی', 'note' => 'برگشت', 'origin' => 'panel'])->assertOk();

        StoqsPromotions::forget();
        $this->assertFalse(StoqsPromotions::managed());
        $s = StoqsPromoSource::state();
        $this->assertSame([3, 'shop', 'علی', 'برگشت', false], [$s['seq'], $s['source'], $s['by'], $s['note'], $s['pending']]);
        $log = DB::table('stoqs_promo_source_log')->first();
        $this->assertSame(['stoqs', 'shop', 'stoqs_panel', 1], [$log->from_source, $log->to_source, $log->origin, (int) $log->confirmed]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'promotions/ack') && ($r->data()['source_seq'] ?? null) === 3
            && $r['own_discounts'] === ['rules' => ['قانون ویژه'], 'coupons' => ['OWN10']]);

        // The same seq again (a replay or an old event): 200, nothing happens.
        $before = Http::recorded()->count();
        $this->webhook('promotions.source_changed', ['source' => 'stoqs', 'seq' => 3, 'origin' => 'api'])->assertOk();
        $this->webhook('promotions.source_changed', ['source' => 'stoqs', 'seq' => 2, 'origin' => 'api'])->assertOk();
        $this->assertSame($before, Http::recorded()->count());
        StoqsPromotions::forget();
        $this->assertFalse(StoqsPromotions::managed());
        $this->assertSame(1, DB::table('stoqs_promo_source_log')->count());
    }

    public function test_a_newer_seq_with_the_same_source_is_stored_without_a_flip(): void
    {
        $this->manage($this->payload(['managed' => true]), ['promo_source_seq' => 2]);
        Http::fake(['stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true])]);

        $this->webhook('promotions.source_changed', ['source' => 'stoqs', 'seq' => 6, 'origin' => 'api', 'by' => 'bot'])->assertOk();

        StoqsPromotions::forget();
        $this->assertTrue(StoqsPromotions::managed());
        $this->assertSame(6, StoqsPromoSource::state()['seq']);
        $this->assertSame('stoqs_api', DB::table('stoqs_promo_source_log')->value('origin'));
        $this->assertFalse((bool) StoqsPromotions::row()->reimport_pending, 'no flip, no re-import');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'promotions/ack') && ($r->data()['source_seq'] ?? null) === 6);
    }

    public function test_a_failed_webhook_then_the_minute_pull_converges_and_acks(): void
    {
        $this->manage($this->payload(['managed' => true]), ['promo_source_seq' => 3]);   // the webhook for seq 4 never arrived
        Http::fake([
            'stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true]),
            'stoqs.test/api/v1/promotions*' => Http::response($this->payload(['managed' => false, 'promo_source' => 'shop', 'promo_source_seq' => 4, 'version' => 11,
                'promo_source_by' => 'علی', 'promo_source_changed_at' => now()->toAtomString()]), 200, ['ETag' => '"v11"']),
        ]);

        $this->artisan('stoqs:promotions-sync')->assertSuccessful();

        StoqsPromotions::forget();
        $this->assertFalse(StoqsPromotions::managed());
        $s = StoqsPromoSource::state();
        $this->assertSame([4, 'علی', false], [$s['seq'], $s['by'], $s['pending']]);
        $log = DB::table('stoqs_promo_source_log')->first();
        $this->assertSame(['stoqs', 'shop', 'pull', 1], [$log->from_source, $log->to_source, $log->origin, (int) $log->confirmed]);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'promotions/ack') && ($r->data()['source_seq'] ?? null) === 4 && isset($r['own_discounts']['coupons']));
    }

    public function test_a_not_managed_answer_carries_the_seq_too_and_a_failed_ack_is_retried(): void
    {
        $this->manage($this->payload(['managed' => false, 'promo_source' => 'shop']), ['promo_source_seq' => 1]);
        $answer = $this->payload(['managed' => false, 'promo_source' => 'shop', 'promo_source_seq' => 2, 'version' => 12]);
        $acks = 0;
        Http::fake([
            'stoqs.test/api/v1/promotions/ack' => function () use (&$acks) {
                return ++$acks <= 2 ? Http::response(['ok' => false], 500) : Http::response(['ok' => true]);
            },
            'stoqs.test/api/v1/promotions*' => Http::response($answer, 200, ['ETag' => '"v12"']),
        ]);

        $this->artisan('stoqs:promotions-sync')->assertSuccessful();   // the pull's ack and the job's retry both fail
        StoqsPromotions::forget();
        $this->assertSame(2, StoqsPromoSource::state()['seq']);
        $this->assertTrue(StoqsPromoSource::state()['pending'], 'the ack failed: pending');
        $this->assertSame('shop', DB::table('stoqs_promo_source_log')->value('to_source'));

        Http::fake(['stoqs.test/api/v1/promotions*' => Http::response('', 304), 'stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true])]);
        $this->artisan('stoqs:promotions-sync')->assertSuccessful();
        StoqsPromotions::forget();
        $this->assertFalse(StoqsPromoSource::state()['pending'], 'the minute job told StoqS');
        $this->assertSame(1, DB::table('stoqs_promo_source_log')->count(), 'logged once');
    }

    public function test_own_discounts_are_capped_at_fifty_names(): void
    {
        foreach (range(1, 55) as $i) {
            Coupon::create(['code' => 'C'.$i, 'type' => 'percent', 'value' => 5, 'is_active' => true]);
        }
        $this->assertCount(50, StoqsPromoSource::ownDiscounts()['coupons']);
        $this->assertSame([], StoqsPromoSource::ownDiscounts()['rules']);
    }
}

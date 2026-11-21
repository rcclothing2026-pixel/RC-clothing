<?php

namespace Tests\Feature;

use App\Models\CampaignApproval;
use App\Models\Category;
use App\Models\OutletItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CampaignPlan;
use App\Support\Campaigns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Black Friday floating bar, pop-ups and card tags (config campaigns.floating_bars / popups / badges). */
class BlackFridayBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'collection']);
    }

    private function approveBf(string $from = '2026-11-14', string $to = '2026-11-30'): void
    {
        CampaignApproval::updateOrCreate(['key' => 'bf'], ['starts_on' => $from, 'ends_on' => $to, 'approved' => true]);
        request()->attributes->remove('campaigns.approvals');
    }

    private function on(string $day): void
    {
        $this->travelTo(Carbon::parse($day.' 12:00', 'Asia/Tehran'));
        request()->attributes->remove('campaigns.approvals');
    }

    private function product(string $slug, string $cat): Product
    {
        $c = Category::firstOrCreate(['slug' => $cat], ['name' => $cat, 'is_active' => true]);
        $p = Product::create(['slug' => $slug, 'name' => $slug, 'price' => 5_000_000, 'category_id' => $c->id, 'is_active' => true]);
        $p->categories()->sync([$c->id]);
        ProductVariant::create(['product_id' => $p->id, 'size' => 'M', 'price' => 5_000_000, 'stock_qty' => 5, 'is_active' => true]);

        return $p->load(['categories', 'category']);
    }

    private function ids(): array
    {
        return array_column(Campaigns::floatingBars(), 'id');
    }

    public function test_floating_bar_waits_for_the_owner_to_approve_bf(): void
    {
        $this->on('2026-11-25');
        $this->assertSame([], Campaigns::floatingBars());
        $this->get('/')->assertOk()->assertDontSee('id="bf-bar"', false);

        $this->approveBf();
        $this->assertSame(['bf'], $this->ids());
    }

    public function test_floating_bar_windows_teaser_then_tiers(): void
    {
        $this->approveBf();

        $this->on('2026-11-20');
        $this->assertSame([], $this->ids());

        $this->on('2026-11-21');
        $bar = Campaigns::floatingBars()[0];
        $this->assertSame('bf-early', $bar['id']);
        $this->assertSame('EARLYRC', $bar['code']);
        $this->assertSame([], $bar['tiers'], 'no tier is claimed before 3 Azar');
        $this->assertContains('EARLYRC', array_keys(config('campaigns.coupons')), 'the code shown is a real coupon');

        $this->on('2026-11-23');
        $this->assertSame(['bf-early'], $this->ids());

        $this->on('2026-11-24');
        $bar = Campaigns::floatingBars()[0];
        $this->assertSame('bf', $bar['id']);
        $this->assertSame('', $bar['code'], 'tiers are automatic, no code');
        $this->assertCount(4, $bar['tiers']);
        $this->assertCount(3, $bar['steps']);
        $this->assertStringContainsString('پولو جامپر', $bar['exclusion']);

        $this->on('2026-11-30');
        $this->assertSame(['bf'], $this->ids());
        $this->on('2026-12-01');
        $this->assertSame([], $this->ids());
    }

    public function test_floating_bar_dates_shift_with_the_approved_window(): void
    {
        $this->approveBf('2026-11-16', '2026-12-02');   // moved two days later in NJ Focus

        $this->on('2026-11-22');
        $this->assertSame([], $this->ids());
        $this->on('2026-11-23');
        $this->assertSame(['bf-early'], $this->ids());
        $this->on('2026-11-26');
        $this->assertSame(['bf'], $this->ids());
        $this->on('2026-12-02');
        $this->assertSame(['bf'], $this->ids());
        $this->on('2026-12-03');
        $this->assertSame([], $this->ids());
    }

    public function test_bar_renders_on_pages_with_its_code_or_the_no_code_note(): void
    {
        $this->approveBf();
        $this->on('2026-11-22');
        $this->get('/')->assertOk()->assertSee('id="bf-bar"', false)->assertSee('EARLYRC')->assertSee('بلک فرایدی ۳ آذر شروع می‌شود');

        $this->on('2026-11-25');
        $res = $this->get('/shop')->assertOk()->assertSee('id="bf-bar"', false)->assertSee('از ۹ میلیون ۸٪')->assertSee('کد لازم نیست');
        $res->assertDontSee('EARLYRC');
    }

    public function test_bar_is_left_off_the_checkout_pages(): void
    {
        $this->approveBf();
        $this->on('2026-11-25');
        $variant = $this->product('tee-1', 'tyshrt')->variants()->first();
        $this->actingAs(\App\Models\User::factory()->create());
        $this->postJson('/cart/add', ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();

        $this->get('/cart')->assertOk()->assertSee('id="bf-bar"', false);
        $this->get('/checkout')->assertOk()->assertDontSee('id="bf-bar"', false);
    }

    public function test_popups_cover_the_teaser_and_the_tiers_once_per_session_on_all_pages(): void
    {
        $this->approveBf();

        $this->on('2026-11-20');
        $this->assertSame([], array_column(Campaigns::popups(), 'id'));

        $this->on('2026-11-21');
        $pop = collect(Campaigns::popups());
        $this->assertSame(['c-bf-early'], $pop->pluck('id')->all());
        $this->assertSame(['session', 'all', 5], [$pop[0]['frequency'], $pop[0]['pages'], $pop[0]['delay']]);
        $this->assertStringContainsString('EARLYRC', $pop[0]['html']);

        $this->on('2026-11-24');
        $pop = collect(Campaigns::popups());
        $this->assertSame(['c-bf'], $pop->pluck('id')->all());
        $this->assertSame(['session', 'all'], [$pop[0]['frequency'], $pop[0]['pages']]);
        $this->assertStringNotContainsString('EARLYRC', $pop[0]['html']);
    }

    public function test_promo_slides_leave_no_gap(): void
    {
        $this->approveBf();
        foreach (['2026-11-21' => 'دسترسی زودهنگام', '2026-11-23' => 'دسترسی زودهنگام', '2026-11-24' => 'از ۹ میلیون ۸٪'] as $day => $needle) {
            $this->on($day);
            $this->assertTrue(collect(Campaigns::promoSlides())->contains(fn ($s) => str_contains($s['text'], $needle)), "$day: $needle");
        }
        $this->on('2026-11-22');
        $this->assertFalse(collect(Campaigns::promoSlides())->contains(fn ($s) => str_contains($s['text'], 'از ۹ میلیون')), 'no tier text before 24 Nov');
    }

    public function test_bf_tags_follow_the_window_and_approval_and_stay_honest_for_polo_jumpers(): void
    {
        $tee = $this->product('tee-1', 'tyshrt');
        $polo = $this->product('polo-1', 'gampr');

        $this->on('2026-11-25');
        $this->assertSame([], Campaigns::tagsFor($tee), 'unapproved: nothing');

        $this->approveBf();
        $this->assertSame([['text' => 'بلک فرایدی', 'tone' => 'bf']], Campaigns::tagsFor($tee));
        $this->assertSame([['text' => 'بلک فرایدی · حساب در پله‌ها', 'tone' => 'quiet']], Campaigns::tagsFor($polo));
        $this->assertStringNotContainsString('تخفیف', Campaigns::tagsFor($polo)[0]['text']);

        $this->on('2026-11-23');
        $this->assertSame([], Campaigns::tagsFor($tee), 'the tier discount is not live before 24 Nov');
        $this->on('2026-12-01');
        $this->assertSame([], Campaigns::tagsFor($tee));
    }

    public function test_bf_tags_shift_with_the_approved_window(): void
    {
        $tee = $this->product('tee-1', 'tyshrt');
        $this->approveBf('2026-11-16', '2026-12-02');
        $this->on('2026-11-25');
        $this->assertSame([], Campaigns::tagsFor($tee));
        $this->on('2026-11-26');
        $this->assertCount(1, Campaigns::tagsFor($tee));
    }

    public function test_outlet_tag_shows_for_active_outlet_items_only_and_comes_after_bf(): void
    {
        $outlet = $this->product('out-1', 'tyshrt');
        $plain = $this->product('plain-1', 'klah');
        $off = $this->product('off-1', 'klah');
        OutletItem::create(['product_id' => $outlet->id, 'percent' => 20, 'is_active' => true]);
        OutletItem::create(['product_id' => $off->id, 'percent' => 20, 'is_active' => false]);

        $this->on('2026-11-10');   // outside Black Friday, bf not approved
        $this->assertSame([['text' => 'اوتلت', 'tone' => 'outlet']], Campaigns::tagsFor($outlet));
        $this->assertSame([], Campaigns::tagsFor($plain));
        request()->attributes->remove('outlet.percents');
        $this->assertSame([], Campaigns::tagsFor($off));

        $this->approveBf();
        $this->on('2026-11-25');
        request()->attributes->remove('outlet.percents');
        $this->assertSame(['bf', 'outlet'], array_column(Campaigns::tagsFor($outlet), 'tone'));
        $this->assertSame(['bf'], array_column(Campaigns::tagsFor($plain), 'tone'));
    }

    public function test_cards_and_product_page_render_the_tags(): void
    {
        $outlet = $this->product('out-1', 'tyshrt');
        OutletItem::create(['product_id' => $outlet->id, 'percent' => 20, 'is_active' => true]);
        $this->approveBf();
        $this->on('2026-11-25');

        $this->get('/product/out-1')->assertOk()->assertSee('بلک فرایدی', false)->assertSee('اوتلت');
        $this->get('/shop')->assertOk()->assertSee('اوتلت');
    }

    public function test_plan_editor_keeps_floating_bars(): void
    {
        $e = CampaignPlan::toEditor(config('campaigns'));
        $this->assertSame(['bf-early', 'bf'], array_column($e['floating_bars'], 'key'));
        $back = CampaignPlan::fromEditor($e);
        $this->assertSame(config('campaigns.floating_bars'), $back['floating_bars']);
        $this->assertSame([], CampaignPlan::validate($back));
    }

    public function test_without_nj_focus_campaigns_run_on_their_own_dates_and_a_saved_plan_is_ignored(): void
    {
        config(['campaigns.nj_focus' => false, 'campaigns_file.nj_focus' => false]);
        $file = config('campaigns');
        \DB::table('campaign_plans')->insert(['id' => 1, 'plan' => json_encode(['popups' => ['bf' => ['title' => 'OLD BF', 'campaign' => 'bf']]]), 'created_at' => now(), 'updated_at' => now()]);
        CampaignPlan::apply();
        $this->assertSame($file['popups'], config('campaigns.popups'));   // the saved copy is not read

        // no approval row anywhere: the planned window of bf (14 to 30 Nov) is the window
        $this->on('2026-11-25');
        $this->assertSame([config('campaigns.campaigns.bf.from'), config('campaigns.campaigns.bf.to')], Campaigns::approvals()['bf']);
        $this->assertSame(['bf'], $this->ids());
        $this->assertContains('c-bf', array_column(Campaigns::popups(), 'id'));   // no approval row needed

        $this->on('2026-12-02');
        $this->assertSame([], Campaigns::floatingBars());
        $this->assertNotContains('c-bf', array_column(Campaigns::popups(), 'id'));
    }
}

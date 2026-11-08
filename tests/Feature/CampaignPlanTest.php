<?php

namespace Tests\Feature;

use App\Models\CampaignApproval;
use App\Models\Coupon;
use App\Models\DiscountRule;
use App\Support\Campaigns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The campaign plan edited from NJ Focus (/api/campaigns/editor). */
class CampaignPlanTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'plan-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ig_catalog.key' => self::KEY, 'scout.driver' => 'collection']);
    }

    private function api(string $method, string $path, array $data = [])
    {
        return $this->withHeaders(['X-Catalog-Key' => self::KEY])->json($method, '/api/campaigns'.$path, $data);
    }

    private function editor(): array
    {
        return $this->api('GET', '/editor')->assertOk()->json();
    }

    public function test_editor_round_trips_the_default_plan_unchanged(): void
    {
        $e = $this->editor();
        $this->assertFalse($e['edited']);
        $this->assertNotEmpty($e['plan']['rules']);
        $this->assertSame('type', array_key_first($e['plan']['rules'][0]['conditions'][0] ?? ['type' => 1]));

        $this->api('PUT', '/editor', ['plan' => $e['plan'], 'by' => 'owner'])->assertOk()->assertJsonPath('edited', true);
        $this->assertEquals(array_diff_key(config('campaigns_file'), ['card' => 1]), array_diff_key(config('campaigns'), ['card' => 1]));
    }

    public function test_edits_change_rules_coupons_and_popups_and_reset_restores(): void
    {
        $e = $this->editor();
        $e['plan']['campaigns'][] = ['key' => 'test-week', 'name' => 'هفتهٔ آزمایشی', 'from' => '2026-12-01', 'to' => '2026-12-07'];
        $e['plan']['rules'][] = ['key' => 'test-3', 'label' => '۱۲٪ تخفیف سه قطعه', 'campaign' => 'test-week',
            'conditions' => [['type' => 'cart_item_count', 'min' => '3']], 'actions' => [['type' => 'cart_discount_percent', 'value' => '12']]];
        $e['plan']['coupons'][] = ['key' => 'TESTWEEK', 'type' => 'percent', 'value' => 9, 'description' => 'کد آزمایشی', 'campaign' => 'test-week'];
        $e['plan']['popups'][] = ['key' => 'test-week', 'title' => 'هفتهٔ <b>آزمایشی</b>', 'body' => 'سه قطعه بخرید', 'cta' => 'خرید', 'url' => '/shop', 'campaign' => 'test-week'];
        $first = $e['plan']['coupons'][0]['key'];
        array_shift($e['plan']['coupons']);   // drop the first-order code

        $this->api('PUT', '/editor', ['plan' => $e['plan']])->assertOk();

        $rule = DiscountRule::where('description', 'campaign:test-3')->firstOrFail();
        $this->assertFalse($rule->is_active);   // not approved yet
        $this->assertSame([['type' => 'cart_item_count', 'min' => 3]], $rule->conditions);
        $this->assertSame([['type' => 'cart_discount_percent', 'value' => 12]], $rule->actions);
        $this->assertFalse(Coupon::where('code', 'TESTWEEK')->value('is_active'));

        CampaignApproval::create(['key' => 'test-week', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-07', 'approved' => true]);
        request()->attributes->remove('campaigns.approvals');
        $this->artisan('campaigns:sync');
        $this->assertTrue(DiscountRule::where('description', 'campaign:test-3')->value('is_active'));
        $this->assertTrue(Coupon::where('code', 'TESTWEEK')->value('is_active'));
        $this->assertFalse((bool) Coupon::where('code', $first)->value('is_active'));

        $this->travelTo(Carbon::parse('2026-12-03 12:00', 'Asia/Tehran'));
        $pop = collect(Campaigns::popups())->firstWhere('id', 'c-test-week');
        $this->assertStringContainsString('هفتهٔ &lt;b&gt;آزمایشی&lt;/b&gt;', $pop['html']);
        $this->assertStringContainsString('سه قطعه بخرید', $pop['html']);

        // The new campaign is in the plan NJ Focus pulls.
        $this->assertContains('test-week', array_column($this->api('GET', '/plan')->json('campaigns'), 'key'));

        $this->api('POST', '/editor/reset')->assertOk()->assertJsonPath('edited', false);
        $this->assertFalse(DiscountRule::where('description', 'campaign:test-3')->value('is_active'));
        $this->assertFalse((bool) Coupon::where('code', 'TESTWEEK')->value('is_active'));
        $this->assertArrayNotHasKey('test-week', config('campaigns.campaigns'));
    }

    public function test_bad_edits_are_refused_with_reasons_and_nothing_changes(): void
    {
        $e = $this->editor();
        $e['plan']['rules'][0]['actions'] = [['type' => 'cart_discount_percent', 'value' => 40]];
        $e['plan']['coupons'][] = ['key' => 'bad code', 'type' => 'percent', 'value' => 5];
        $e['plan']['slides'][] = ['text' => 'x', 'campaign' => 'nope'];

        $errors = $this->api('PUT', '/editor', ['plan' => $e['plan']])->assertStatus(422)->json('errors');
        $this->assertCount(3, $errors);
        $this->assertFalse($this->editor()['edited']);
    }

    public function test_discounts_outside_the_plan_are_fully_editable(): void
    {
        $cat = \App\Models\Category::create(['name' => 'تی‌شرت', 'slug' => 'ty-shrt', 'is_active' => true]);
        $rule = DiscountRule::create(['name' => 'پله ۱', 'description' => 'admin', 'conditions' => [['type' => 'cart_subtotal', 'min' => 5000000]],
            'actions' => [['type' => 'cart_discount_percent', 'value' => 5]], 'apply_mode' => 'all', 'stack_mode' => 'all', 'is_active' => true]);
        $coupon = Coupon::create(['code' => 'OLD10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);

        $row = collect($this->editor()['extra']['rules'])->firstWhere('id', $rule->id);
        $this->assertSame([['type' => 'cart_subtotal', 'min' => 5000000]], $row['conditions']);
        $this->assertNotNull(collect($this->editor()['extra']['coupons'])->firstWhere('code', 'OLD10'));

        // Replace conditions and actions, switch to «any», add limits and dates.
        $row['conditions'] = [['type' => 'item_count_from_categories', 'categories' => ['ty-shrt'], 'min' => 2], ['type' => 'user_device', 'device' => 'mobile']];
        $row['actions'] = [['type' => 'cart_discount_percent', 'value' => 7], ['type' => 'discount_cap', 'value' => 500000]];
        $row = array_merge($row, ['apply_mode' => 'any', 'stack_mode' => 'best', 'max_uses' => 50, 'priority' => 30, 'to' => '2026-12-30']);
        $row['is_active'] = false;
        $this->api('PUT', "/discounts/rules/{$rule->id}", $row)->assertOk();
        $rule->refresh();
        $this->assertSame([['type' => 'item_count_from_categories', 'min' => 2, 'category_ids' => [$cat->id]], ['type' => 'user_device', 'device' => 'mobile']], $rule->conditions);
        $this->assertSame([['type' => 'cart_discount_percent', 'value' => 7], ['type' => 'discount_cap', 'value' => 500000]], $rule->actions);
        $this->assertSame(['any', 'best', 50, 30, false], [$rule->apply_mode, $rule->stack_mode, $rule->max_uses, $rule->priority, $rule->is_active]);
        $this->assertSame('2026-12-30', $rule->expires_at->setTimezone('Asia/Tehran')->toDateString());

        // Refusals: over the cap, an empty list condition, a block without a message.
        $bad = ['label' => 'x', 'conditions' => [['type' => 'item_brands', 'brands' => []]], 'actions' => [['type' => 'cart_discount_percent', 'value' => 40], ['type' => 'block_purchase', 'value' => 0]]];
        $this->assertCount(3, $this->api('PUT', "/discounts/rules/{$rule->id}", $bad)->assertStatus(422)->json('errors'));

        // Create and delete a new rule.
        $new = ['label' => 'پرداخت کارت', 'conditions' => [['type' => 'order_payment_method', 'method_keys' => ['zarinpal']]], 'actions' => [['type' => 'cart_surcharge_fixed', 'value' => 20000]]];
        $this->api('POST', '/discounts/rules', $new)->assertOk();
        $made = DiscountRule::where('name', 'پرداخت کارت')->firstOrFail();
        $this->assertSame('nj-focus', $made->description);
        $this->api('DELETE', "/discounts/rules/{$made->id}")->assertOk();
        $this->assertNull(DiscountRule::find($made->id));

        // Coupons: edit, create, delete; plan rules and codes are refused here.
        $this->api('PUT', "/discounts/coupons/{$coupon->id}", ['value' => 30])->assertStatus(422);
        $this->api('PUT', "/discounts/coupons/{$coupon->id}", ['value' => 12, 'is_active' => false, 'per_user_limit' => 1])->assertOk();
        $this->assertSame([12, false, 1], [(int) $coupon->fresh()->value, $coupon->fresh()->is_active, $coupon->fresh()->per_user_limit]);
        $this->api('POST', '/discounts/coupons', ['code' => 'new5', 'type' => 'fixed', 'value' => 500000, 'min_subtotal' => 3000000])->assertOk();
        $this->assertSame(500000, (int) Coupon::where('code', 'NEW5')->value('value'));
        $this->api('POST', '/discounts/coupons', ['code' => 'NEW5', 'value' => 5])->assertStatus(422);
        $this->api('DELETE', '/discounts/coupons/'.Coupon::where('code', 'NEW5')->value('id'))->assertOk();
        $this->assertFalse(Coupon::where('code', 'NEW5')->exists());

        $this->artisan('campaigns:sync');
        $plan = DiscountRule::where('description', 'like', 'campaign:%')->firstOrFail();
        $this->api('PUT', "/discounts/rules/{$plan->id}", ['label' => 'x', 'actions' => [['type' => 'free_shipping']]])->assertStatus(422);
        $this->api('DELETE', "/discounts/rules/{$plan->id}")->assertStatus(422);
    }

    public function test_plan_rules_take_any_engine_condition_and_rule_settings(): void
    {
        $e = $this->editor();
        $e['plan']['rules'][] = ['key' => 'vip', 'label' => 'ویژهٔ اعضا', 'campaign' => 'always', 'apply_mode' => 'any', 'stack_mode' => 'best', 'max_uses_per_user' => 1, 'priority' => 40,
            'conditions' => [['type' => 'user_group', 'group_ids' => ['vip']], ['type' => 'coupon_code', 'code' => 'VIP']],
            'actions' => [['type' => 'block_purchase', 'value' => 0, 'message' => 'فعلاً نه'], ['type' => 'item_surcharge_percent', 'value' => 5]]];
        $this->api('PUT', '/editor', ['plan' => $e['plan']])->assertOk();
        $r = DiscountRule::where('description', 'campaign:vip')->firstOrFail();
        $this->assertSame(['any', 'best', 1, 40], [$r->apply_mode, $r->stack_mode, $r->max_uses_per_user, $r->priority]);
        $this->assertSame([['type' => 'block_purchase', 'value' => 0, 'message' => 'فعلاً نه'], ['type' => 'item_surcharge_percent', 'value' => 5]], $r->actions);
        $this->assertSame([['type' => 'user_group', 'group_ids' => ['vip']], ['type' => 'coupon_code', 'code' => 'VIP']], $r->conditions);
        $back = collect($this->editor()['plan']['rules'])->firstWhere('key', 'vip');
        $this->assertSame('فعلاً نه', $back['actions'][0]['message']);
        $this->assertNotEmpty($this->editor()['options']['conditions']['user_city']);
    }

    public function test_editor_needs_the_key(): void
    {
        $this->withHeaders(['X-Catalog-Key' => 'wrong'])->getJson('/api/campaigns/editor')->assertNotFound();
        $this->withHeaders(['X-Catalog-Key' => 'wrong'])->putJson('/api/campaigns/editor', ['plan' => []])->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SellLink;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellLinkTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null']); // stock edits below must not call Meilisearch
        $this->seed();
        // Seeded stock is random (can be 0) — pin a known buyable variant.
        $this->variant = ProductVariant::whereHas('product', fn ($p) => $p->where('is_active', true)->where('is_bundle', false))
            ->where('is_active', true)->firstOrFail();
        $this->variant->update(['stock_qty' => 5]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function makeLink(array $attrs = []): SellLink
    {
        return SellLink::create(array_merge([
            'code' => SellLink::newCode(),
            'items' => [['variant_id' => $this->variant->id, 'qty' => 2]],
            'expires_at' => now()->addDay(),
        ], $attrs));
    }

    public function test_sell_app_is_admin_only(): void
    {
        $this->get(route('admin.sell.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.sell.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.sell.index'))
            ->assertOk()->assertSee('فروش سریع')->assertSee('sell-manifest.webmanifest');
    }

    public function test_admin_search_and_create_link(): void
    {
        $this->actingAs($this->admin());

        $this->getJson(route('admin.sell.search', ['q' => $this->variant->sku]))
            ->assertOk()
            ->assertJsonPath('products.0.id', $this->variant->product_id);

        $res = $this->postJson(route('admin.sell.store'), [
            'items' => [['variant_id' => $this->variant->id, 'qty' => 1], ['variant_id' => $this->variant->id, 'qty' => 1]],
            'handle' => '@someone',
            'hours' => 72,
        ])->assertOk()->assertJsonStructure(['code', 'url', 'total', 'units']);

        $link = SellLink::where('code', $res->json('code'))->firstOrFail();
        $this->assertSame([['variant_id' => $this->variant->id, 'qty' => 2]], $link->items); // merged
        $this->assertSame('@someone', $link->customer_handle);
        $this->assertTrue($link->expires_at->between(now()->addHours(71), now()->addHours(73)));

        $this->getJson(route('admin.sell.links'))->assertOk()
            ->assertJsonPath('links.0.code', $link->code)
            ->assertJsonPath('links.0.status', 'sent');
    }

    public function test_unknown_coupon_is_rejected(): void
    {
        $this->actingAs($this->admin())->postJson(route('admin.sell.store'), [
            'items' => [['variant_id' => $this->variant->id, 'qty' => 1]],
            'coupon' => 'NOPE-NOT-REAL',
            'hours' => 24,
        ])->assertStatus(422);
        $this->assertSame(0, SellLink::count());
    }

    public function test_opening_link_fills_cart_without_doubling(): void
    {
        $link = $this->makeLink();

        $this->get('/b/'.$link->code)
            ->assertOk()
            ->assertSee('سبد خرید شما آماده است')
            ->assertSee($this->variant->product->name)
            ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache');
        $this->get('/b/'.strtolower($link->code))->assertOk();

        $this->assertSame([$this->variant->id => 2], session('cart'));
        $this->assertSame($link->id, session('sell_link_id'));
        $this->assertSame(route('checkout.index'), session('url.intended'));
        $this->assertSame(2, $link->fresh()->open_count);
        $this->assertNotNull($link->fresh()->opened_at);
    }

    public function test_link_keeps_existing_cart_items(): void
    {
        $other = ProductVariant::where('id', '!=', $this->variant->id)->where('is_active', true)
            ->whereHas('product', fn ($p) => $p->where('is_active', true)->where('is_bundle', false))->firstOrFail();
        $other->update(['stock_qty' => 3]);

        $this->post(route('cart.add'), ['variant_id' => $other->id, 'quantity' => 1]);
        $this->get('/b/'.$this->makeLink()->code)->assertOk();

        $this->assertSame([$other->id => 1, $this->variant->id => 2], session('cart'));
    }

    public function test_link_preview_bots_do_not_count_or_fill(): void
    {
        $link = $this->makeLink();

        $this->withHeader('User-Agent', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)')
            ->get('/b/'.$link->code)->assertOk();

        $this->assertNull(session('cart'));
        $this->assertSame(0, $link->fresh()->open_count);
    }

    public function test_sold_out_and_expired_links(): void
    {
        $this->variant->update(['stock_qty' => 0]);
        $this->get('/b/'.$this->makeLink()->code)->assertOk()->assertSee('تمام شده');

        $this->get('/b/'.$this->makeLink(['expires_at' => now()->subMinute()])->code)
            ->assertStatus(410)->assertSee('منقضی');

        $this->get('/b/ZZZZZ')->assertNotFound();
    }

    public function test_order_from_link_is_attached_and_shows_as_paid(): void
    {
        $user = User::factory()->create(['phone' => '09120001199']);
        $address = Address::create([
            'user_id' => $user->id, 'recipient_name' => 'تست', 'phone' => '09120001199',
            'province' => 'تهران', 'city' => 'تهران', 'line' => 'خیابان نمونه',
        ]);
        $link = $this->makeLink();

        $this->actingAs($user)->get('/b/'.$link->code)->assertOk()->assertSee(route('checkout.index'));

        $place = $this->post(route('checkout.place'), [
            'address_id' => (string) $address->id,
            'shipping_method_id' => ShippingMethod::first()->id,
            'payment_method' => 'zarinpal',
        ])->assertRedirect();
        $this->get($place->headers->get('Location')); // dev gateway callback

        $order = Order::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame($order->id, $link->fresh()->order_id);
        $this->assertNull(session('sell_link_id'));
        $this->assertSame('paid', $link->fresh()->status());

        // Success page: Telegram block only when the bot is configured; home link always.
        $this->get(route('checkout.success', $order))->assertOk()->assertSee('دیدن بقیه محصولات');
    }

    public function test_checkout_preselects_last_orders_shipping(): void
    {
        $methods = ShippingMethod::where('is_active', true)->orderBy('position')->get();
        if ($methods->count() < 2) {
            $this->markTestSkipped('needs two active shipping methods');
        }
        $user = User::factory()->create();
        Order::create([
            'number' => Order::generateNumber(), 'user_id' => $user->id, 'status' => Order::STATUS_PAID,
            'subtotal' => 1000, 'shipping_cost' => 0, 'discount' => 0, 'total' => 1000,
            'shipping_method_id' => $methods[1]->id, 'shipping_method_name' => $methods[1]->name,
            'shipping_address' => [], 'customer_name' => 'x', 'customer_phone' => '09120000001', 'placed_at' => now(),
        ]);

        $this->actingAs($user)->post(route('cart.add'), ['variant_id' => $this->variant->id, 'quantity' => 1]);
        $html = $this->get(route('checkout.index'))->assertOk()->getContent();

        preg_match_all('/<input type="radio" name="shipping_method_id" value="(\d+)"[^>]*>/s', $html, $m, PREG_SET_ORDER);
        $checked = collect($m)->filter(fn ($tag) => str_contains($tag[0], 'checked'))->map(fn ($tag) => (int) $tag[1])->values()->all();
        $this->assertSame([$methods[1]->id], $checked);
    }
}

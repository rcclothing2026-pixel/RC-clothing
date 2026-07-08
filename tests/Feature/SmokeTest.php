<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Pattern;
use App\Models\Product;
use App\Models\User;
use App\Support\Blocks\BlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Broad smoke coverage of the things we're actively iterating on:
 *  · every admin GET index page renders without a 500
 *  · page-builder save/update persists blocks
 *  · template apply replaces / appends blocks correctly
 *  · pattern save (JSON) + server-render endpoint
 *  · every registered block-card renders in the admin editor template
 *  · every registered block renders on the public page route
 *  · per-block visibility (`_v`) gates auth/guest correctly
 *  · the public storefront pages (home, page/{slug}, product, contact, cart)
 *
 * Each test is shallow on purpose — assert no 500 + the one thing that
 * changed. Bugs that creep in via Blade syntax, missing fields, nested
 * forms, etc. will all light up here.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function slug(string $base): string
    {
        return $base.'-'.uniqid();
    }

    // ────────────────────────────────────────────────────────────────────
    // Admin pages — every GET *.index smoke-loads.
    // ────────────────────────────────────────────────────────────────────

    #[DataProvider('adminIndexRoutes')]
    public function test_admin_index_route_smoke(string $route): void
    {
        $this->actingAs($this->admin());
        $this->get(route($route))->assertOk();
    }

    public static function adminIndexRoutes(): array
    {
        return [
            'dashboard'        => ['admin.dashboard'],
            'pages.index'      => ['admin.pages.index'],
            'pages.create'     => ['admin.pages.create'],
            'patterns.index'   => ['admin.patterns.index'],
            'categories'       => ['admin.categories.index'],
            'collections'      => ['admin.collections.index'],
            'products'         => ['admin.products.index'],
            'orders'           => ['admin.orders.index'],
            'customers'        => ['admin.customers.index'],
            'coupons'          => ['admin.coupons.index'],
            'discounts'        => ['admin.discounts.index'],
            'discount-rules'   => ['admin.discount-rules.index'],
            'gift-cards'       => ['admin.gift-cards.index'],
            'media'            => ['admin.media.index'],
            'menus'            => ['admin.menus.index'],
            'messages'         => ['admin.messages.index'],
            'popups'           => ['admin.popups.index'],
            'returns'          => ['admin.returns.index'],
            'shipping'         => ['admin.shipping.index'],
            'subscribers'      => ['admin.subscribers.index'],
            'hero.edit'        => ['admin.hero.edit'],
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // Page builder
    // ────────────────────────────────────────────────────────────────────

    public function test_page_builder_edit_renders_for_an_existing_page(): void
    {
        $this->actingAs($this->admin());
        $page = Page::create([
            'title' => 'دربارهٔ ما', 'slug' => $this->slug('about'),
            'is_published' => true, 'blocks' => [],
        ]);

        $this->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertSee('ویرایش: دربارهٔ ما', false)
            ->assertSee('افزودن بلاک', false);
    }

    public function test_page_builder_save_persists_blocks(): void
    {
        $this->actingAs($this->admin());
        $page = Page::create([
            'title' => 'تست', 'slug' => $this->slug('smoke-page'),
            'is_published' => true, 'blocks' => [],
        ]);

        $this->put(route('admin.pages.update', $page), [
            'title' => 'تست',
            'slug' => $page->slug,
            'is_published' => '1',
            'blocks' => [
                ['type' => 'rich_text', 'data' => ['body' => '<p>hello</p>'], '_v' => 'all'],
                ['type' => 'image',     'data' => ['url'  => '/placeholder.png'], '_v' => 'mobile'],
            ],
        ])->assertRedirect();

        $page->refresh();
        $this->assertCount(2, $page->blocks);
        $this->assertSame('rich_text', $page->blocks[0]['type']);
        $this->assertSame('mobile',    $page->blocks[1]['_v']);
    }

    public function test_live_preview_renders_unsaved_blocks(): void
    {
        $this->actingAs($this->admin());
        $resp = $this->post(route('admin.pages.preview'), [
            'title'  => 'پیش‌نمایش',
            'slug'   => 'preview',
            'blocks' => [
                ['type' => 'rich_text', 'data' => ['body' => '<p>LIVE_PREVIEW_MARKER</p>'], '_v' => 'all'],
            ],
        ]);
        $resp->assertOk()->assertSee('LIVE_PREVIEW_MARKER', false);
    }

    public function test_apply_template_replaces_or_appends(): void
    {
        $this->actingAs($this->admin());
        $page = Page::create([
            'title' => 'با قالب', 'slug' => $this->slug('tpl-page'),
            'is_published' => true,
            'blocks' => [['type' => 'rich_text', 'data' => ['body' => 'EXISTING']]],
        ]);
        $tplSlug = array_key_first(Page::templates());
        $tplBlocks = Page::templates()[$tplSlug]['blocks'];

        $this->post(route('admin.pages.applyTemplate', $page), [
            'template' => $tplSlug, 'mode' => 'replace',
        ])->assertRedirect();
        $page->refresh();
        $this->assertCount(count($tplBlocks), $page->blocks);

        $existingCount = count($page->blocks);
        $this->post(route('admin.pages.applyTemplate', $page), [
            'template' => $tplSlug, 'mode' => 'append',
        ])->assertRedirect();
        $page->refresh();
        $this->assertCount($existingCount + count($tplBlocks), $page->blocks);
    }

    // ────────────────────────────────────────────────────────────────────
    // Patterns
    // ────────────────────────────────────────────────────────────────────

    public function test_pattern_save_then_render(): void
    {
        $this->actingAs($this->admin());

        $resp = $this->postJson(route('admin.patterns.store'), [
            'name'   => 'بنر تست',
            'icon'   => '🎯',
            'blocks' => [
                ['type' => 'rich_text', 'data' => ['body' => '<p>pattern body</p>']],
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $pattern = Pattern::findOrFail($resp->json('id'));
        $this->assertSame('بنر تست', $pattern->name);
        $this->assertCount(1, $pattern->blocks);

        $renderResp = $this->get(route('admin.patterns.render', $pattern))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $html = $renderResp->json('html');
        $this->assertStringContainsString('__I__', $html);
        $this->assertStringContainsString('block-card', $html);
    }

    // ────────────────────────────────────────────────────────────────────
    // Block-card render in the admin editor — every registered type.
    // Catches Blade-syntax / missing-field / field-include bugs per block.
    // ────────────────────────────────────────────────────────────────────

    #[DataProvider('blockTypes')]
    public function test_admin_block_card_renders(string $type): void
    {
        $html = view('admin.pages._block', [
            'type' => $type, 'data' => [], 'i' => 0, 'vis' => 'all',
        ])->render();

        // The compiled view must produce real HTML, NOT raw Blade source —
        // a `<?php(` open-tag bug would leak `{{` and `@if` markers into the
        // output. Assert none of those leak.
        $this->assertStringContainsString('block-card', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('@if', $html);
        $this->assertStringNotContainsString('@endphp', $html);
        $this->assertStringNotContainsString('<?php(', $html);
    }

    #[DataProvider('blockTypes')]
    public function test_public_block_renders(string $type): void
    {
        if (in_array($type, ['hero_banner'], true)) {
            $this->markTestSkipped("$type renders from a separate admin store");
        }

        $html = view('blocks.render', [
            'block' => ['type' => $type, 'data' => [], '_v' => 'all'],
        ])->render();

        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('@php', $html);
        $this->assertStringNotContainsString('<?php(', $html);
    }

    public static function blockTypes(): array
    {
        $cases = [];
        foreach (array_keys(BlockRegistry::all()) as $type) {
            $cases[$type] = [$type];
        }

        return $cases;
    }

    // ────────────────────────────────────────────────────────────────────
    // Per-block visibility (_v) gates
    // ────────────────────────────────────────────────────────────────────

    public function test_visibility_auth_and_guest_gates(): void
    {
        $slug = $this->slug('vis');
        Page::create([
            'title' => 'Vis', 'slug' => $slug, 'is_published' => true,
            'blocks' => [
                ['type' => 'rich_text', 'data' => ['body' => '<p>AUTHONLY</p>'],  '_v' => 'auth'],
                ['type' => 'rich_text', 'data' => ['body' => '<p>GUESTONLY</p>'], '_v' => 'guest'],
                ['type' => 'rich_text', 'data' => ['body' => '<p>EVERYONE</p>'],  '_v' => 'all'],
            ],
        ]);

        $resp = $this->get('/page/'.$slug)->assertOk();
        $resp->assertSee('GUESTONLY', false);
        $resp->assertSee('EVERYONE', false);
        $resp->assertDontSee('AUTHONLY', false);

        $this->actingAs(User::factory()->create());
        $resp = $this->get('/page/'.$slug)->assertOk();
        $resp->assertSee('AUTHONLY', false);
        $resp->assertSee('EVERYONE', false);
        $resp->assertDontSee('GUESTONLY', false);
    }

    public function test_visibility_desktop_and_mobile_wrap_classes(): void
    {
        $slug = $this->slug('bp');
        Page::create([
            'title' => 'Bp', 'slug' => $slug, 'is_published' => true,
            'blocks' => [
                ['type' => 'rich_text', 'data' => ['body' => '<p>DESK</p>'], '_v' => 'desktop'],
                ['type' => 'rich_text', 'data' => ['body' => '<p>MOB</p>'],  '_v' => 'mobile'],
            ],
        ]);

        $html = $this->get('/page/'.$slug)->assertOk()->getContent();
        $this->assertStringContainsString('hidden md:block', $html);  // desktop wrapper
        $this->assertStringContainsString('md:hidden', $html);        // mobile wrapper
    }

    // ────────────────────────────────────────────────────────────────────
    // Public storefront smoke
    // ────────────────────────────────────────────────────────────────────

    public function test_public_pages_load(): void
    {
        $this->get('/')->assertOk();
        $this->get('/contact')->assertOk();
        $this->get('/cart')->assertOk();
        $this->get('/gift')->assertOk();

        if ($product = Product::first()) {
            $this->get('/product/'.$product->slug)->assertOk();
        }

        if ($page = Page::where('is_home', false)->first()) {
            $this->get('/page/'.$page->slug)->assertOk();
        }
    }

    public function test_shop_filter_in_stock_does_not_500(): void
    {
        $this->get('/shop?in_stock=1')->assertOk();
        $this->get('/shop?in_stock=1&sort=price_asc')->assertOk();
    }

    public function test_gift_surprise_redirects_or_falls_back(): void
    {
        // Either redirects to a product (302) or back to /gift with a flash
        // error if no in-budget products exist in the test seed.
        $resp = $this->get('/gift/surprise');
        $this->assertContains($resp->status(), [302, 200]);
    }
}

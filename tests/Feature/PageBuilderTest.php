<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBuilderTest extends TestCase
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

    public function test_admin_can_open_builder_index_and_create_form(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.pages.index'))->assertOk()->assertSee('صفحه‌ساز');
        $this->get(route('admin.pages.create'))->assertOk()->assertSee('افزودن بلاک');
    }

    public function test_admin_can_store_a_page_with_blocks(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.pages.store'), [
            'title' => 'لوک‌بوک',
            'slug' => 'lookbook',
            'is_published' => '1',
            'blocks' => [
                ['type' => 'hero', 'data' => ['title' => 'سلام دنیا', 'style' => 'dark']],
                ['type' => 'bogus', 'data' => ['x' => 'y']], // unknown type is dropped
                ['type' => 'product_grid', 'data' => ['heading' => 'منتخب', 'source' => 'featured', 'limit' => '4']],
            ],
        ])->assertRedirect();

        $page = Page::where('slug', 'lookbook')->firstOrFail();
        $this->assertCount(2, $page->blocks); // bogus block filtered out
        $this->assertSame('hero', $page->blocks[0]['type']);
        $this->assertSame('سلام دنیا', $page->blocks[0]['data']['title']);
        $this->assertSame(4, $page->blocks[1]['data']['limit']); // number coerced
        $this->assertArrayNotHasKey('x', $page->blocks[0]['data']); // only declared keys kept
    }

    public function test_motion_banner_slides_are_saved_and_rendered(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.pages.store'), [
            'title' => 'بنر', 'slug' => 'banner', 'is_published' => '1',
            'blocks' => [
                ['type' => 'motion_banner', 'data' => [
                    'interval' => '6',
                    'slides' => [
                        ['title' => 'مبل کاناپه', 'color' => 'purple', 'image' => '/x.png', 'subtitle' => 'راحتی'],
                        ['title' => 'گیاه آپارتمانی', 'color' => 'teal', 'cta_text' => 'خرید'],
                        ['title' => '', 'color' => 'red'], // empty row dropped
                    ],
                ]],
            ],
        ])->assertRedirect();

        $page = Page::where('slug', 'banner')->firstOrFail();
        $slides = $page->blocks[0]['data']['slides'];
        $this->assertCount(2, $slides); // empty row removed
        $this->assertSame('مبل کاناپه', $slides[0]['title']);
        $this->assertSame(6, $page->blocks[0]['data']['interval']);

        $this->get('/page/banner')->assertOk()
            ->assertSee('مبل کاناپه')->assertSee('گیاه آپارتمانی')->assertSee('motion-banner', false);
    }

    public function test_product_grid_feed_picker_parses_sources(): void
    {
        $this->assertNotNull(\App\Support\Blocks\BlockData::products(['feed' => 'featured', 'limit' => 4]));
        $this->assertNotNull(\App\Support\Blocks\BlockData::products(['feed' => 'new', 'limit' => 4]));

        $cat = \App\Models\Category::where('is_active', true)->first();
        $this->assertNotNull(\App\Support\Blocks\BlockData::products(['feed' => 'category:'.$cat->slug, 'limit' => 4]));
    }

    public function test_category_tiles_limit_is_respected(): void
    {
        $total = \App\Support\Blocks\BlockData::categories([])->count();
        $this->assertGreaterThan(1, $total);
        $this->assertCount(1, \App\Support\Blocks\BlockData::categories(['limit' => 1]));
    }

    public function test_storefront_renders_a_built_page(): void
    {
        Page::create([
            'title' => 'تست', 'slug' => 'promo', 'is_published' => true,
            'blocks' => [['type' => 'promo_banner', 'data' => ['heading' => 'فروش ویژه', 'text' => 'تخفیف', 'style' => 'dark']]],
        ]);

        $this->get('/page/promo')->assertOk()->assertSee('فروش ویژه');
    }

    public function test_home_page_renders_through_blocks(): void
    {
        // PageSeeder created an is_home page mirroring the homepage.
        $this->get('/')->assertOk()->assertSee('منتخب چیاکو');
    }

    public function test_home_is_provisioned_when_missing(): void
    {
        Page::query()->delete(); // remove the seeded home
        $this->get('/')->assertOk();
        $this->assertNotNull(Page::home()); // auto-created and editable
    }

    public function test_only_one_home_page_allowed(): void
    {
        $this->actingAs($this->admin());
        $existingHome = Page::home();
        $this->assertNotNull($existingHome);

        $this->post(route('admin.pages.store'), [
            'title' => 'خانه جدید', 'is_home' => '1', 'is_published' => '1',
            'blocks' => [['type' => 'rich_text', 'data' => ['heading' => 'خوش آمدید']]],
        ])->assertRedirect();

        $this->assertSame(1, Page::where('is_home', true)->count());
        $this->assertFalse($existingHome->fresh()->is_home);
    }
}

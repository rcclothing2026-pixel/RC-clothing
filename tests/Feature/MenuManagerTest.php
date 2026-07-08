<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_add_and_storefront_uses_header_menu(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.menus.store'), [
            'location' => 'header', 'label' => 'حراج تابستان', 'url' => '/shop?sort=price_asc',
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_items', ['location' => 'header', 'label' => 'حراج تابستان']);

        // Guest storefront shows the managed link.
        $this->get('/')->assertOk()->assertSee('حراج تابستان');
    }

    public function test_inactive_items_are_hidden(): void
    {
        MenuItem::create(['location' => 'footer_1', 'label' => 'لینک مخفی', 'url' => '/x', 'is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('لینک مخفی');
    }

    public function test_submenu_is_nested_and_rendered(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $parent = MenuItem::create(['location' => 'header', 'label' => 'پوشاک', 'url' => '/shop', 'position' => 50]);

        $this->actingAs($admin)->post(route('admin.menus.store'), [
            'location' => 'header', 'parent_id' => $parent->id, 'label' => 'مانتو', 'url' => '/shop?category=manto',
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_items', ['parent_id' => $parent->id, 'label' => 'مانتو']);

        // for() returns a one-level tree: the child hangs off the parent.
        $tree = MenuItem::for('header');
        $found = $tree->firstWhere('id', $parent->id);
        $this->assertNotNull($found);
        $this->assertTrue($found->children->contains('label', 'مانتو'));

        $this->get('/')->assertOk()->assertSee('مانتو');
    }

    public function test_current_menus_are_seeded(): void
    {
        // MenuSeeder pre-fills header (shop + categories) and the footer columns.
        $this->assertDatabaseHas('menu_items', ['location' => 'header', 'label' => 'فروشگاه']);
        $this->assertTrue(MenuItem::where('location', 'footer_1')->exists());
        $this->assertTrue(MenuItem::where('location', 'footer_2')->exists());
    }

    public function test_move_reorders_items(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        MenuItem::where('location', 'header')->delete(); // isolate from seeded items
        $a = MenuItem::create(['location' => 'header', 'label' => 'A', 'url' => '/a', 'position' => 1]);
        $b = MenuItem::create(['location' => 'header', 'label' => 'B', 'url' => '/b', 'position' => 2]);

        $this->actingAs($admin)->post(route('admin.menus.move', $b), ['dir' => 'up'])->assertRedirect();

        $this->assertTrue($b->fresh()->position < $a->fresh()->position);
    }
}

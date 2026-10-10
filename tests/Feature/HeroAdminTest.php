<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Hero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroAdminTest extends TestCase
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

    public function test_admin_hero_editor_and_preview_render(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.hero.edit'))->assertOk()->assertSee('مدیریت بنر هیرو');
        $this->get(route('admin.hero.preview'))->assertOk()->assertSee('chiaco-hero', false);
    }

    public function test_save_sanitizes_and_persists_config(): void
    {
        $this->actingAs($this->admin());

        $this->postJson(route('admin.hero.save'), ['config' => [
            'storeTitle' => 'چیاکو', 'brandLine' => 'فروشگاهِ', 'rotationSeconds' => 99, // clamped to 15
            'autoplay' => true, 'pauseOnHover' => false,
            'slides' => [[
                'headline' => "سلام\nدنیا", 'accent' => 'bogus', // -> red
                'ctaLink' => '/shop', 'heroImg' => '',
                'floaters' => [['color' => 'dark', 'link' => '#']],
                'products' => [['name' => 'کالا', 'color' => 'pink', 'link' => '/shop', 'dims' => '۱', 'no' => '۰۱', 'img' => '']],
            ]],
        ]])->assertOk()->assertJson(['ok' => true]);

        $cfg = Hero::config();
        $this->assertSame(15.0, (float) $cfg['rotationSeconds']);
        $this->assertSame('red', $cfg['slides'][0]['accent']); // bad accent coerced
        $this->assertFalse($cfg['pauseOnHover']);
    }

    public function test_product_search_returns_results(): void
    {
        $this->actingAs($this->admin());
        $res = $this->getJson(route('admin.hero.products').'?q=');
        $res->assertOk()->assertJsonStructure(['results' => [['name', 'link', 'img']]]);
    }

    public function test_hero_image_upload(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $res = $this->post(route('admin.hero.upload'), ['file' => UploadedFile::fake()->image('h.jpg', 400, 400)]);
        $res->assertOk()->assertJsonStructure(['url']);
        $this->assertStringContainsString('/storage/hero/', $res->json('url'));
    }

    public function test_reset_restores_defaults(): void
    {
        $this->actingAs($this->admin());
        Hero::save(['storeTitle' => 'X', 'slides' => [['headline' => 'a']]] + Hero::defaults());
        $this->post(route('admin.hero.reset'))->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('چیاکو', Hero::config()['storeTitle']);
    }
}

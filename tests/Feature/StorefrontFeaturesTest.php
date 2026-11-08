<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        // 'auth' middleware redirects a guest to login before the admin gate.
        $this->get('/admin')->assertRedirect();
    }

    public function test_non_admin_cannot_access_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/admin')->assertStatus(403);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('داشبورد');
    }

    public function test_footer_renders_both_enamad_and_samandehi_seals(): void
    {
        $html = view('partials.footer', ['site' => [
            'site.enamad_html' => '<a id="enamad-seal">enamad</a>',
            'site.samandehi_html' => '<a id="samandehi-seal">samandehi</a>',
        ]])->render();

        $this->assertStringContainsString('enamad-seal', $html);
        $this->assertStringContainsString('samandehi-seal', $html);
        $this->assertStringNotContainsString('به‌زودی', $html); // placeholder hidden when seals present
    }

    public function test_footer_shows_placeholder_when_no_seals(): void
    {
        // No seals configured: the trust-badge block is left out entirely.
        $html = view('partials.footer', ['site' => []])->render();
        $this->assertStringNotContainsString('enamad', $html);
        $this->assertStringNotContainsString('samandehi', $html);
    }

    public function test_user_can_toggle_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->first();

        $this->actingAs($user)->post(route('wishlist.toggle', $product))->assertRedirect();
        $this->assertTrue($user->wishlist()->where('product_id', $product->id)->exists());

        $this->actingAs($user)->post(route('wishlist.toggle', $product))->assertRedirect();
        $this->assertFalse($user->fresh()->wishlist()->where('product_id', $product->id)->exists());
    }

}

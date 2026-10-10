<?php

namespace Tests\Feature;

use App\Models\Popup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_a_popup(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.popups.store'), [
            'name' => 'تخفیف عید', 'html' => '<h3>۲۰٪ تخفیف</h3>', 'trigger' => 'delay',
            'delay' => 5, 'frequency' => 'daily', 'pages' => 'all', 'is_active' => '1',
        ])->assertRedirect(route('admin.popups.index'));

        $this->assertDatabaseHas('popups', ['name' => 'تخفیف عید', 'trigger' => 'delay', 'frequency' => 'daily']);
    }

    public function test_active_popup_is_injected_on_storefront(): void
    {
        Popup::create(['name' => 'p', 'html' => '<b>سلام پاپ‌آپ</b>', 'trigger' => 'load', 'frequency' => 'session', 'pages' => 'all', 'is_active' => true]);

        $res = $this->get('/')->assertOk();
        $res->assertSee('popups-data', false);
        $res->assertSee('سلام پاپ‌آپ', false); // serialized in the JSON payload (unicode preserved)
    }

    public function test_inactive_popup_is_not_injected(): void
    {
        Popup::create(['name' => 'p', 'html' => '<b>مخفی</b>', 'trigger' => 'load', 'frequency' => 'always', 'pages' => 'all', 'is_active' => false]);

        $this->get('/')->assertOk()->assertDontSee('مخفی', false);
    }

    public function test_html_is_escaped_against_script_breakout(): void
    {
        Popup::create(['name' => 'p', 'html' => '</script><script>alert(1)</script>', 'trigger' => 'load', 'frequency' => 'always', 'pages' => 'all', 'is_active' => true]);

        // The closing tag must be hex-escaped so it can't break out of the JSON script block.
        $this->get('/')->assertOk()->assertDontSee('</script><script>alert(1)', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEnglishSeoTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        config(['scout.driver' => 'collection']);

        return Product::create([
            'name' => 'کلاه راکت کلاب', 'slug' => 'klah-test', 'summary' => 'خلاصه فارسی',
            'description' => '<p>توضیح فارسی</p>', 'name_en' => 'Racket Club Cap',
            'summary_en' => 'English summary', 'description_en' => '<p>English body</p>',
            'price' => 2100000, 'is_active' => true,
        ]);
    }

    public function test_persian_page_has_valid_product_schema(): void
    {
        $p = $this->product();
        $html = $this->get(route('product.show', $p))->assertOk()->getContent();

        $this->assertStringContainsString('"@context":"https://schema.org","@type":"Product"', $html);
        $this->assertStringNotContainsString('$__contextArgs', $html);
        $this->assertStringContainsString('"price":21000000,"priceCurrency":"IRR"', $html);
        $this->assertStringContainsString('<meta name="description" content="خلاصه فارسی">', $html);
    }

    public function test_english_page_uses_english_copy(): void
    {
        $p = $this->product();
        $html = $this->get(route('product.show', $p).'?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Racket Club Cap | Racket Club</title>', $html);
        $this->assertStringContainsString('English body', $html);
        $this->assertStringNotContainsString('توضیح فارسی', $html);
    }

    public function test_admin_keeps_persian_values_under_english_locale(): void
    {
        $p = $this->product();
        $admin = User::factory()->create(['is_admin' => true, 'phone_verified_at' => now()]);
        $this->actingAs($admin)->withSession(['locale' => 'en']);

        $html = $this->get(route('admin.products.edit', $p))->assertOk()->getContent();
        $this->assertStringContainsString('value="کلاه راکت کلاب"', $html);
        $this->assertStringContainsString('value="Racket Club Cap"', $html);
    }

    public function test_overgrip_copy_migration_fills_new_products(): void
    {
        config(['scout.driver' => 'collection']);
        $p = Product::create(['name' => 'اورگریپ', 'slug' => 'aorgryp-rakt-klab-super-tacky-1481', 'price' => 1, 'is_active' => true]);
        (require database_path('migrations/2026_10_26_000000_seed_overgrip_and_split_product_copy.php'))->up();

        $p->refresh();
        $this->assertStringContainsString('Super Tacky', $p->getRawOriginal('name_en'));
        $this->assertStringContainsString('بسته ۱۲ عددی', $p->summary);
        $this->assertStringContainsString('<h3>نصب و نگهداری</h3>', $p->description);
    }
}

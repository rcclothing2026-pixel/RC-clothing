<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_upload_a_category_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::first();

        $this->actingAs($admin)->patch(route('admin.categories.update', $category), [
            'name' => $category->name,
            'image' => UploadedFile::fake()->image('cat.jpg', 300, 300),
        ])->assertRedirect();

        $this->assertNotNull($category->fresh()->image_path);
        $this->assertStringContainsString('/storage/categories/', $category->fresh()->image_path);
    }

    public function test_admin_can_remove_a_category_image(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::first();
        $category->update(['image_path' => '/storage/categories/x.jpg']);

        $this->actingAs($admin)->patch(route('admin.categories.update', $category), [
            'name' => $category->name,
            'remove_image' => '1',
        ])->assertRedirect();

        $this->assertNull($category->fresh()->image_path);
    }
}

<?php

use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Provision an editable "concept stores" page (slug: concept-stores) with the
 * concept_stores block pre-filled with two placeholder stores, and add it to
 * the header menu. Idempotent — never clobbers an existing page/menu item, so
 * admin edits are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        Page::firstOrCreate(
            ['slug' => 'concept-stores'],
            [
                'title' => 'کانسپت‌استورها',
                'is_published' => true,
                'is_home' => false,
                'blocks' => [
                    ['type' => 'concept_stores', 'data' => [
                        'heading' => 'کانسپت‌استورهای همکار چیاکو',
                        'subtitle' => 'فروشگاه‌های منتخبی که می‌توانید محصولات چیاکو را از نزدیک ببینید و تجربه کنید.',
                        'container' => 'default',
                        'padding' => 'md',
                        'stores' => [
                            [
                                'name' => 'نام کانسپت‌استور اول',
                                'tagline' => 'یک جملهٔ کوتاه دربارهٔ فروشگاه',
                                'city' => 'تهران',
                                'description' => '<p>اینجا معرفی کامل فروشگاه را بنویسید — داستان برند، فضای فروشگاه و آنچه آن را خاص می‌کند. این متن را از بخش «معرفی کامل» ویرایش کنید.</p>',
                                'image' => '',
                                'image2' => '',
                                'instagram' => '',
                                'website' => '',
                            ],
                            [
                                'name' => 'نام کانسپت‌استور دوم',
                                'tagline' => 'یک جملهٔ کوتاه دربارهٔ فروشگاه',
                                'city' => '',
                                'description' => '<p>معرفی کامل فروشگاه دوم را اینجا وارد کنید.</p>',
                                'image' => '',
                                'image2' => '',
                                'instagram' => '',
                                'website' => '',
                            ],
                        ],
                    ]],
                ],
            ]
        );

        if (Schema::hasTable('menu_items')) {
            $exists = MenuItem::where('location', 'header')
                ->where('url', 'like', '%/page/concept-stores')->exists();
            if (! $exists) {
                MenuItem::create([
                    'location' => 'header',
                    'label' => 'کانسپت‌استورها',
                    'url' => '/page/concept-stores',
                    'position' => (int) MenuItem::where('location', 'header')->whereNull('parent_id')->max('position') + 1,
                    'is_active' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Content, not schema — leave the page + menu item in place on rollback.
    }
};

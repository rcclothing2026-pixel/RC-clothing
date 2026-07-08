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
                'title' => 'The Club',
                'is_published' => true,
                'is_home' => false,
                'blocks' => [
                    ['type' => 'concept_stores', 'data' => [
                        'heading' => 'The Club',
                        'subtitle' => 'Selected spaces where you can see and feel Racket Club in person.',
                        'container' => 'default',
                        'padding' => 'md',
                        'stores' => [
                            [
                                'name' => 'The Flagship',
                                'tagline' => 'A short line about the space',
                                'city' => 'London',
                                'description' => '<p>Introduce the space here — the story, the atmosphere, and what makes it worth the visit. Edit this from the "Full description" field.</p>',
                                'image' => '',
                                'image2' => '',
                                'instagram' => '',
                                'website' => '',
                            ],
                            [
                                'name' => 'The Pavilion',
                                'tagline' => 'A short line about the space',
                                'city' => '',
                                'description' => '<p>Introduce the second space here.</p>',
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

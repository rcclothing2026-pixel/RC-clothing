<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Seed an editable homepage that mirrors the built-in layout, so the page
     * builder has real content to edit out of the box. Idempotent by slug.
     */
    public function run(): void
    {
        Page::updateOrCreate(['slug' => 'home'], [
            'title' => 'صفحه اصلی',
            'is_home' => true,
            'is_published' => true,
            'blocks' => Page::defaultHomeBlocks(),
        ]);

        // Built-in content / legal pages, editable in the page builder.
        Page::provisionContentPages();
    }
}

<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Add the «هدیه» link to the header nav, pointing at /gift (the new
 * interactive gift-discovery page). Idempotent — won't duplicate if a row
 * with the same location + url already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        $exists = MenuItem::query()
            ->where('location', 'header')
            ->where('url', '/gift')
            ->exists();

        if ($exists) {
            return;
        }

        // Place at the end of the header row. Admin can re-order via /admin/menus.
        $maxPosition = (int) MenuItem::query()->where('location', 'header')->max('position');

        MenuItem::create([
            'location'  => 'header',
            'parent_id' => null,
            'label'     => 'هدیه',
            'url'       => '/gift',
            'position'  => $maxPosition + 10,
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        MenuItem::query()
            ->where('location', 'header')
            ->where('url', '/gift')
            ->delete();
    }
};

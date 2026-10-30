<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

// Adds «بسته‌ها و ست‌ها» to the admin-managed header menu. Only when that menu
// already has items: an empty menu falls back to the default nav, which links
// /bundles itself, and one row here would replace that whole default.
return new class extends Migration
{
    public function up(): void
    {
        $top = MenuItem::where('location', 'header')->whereNull('parent_id');
        if (! $top->exists() || MenuItem::where('location', 'header')->where('url', '/bundles')->exists()) {
            return;
        }
        MenuItem::create(['location' => 'header', 'label' => 'بسته‌ها و ست‌ها', 'url' => '/bundles',
            'position' => (int) $top->max('position') + 1, 'is_active' => true]);
        Cache::forget('menu_items:active');
    }

    public function down(): void
    {
        MenuItem::where('location', 'header')->where('url', '/bundles')->delete();
        Cache::forget('menu_items:active');
    }
};

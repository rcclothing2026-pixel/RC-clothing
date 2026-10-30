<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

// «اوتلت» in the admin header menu, only when that menu already has items
// (an empty menu keeps the default nav, which links /outlet itself).
return new class extends Migration
{
    public function up(): void
    {
        $top = MenuItem::where('location', 'header')->whereNull('parent_id');
        if (! $top->exists() || MenuItem::where('location', 'header')->where('url', '/outlet')->exists()) {
            return;
        }
        MenuItem::create(['location' => 'header', 'label' => 'اوتلت', 'url' => '/outlet',
            'position' => (int) $top->max('position') + 1, 'is_active' => true]);
        Cache::forget('menu_items:active');
    }

    public function down(): void
    {
        MenuItem::where('location', 'header')->where('url', '/outlet')->delete();
        Cache::forget('menu_items:active');
    }
};

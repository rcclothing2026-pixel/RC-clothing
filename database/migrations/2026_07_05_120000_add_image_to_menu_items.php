<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Optional per-item image, used by the desktop mega-menu cards.
            // When empty, the mega-menu falls back to the linked category's photo.
            $table->string('image')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};

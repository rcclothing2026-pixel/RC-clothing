<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product "buy externally" mode: when external_enabled is on and
 * external_url is set, the storefront replaces add-to-cart with a link out to
 * the retailer's own page instead of selling the item on-site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('external_enabled')->default(false)->after('is_featured');
            $table->string('external_url')->nullable()->after('external_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['external_enabled', 'external_url']);
        });
    }
};

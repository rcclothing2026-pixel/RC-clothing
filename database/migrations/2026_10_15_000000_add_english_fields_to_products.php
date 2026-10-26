<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * English copy for the ?lang=en storefront. Empty fields fall back to the
 * Persian name/summary/description (see Product::localized()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'name_en')) {
            return; // already applied by the 2026-10-16 deploy
        }
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('summary_en')->nullable()->after('summary');
            $table->text('description_en')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'summary_en', 'description_en']);
        });
    }
};

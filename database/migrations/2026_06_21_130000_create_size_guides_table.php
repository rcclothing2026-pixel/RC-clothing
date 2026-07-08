<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Size guides: reusable sizing tables the admin creates and assigns to products.
 * products.size_guide_id is a website-local field (NOT touched by the StoqS
 * sync). FK-free + idempotent for shared-host safety.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('size_guides')) {
            Schema::create('size_guides', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->longText('content')->nullable(); // purified HTML (a sizing table)
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'size_guide_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('size_guide_id')->nullable()->after('collection_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'size_guide_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('size_guide_id');
            });
        }
        Schema::dropIfExists('size_guides');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A size guide can be an uploaded image (a size-chart graphic) in addition to /
 * instead of the HTML table content. Additive + idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('size_guides') && ! Schema::hasColumn('size_guides', 'image_path')) {
            Schema::table('size_guides', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('content');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('size_guides', 'image_path')) {
            Schema::table('size_guides', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};

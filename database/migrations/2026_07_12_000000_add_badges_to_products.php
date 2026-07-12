<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Per-product corner badges (up to 4: top-start/top-end/bottom-start/bottom-end).
// Website-only presentation data — the StoqS importer never touches this column,
// so badges survive catalogue syncs. Stored as JSON keyed by corner.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('badges')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('badges');
        });
    }
};

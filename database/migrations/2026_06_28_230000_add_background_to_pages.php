<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-page background: a colour and/or a full-bleed image that sits behind
 * all of the page's blocks. Both optional — empty = the default paper bg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('bg_color', 16)->nullable()->after('blocks');
            $table->string('bg_image')->nullable()->after('bg_color');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['bg_color', 'bg_image']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stockkeeping_log', function (Blueprint $table) {
            $table->string('source', 10)->nullable()->after('direction')->index();
            $table->text('summary')->nullable()->after('payload');
            $table->unsignedInteger('duration_ms')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('stockkeeping_log', function (Blueprint $table) {
            $table->dropColumn(['source', 'summary', 'duration_ms']);
        });
    }
};

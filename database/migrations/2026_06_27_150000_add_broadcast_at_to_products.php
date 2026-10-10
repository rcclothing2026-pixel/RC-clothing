<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Stamped the first time a product is broadcast to the Telegram
            // channel. Re-saves never re-broadcast.
            $table->timestamp('broadcast_at')->nullable()->after('stock_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('broadcast_at');
        });
    }
};

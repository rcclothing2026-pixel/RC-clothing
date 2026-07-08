<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Which StoqS location the stock was taken from for this order
            // (token like "warehouse:1" / "shop:3") + a human label for display.
            $table->string('stockkeeping_location')->nullable()->after('stockkeeping_sale_id');
            $table->string('stockkeeping_location_name')->nullable()->after('stockkeeping_location');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['stockkeeping_location', 'stockkeeping_location_name']);
        });
    }
};

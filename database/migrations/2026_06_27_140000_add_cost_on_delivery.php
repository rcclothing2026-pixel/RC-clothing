<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «هزینهٔ پس‌کرایه» — the customer pays the shipping fee to the courier on
 * receipt instead of through the website checkout. Marked on the shipping
 * method; snapshotted onto each order so future method edits don't rewrite
 * historical orders' display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->boolean('cost_on_delivery')->default(false)->after('free_over');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('shipping_cost_on_delivery')->default(false)->after('shipping_method_name');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn('cost_on_delivery');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_cost_on_delivery');
        });
    }
};

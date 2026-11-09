<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finished cost per product (toman), written by NJ Focus or StoqS. The cart
 * uses it so discounts never take an order below the minimum margin. Kept out
 * of `products` so the StoqS catalogue import never overwrites it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_costs')) {
            return;
        }
        Schema::create('product_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cost');
            $table->string('source', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_costs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gift / bundle products: a normal Product flagged is_bundle whose "stock" is
 * derived from a set of child variants in bundle_items. The bundle has one
 * placeholder variant of its own so cart/order/payment flow is unchanged; the
 * checkout decrement and StoqS sale report look at bundle_items to act on the
 * real child variants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_bundle')->default(false)->after('is_active')->index();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // A variant can only appear once per bundle; merge quantities on edit.
            $table->unique(['bundle_product_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_bundle');
        });
    }
};

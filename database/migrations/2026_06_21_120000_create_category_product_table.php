<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Many-to-many product↔category pivot. products.category_id stays as the PRIMARY
 * category (kept = first), so every existing query/view keeps working; this table
 * holds the full set. FK-free + idempotent (shared-host safe). The current single
 * category of every product is backfilled so nothing is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('product_id');
                $table->primary(['category_id', 'product_id']);
                $table->index('product_id');
            });
        }

        // Backfill each product's existing single category (idempotent).
        try {
            DB::statement(
                'INSERT INTO category_product (category_id, product_id)
                 SELECT p.category_id, p.id FROM products p
                 WHERE p.category_id IS NOT NULL
                   AND NOT EXISTS (
                       SELECT 1 FROM category_product cp
                        WHERE cp.product_id = p.id AND cp.category_id = p.category_id
                   )'
            );
        } catch (\Throwable $e) {
            // products table not present yet / already backfilled — ignore.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
    }
};

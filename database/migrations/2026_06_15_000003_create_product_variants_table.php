<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size')->nullable();     // e.g. S, M, L, XL, 38, 40
            $table->string('color')->nullable();    // Persian color name
            $table->string('color_hex', 9)->nullable();
            $table->string('sku')->nullable()->unique();

            // Optional per-variant price override (Toman); null => use product price.
            $table->unsignedBigInteger('price')->nullable();

            // Local mirror of stock. Authoritative quantity lives in stock-keeping;
            // this is the cached value used for fast page loads.
            $table->integer('stock_qty')->default(0);

            $table->boolean('is_active')->default(true);

            // Mapping to the stock-keeping variant/SKU.
            $table->string('stockkeeping_variant_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

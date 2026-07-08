<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                 // Persian name
            $table->string('slug')->unique();
            $table->string('summary')->nullable();  // short tagline
            $table->longText('description')->nullable();

            // Prices stored as integer Toman (no fractional Toman in practice).
            $table->unsignedBigInteger('price');            // current sale price (Toman)
            $table->unsignedBigInteger('compare_at_price')->nullable(); // original price for discount display

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            // Mapping to the Chiaco Stock-Keeping POS (system of record).
            $table->string('stockkeeping_id')->nullable()->index();
            $table->timestamp('stock_synced_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

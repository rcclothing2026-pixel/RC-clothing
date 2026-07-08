<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();          // human-friendly order number
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // pending | paid | failed | canceled | processing | shipped | delivered
            $table->string('status')->default('pending')->index();

            $table->unsignedBigInteger('subtotal');       // Toman
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');

            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shipping_method_name')->nullable();

            // Snapshot of the delivery address at purchase time.
            $table->json('shipping_address')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();

            // Reference returned by the stock-keeping SaaS once the sale is reported.
            $table->string('stockkeeping_sale_id')->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Instagram «لینک خرید»: an admin builds a cart on /admin/sell and sends the
// customer a short /b/{code} link that fills their cart. New table only —
// orders and the normal checkout are untouched (a link just points at the
// order it led to).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_links', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('items'); // [{variant_id, qty}]
            $table->string('coupon_code')->nullable();
            $table->string('customer_handle', 100)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_links');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('type', 16)->default('percent');     // percent | fixed | free_shipping
            $table->unsignedBigInteger('value')->default(0);     // percent (1-100) or Toman

            // Eligibility rules (all optional / combinable)
            $table->unsignedBigInteger('min_subtotal')->default(0);   // price range — floor
            $table->unsignedBigInteger('max_subtotal')->nullable();   // price range — ceiling
            $table->unsignedBigInteger('max_discount')->nullable();   // cap (for percent)
            $table->unsignedInteger('usage_limit')->nullable();       // total redemptions; null = ∞
            $table->unsignedInteger('per_user_limit')->nullable();    // per-customer; null = ∞
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('first_order_only')->default(false);      // new customers only
            $table->timestamp('starts_at')->nullable();               // date window
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code')->nullable()->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('coupon_code');
        });
        Schema::dropIfExists('coupons');
    }
};

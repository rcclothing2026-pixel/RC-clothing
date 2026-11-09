<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// «Use alone»: a coupon or discount rule marked exclusive never adds up with
// other discounts. When it meets them, the cart keeps whichever is larger.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['coupons', 'discount_rules'] as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'exclusive')) {
                Schema::table($t, fn (Blueprint $b) => $b->boolean('exclusive')->default(false));
            }
        }
    }

    public function down(): void
    {
        foreach (['coupons', 'discount_rules'] as $t) {
            if (Schema::hasColumn($t, 'exclusive')) {
                Schema::table($t, fn (Blueprint $b) => $b->dropColumn('exclusive'));
            }
        }
    }
};

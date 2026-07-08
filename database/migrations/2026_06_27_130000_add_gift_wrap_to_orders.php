<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gift-wrap option on every order:
 *  · gift_wrap        — yes/no
 *  · gift_wrap_price  — Toman snapshot at order time (so price changes don't
 *                       rewrite history). 0 when «free over threshold» kicked in.
 *  · gift_message     — text the customer wants printed on the card. Nullable.
 *
 * Defaults make every legacy order read as unwrapped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('gift_wrap')->default(false)->after('customer_note');
            $table->unsignedInteger('gift_wrap_price')->default(0)->after('gift_wrap');
            $table->text('gift_message')->nullable()->after('gift_wrap_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['gift_wrap', 'gift_wrap_price', 'gift_message']);
        });
    }
};

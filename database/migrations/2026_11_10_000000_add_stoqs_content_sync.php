<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content both ways with StoqS (App\Services\StockKeeping\StoqsContent):
 *  - products.stoqs_sync_mode   auto | stock_only | manual | ignore
 *  - products.stoqs_meta        per StoqS product: content_updated_at, the last synced
 *                               text/base price (for if_match + the 3-way merge), its
 *                               variant ids and photo ids; plus the last conflict note
 *  - products.stoqs_floor_price StoqS floor_price (used when there is no local cost)
 *  - product_images.stoqs_*     which StoqS photo a gallery image is
 *  - coupons.stoqs_code_id      the code registered in StoqS (/discounts/codes)
 *  - order_items.discounts      what the cart gave this line, for the sale report
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('stoqs_sync_mode', 12)->default('auto');
            $table->json('stoqs_meta')->nullable();
            $table->unsignedBigInteger('stoqs_floor_price')->nullable();
        });
        Schema::table('product_images', function (Blueprint $table) {
            $table->unsignedBigInteger('stoqs_media_id')->nullable()->index();
            $table->string('stoqs_sha256', 64)->nullable();
        });
        Schema::table('coupons', function (Blueprint $table) {
            $table->unsignedBigInteger('stoqs_code_id')->nullable();
            $table->string('stoqs_error')->nullable();
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('discounts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['stoqs_sync_mode', 'stoqs_meta', 'stoqs_floor_price']));
        Schema::table('product_images', function (Blueprint $t) {
            $t->dropIndex(['stoqs_media_id']);
            $t->dropColumn(['stoqs_media_id', 'stoqs_sha256']);
        });
        Schema::table('coupons', fn (Blueprint $t) => $t->dropColumn(['stoqs_code_id', 'stoqs_error']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('discounts'));
    }
};

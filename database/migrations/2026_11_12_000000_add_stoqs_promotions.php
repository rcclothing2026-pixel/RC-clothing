<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * StoqS-managed promotions (campaign planner, Promotions API v2).
 *
 * - stoqs_promotions: one row (id 1), the last GET /promotions for this site: whether StoqS
 *   manages its promotions, the version and ETag, the payload, and when it was applied/acked.
 * - product_variants.promo_price: a size's current StoqS window price when it differs from
 *   the product's shown price; site_price_reported: the shop-set size price last sent with
 *   PUT /catalog/variants/{id}/site-price.
 * - orders.promo_eval (stoqs|local) / promo_version: how a StoqS-managed order was priced;
 *   null for every order the shop prices itself.
 * - order_items.list_price: the list price a StoqS-managed line was priced from.
 *
 * All nullable and unused while the site runs its own promotions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stoqs_promotions')) {
            Schema::create('stoqs_promotions', function (Blueprint $t) {
                $t->id();
                $t->boolean('managed')->default(false);
                $t->string('promo_source', 20)->nullable();
                $t->string('price_level', 10)->nullable();
                $t->unsignedBigInteger('version')->nullable();
                $t->string('etag', 100)->nullable();
                $t->longText('payload')->nullable();
                $t->timestamp('pulled_at')->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->unsignedBigInteger('acked_version')->nullable();
                $t->timestamp('acked_at')->nullable();
                $t->string('error', 500)->nullable();
                $t->timestamps();
            });
        }
        Schema::table('product_variants', function (Blueprint $t) {
            if (! Schema::hasColumn('product_variants', 'promo_price')) {
                $t->unsignedBigInteger('promo_price')->nullable();
            }
            if (! Schema::hasColumn('product_variants', 'site_price_reported')) {
                $t->unsignedBigInteger('site_price_reported')->nullable();
            }
        });
        Schema::table('orders', function (Blueprint $t) {
            if (! Schema::hasColumn('orders', 'promo_eval')) {
                $t->string('promo_eval', 10)->nullable();
            }
            if (! Schema::hasColumn('orders', 'promo_version')) {
                $t->unsignedBigInteger('promo_version')->nullable();
            }
        });
        Schema::table('order_items', function (Blueprint $t) {
            if (! Schema::hasColumn('order_items', 'list_price')) {
                $t->unsignedBigInteger('list_price')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach (['order_items' => ['list_price'], 'orders' => ['promo_eval', 'promo_version'], 'product_variants' => ['promo_price', 'site_price_reported']] as $table => $cols) {
            foreach ($cols as $c) {
                if (Schema::hasColumn($table, $c)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
        Schema::dropIfExists('stoqs_promotions');
    }
};

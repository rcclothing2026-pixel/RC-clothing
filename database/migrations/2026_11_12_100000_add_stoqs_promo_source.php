<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way site manager switch (campaign planner, part 3): who manages this site's promotions,
 * «مدیریت با فروشگاه» or «مدیریت با StoqS», is StoqS's state with a monotonic sequence number.
 *
 * - stoqs_promotions.promo_source_seq / _changed_at / _by / _note: the last switch this site applied
 *   (a mirror of StoqS's promo_source_seq); promo_source_ack_pending: the seq was applied but
 *   StoqS has not been told yet (POST /promotions/ack {source_seq}); the minute job retries.
 * - stoqs_promo_source_log: every switch the site applied (who asked, from where).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stoqs_promotions')) {
            Schema::table('stoqs_promotions', function (Blueprint $t) {
                if (! Schema::hasColumn('stoqs_promotions', 'promo_source_seq')) {
                    $t->unsignedBigInteger('promo_source_seq')->default(0);
                    $t->timestamp('promo_source_changed_at')->nullable();
                    $t->string('promo_source_by', 80)->nullable();
                    $t->string('promo_source_note', 200)->nullable();
                    $t->boolean('promo_source_ack_pending')->default(false);
                }
            });
        }
        if (! Schema::hasTable('stoqs_promo_source_log')) {
            Schema::create('stoqs_promo_source_log', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('seq');
                $t->string('from_source', 20)->nullable();
                $t->string('to_source', 20);
                $t->string('origin', 20);   // shop_admin | stoqs_panel | stoqs_api | pull
                $t->string('by_name', 80)->nullable();
                $t->string('note', 200)->nullable();
                $t->boolean('confirmed')->default(true);
                $t->timestamp('created_at')->nullable();
                $t->index('seq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stoqs_promo_source_log');
        if (Schema::hasTable('stoqs_promotions')) {
            foreach (['promo_source_seq', 'promo_source_changed_at', 'promo_source_by', 'promo_source_note', 'promo_source_ack_pending'] as $c) {
                if (Schema::hasColumn('stoqs_promotions', $c)) {
                    Schema::table('stoqs_promotions', fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
    }
};

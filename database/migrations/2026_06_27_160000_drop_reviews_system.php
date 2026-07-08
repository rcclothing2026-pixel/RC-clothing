<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review system removed by product decision. Drops the reviews table and the
 * orders.review_reminded_at column on prod; safe to re-run (guards each).
 * Re-creating later would require a fresh migration — old create migrations
 * for these were deleted from the repo on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reviews')) {
            Schema::drop('reviews');
        }
        if (Schema::hasColumn('orders', 'review_reminded_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('review_reminded_at');
            });
        }
    }

    public function down(): void
    {
        // Removal is intentional and one-way. No re-create on rollback.
    }
};

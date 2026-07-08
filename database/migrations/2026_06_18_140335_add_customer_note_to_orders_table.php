<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // NB: no ->after(): the old 'review_reminded_at' column (reviews
            // system, since removed) doesn't exist on fresh installs, so an
            // AFTER clause referencing it breaks a clean migrate. Column order
            // is cosmetic; append it.
            $table->text('customer_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_note');
        });
    }
};

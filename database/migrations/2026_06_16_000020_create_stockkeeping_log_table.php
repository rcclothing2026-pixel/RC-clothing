<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stockkeeping_log', function (Blueprint $table) {
            $table->id();
            // sale_push | cancel_restock | return_restock | webhook_in | stock_pull
            $table->string('event_type', 40)->index();
            $table->string('direction', 4); // out | in
            $table->string('ref', 120)->nullable()->index(); // CXL-1234, RET-5, order_number …
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable(); // items array + context
            $table->string('status', 10)->default('ok')->index(); // ok | failed
            $table->string('remote_id', 120)->nullable(); // StoqS batch_id returned
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stockkeeping_log');
    }
};

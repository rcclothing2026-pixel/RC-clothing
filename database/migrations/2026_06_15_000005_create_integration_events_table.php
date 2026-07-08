<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox for reliably reporting to the Chiaco Stock-Keeping SaaS.
 * Every sale / income / customer (CRM) event is recorded here first, then
 * pushed to stock-keeping by a queued worker with retries, so the storefront
 * never blocks on (or loses data to) the external POS being unavailable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            // e.g. sale.created, income.recorded, customer.upserted, stock.requested
            $table->string('type')->index();
            $table->json('payload');
            $table->string('status')->default('pending')->index(); // pending|sent|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('remote_id')->nullable(); // id returned by stock-keeping
            $table->timestamp('available_at')->nullable(); // for backoff scheduling
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
    }
};

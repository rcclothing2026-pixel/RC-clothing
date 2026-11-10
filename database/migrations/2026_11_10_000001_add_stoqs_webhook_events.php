<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * StoqS webhooks already applied (StoqsWebhookController), kept 8 days:
 *  - event_id     the envelope's id: claimed (subject '') before the event is applied,
 *                 so a repeat or overlapping delivery hits the unique key and is skipped
 *  - subject      what it changed («p:<StoqS product id>», «s:<barcode>»)
 *  - occurred_at  when StoqS made the change: an older event for a subject is skipped
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stoqs_webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 64);
            $t->string('subject', 120)->default('')->index();
            $t->unique(['event_id', 'subject']);
            $t->timestamp('occurred_at')->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stoqs_webhook_events');
    }
};

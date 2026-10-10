<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // welcome, abandoned_cart, review_request, campaign, order_confirmation
            $table->string('recipient_email');
            $table->string('subject');
            $table->string('status'); // sent, failed
            $table->text('error')->nullable();
            $table->nullableMorphs('relatable'); // order, subscriber, user, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

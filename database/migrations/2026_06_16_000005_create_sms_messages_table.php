<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 40);
            $table->text('body')->nullable();          // rendered text / args summary
            $table->string('type', 30)->default('campaign'); // otp | order | campaign
            $table->string('status', 20)->default('sent');   // sent | failed
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};

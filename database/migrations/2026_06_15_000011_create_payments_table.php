<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('zarinpal');
            $table->unsignedBigInteger('amount');          // Toman (gateway charged in Rial)

            // ZarinPal handshake fields
            $table->string('authority')->nullable()->index();
            $table->string('ref_id')->nullable();          // transaction id on success
            $table->string('card_pan')->nullable();

            // pending | paid | failed | canceled
            $table->string('status')->default('pending')->index();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

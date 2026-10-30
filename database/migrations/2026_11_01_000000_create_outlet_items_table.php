<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Outlet: products the admin marks down by a percentage. Kept apart from
// products because the StoqS catalog sync rewrites product prices hourly;
// the sync re-applies these after setting the StoqS price.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_items');
    }
};

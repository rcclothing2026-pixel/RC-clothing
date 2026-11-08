<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The campaign plan as edited in NJ Focus (one row); config/campaigns.php is the default.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_plans', function (Blueprint $table) {
            $table->id();
            $table->longText('plan');
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_plans');
    }
};

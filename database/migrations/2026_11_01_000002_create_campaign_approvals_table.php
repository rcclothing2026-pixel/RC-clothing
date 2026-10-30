<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Campaign approvals pushed from NJ Focus: a campaign in config/campaigns.php
// runs only while it has an approved row here, inside these dates.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('approved')->default(false);
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_approvals');
    }
};

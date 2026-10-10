<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('html')->nullable();
            $table->string('trigger')->default('load');   // load | delay | exit | scroll
            $table->unsignedInteger('delay')->default(3);  // seconds (for delay trigger)
            $table->string('frequency')->default('session'); // always | session | daily | once
            $table->string('pages')->default('all');       // all | home
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};

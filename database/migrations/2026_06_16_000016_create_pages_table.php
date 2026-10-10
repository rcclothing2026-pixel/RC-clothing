<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->json('blocks')->nullable();      // ordered array of {type,data}
            $table->boolean('is_published')->default(true);
            $table->boolean('is_home')->default(false);
            $table->boolean('show_in_nav')->default(false);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};

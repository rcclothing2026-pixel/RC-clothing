<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

// The first deploy of the campaign build ran its migrations with the previous
// release's cached config, so they saw an empty plan. Re-run them with the
// config files read straight from disk.
return new class extends Migration
{
    public function up(): void
    {
        config(['shop' => require config_path('shop.php'), 'campaigns' => require config_path('campaigns.php')]);
        Artisan::call('campaigns:sync');
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};

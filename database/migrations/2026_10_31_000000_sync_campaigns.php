<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

// Places the campaign plan (config/campaigns.php) on deploy; the scheduler
// re-runs campaigns:sync daily so bundle prices follow StoqS prices.
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('campaigns:sync');
    }

    public function down(): void
    {
        // Campaign rules can be switched off in the admin; nothing to undo.
    }
};

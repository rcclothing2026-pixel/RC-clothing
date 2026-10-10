<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Provision the editable gift landing page at /page/gift with a starter
 * multi-block layout (hero + collection scroller + product grid + features).
 * Create-if-missing so a re-run never clobbers admin edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages')) {
            Page::provisionGiftPage();
        }
    }

    public function down(): void
    {
        // No-op: admins may have customised the page; we don't auto-delete.
    }
};

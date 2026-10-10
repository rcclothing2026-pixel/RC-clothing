<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Provision the built-in content / legal pages (about, FAQ, terms, privacy,
 * shipping & returns) as editable page-builder rows. Before this they were
 * hard-coded blade views; now the admin can edit them fully as HTML under
 * صفحه‌ها. Create-if-missing only, so a re-run never clobbers admin edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages')) {
            Page::provisionContentPages();
        }
    }

    public function down(): void
    {
        // Reversible: drop only the auto-provisioned pages that are untouched is
        // unknowable, so we leave them in place (the static blade views remain as
        // a fallback only when no DB page exists).
    }
};

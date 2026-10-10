<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-sided Telegram bot identity table. Each row is a Telegram private chat
 * the bot knows about, tagged with a role:
 *   - admin    → receives order/ops alerts and may change order status
 *   - customer → linked to a website account, receives their own order updates
 *   - pending  → /started the bot but not yet assigned a role
 *
 * Existing rows in the legacy `telegram_subscribers` table (anonymous admin
 * broadcast list) are imported as admins so live admins keep their alerts.
 *
 * Idempotent and FK-free on purpose: no DB-level foreign key (the app sets
 * user_id null on unlink), so it can't fail on shared MySQL/MariaDB hosts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_chats')) {
            Schema::create('telegram_chats', function (Blueprint $table) {
                $table->id();
                $table->string('chat_id', 40)->unique();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('role', 20)->default('pending')->index(); // admin | customer | pending
                $table->string('first_name')->nullable();
                $table->string('username')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('linked_at')->nullable();
                $table->timestamps();
            });
        }

        // Carry over the current anonymous subscribers as admins (idempotent).
        if (Schema::hasTable('telegram_subscribers') && Schema::hasTable('telegram_chats')) {
            foreach (DB::table('telegram_subscribers')->get() as $sub) {
                DB::table('telegram_chats')->updateOrInsert(
                    ['chat_id' => (string) $sub->chat_id],
                    [
                        'role' => 'admin',
                        'first_name' => $sub->first_name ?? null,
                        'username' => $sub->username ?? null,
                        'is_active' => (bool) ($sub->is_active ?? true),
                        'linked_at' => now(),
                        'updated_at' => now(),
                        'created_at' => $sub->created_at ?? now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');
    }
};

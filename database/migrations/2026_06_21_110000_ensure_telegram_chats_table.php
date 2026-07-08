<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net: the original create_telegram_chats migration used a foreign-key
 * constraint that can fail on some shared MySQL/MariaDB hosts, leaving the
 * table missing while the app expects it. This new migration (new timestamp,
 * so it always runs once) creates the table if it isn't there — FK-free and
 * idempotent — and imports the legacy subscriber admins.
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
                $table->string('role', 20)->default('pending')->index();
                $table->string('first_name')->nullable();
                $table->string('username')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('linked_at')->nullable();
                $table->timestamps();
            });

            if (Schema::hasTable('telegram_subscribers')) {
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
    }

    public function down(): void
    {
        // Leave the table in place; the original migration owns its lifecycle.
    }
};

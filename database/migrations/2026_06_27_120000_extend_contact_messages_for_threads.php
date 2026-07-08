<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend contact_messages so every row belongs to a conversation, regardless
 * of source. New columns:
 *  · telegram_chat_id — the bot's chat with this user (null for non-Telegram)
 *  · source           — telegram | form | phone (form kept for legacy rows)
 *  · direction        — in (customer→admin) | out (admin→customer)
 *  · admin_user_id    — which admin replied (out rows only)
 *
 * Thread grouping in the UI uses telegram_chat_id when set, else phone, else
 * email — so old form submissions keep grouping by sender. No data loss; the
 * defaults make every existing row valid (source=form, direction=in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('telegram_chat_id', 32)->nullable()->after('email')->index();
            $table->string('source', 16)->default('form')->after('telegram_chat_id')->index();
            $table->string('direction', 4)->default('in')->after('source');
            $table->foreignId('admin_user_id')->nullable()->after('direction')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropForeign(['admin_user_id']);
            $table->dropColumn(['telegram_chat_id', 'source', 'direction', 'admin_user_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Photo attachments for the Telegram support system.
//  · photo         → a chiiaco-hosted image URL (admin uploads out; best-effort
//                    download of inbound customer photos for web-inbox display).
//  · photo_file_id → the Telegram file_id, so an inbound customer photo can be
//                    re-sent to admins (and outbound to the customer) reliably
//                    via the bot without needing to fetch the bytes first.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('message');
            $table->string('photo_file_id')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn(['photo', 'photo_file_id']);
        });
    }
};

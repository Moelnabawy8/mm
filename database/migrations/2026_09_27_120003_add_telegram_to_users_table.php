<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram chat id per user, managed by the company admin only.
 *
 * NOTE: `telegram_chat_id` is deliberately NOT added to User::$fillable —
 * same rule as `whatsapp` (see 2026_07_25_050001): UserController@editprofile
 * uses fill($input)->save(), which would let an employee point someone else's
 * notifications at their own chat. Written explicitly in the admin-controlled
 * paths (TelegramController, telegram:dispatch) instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telegram_chat_id')) {
                $table->string('telegram_chat_id', 32)->nullable()->after('whatsapp_status')
                    ->comment('Telegram numeric chat id, linked via /start token');
            }
            if (!Schema::hasColumn('users', 'telegram_status')) {
                $table->enum('telegram_status', ['unset', 'valid', 'invalid'])
                    ->default('unset')->after('telegram_chat_id')
                    ->comment('Driven by real send results; invalid = blocked/deleted chat');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_status']);
        });
    }
};

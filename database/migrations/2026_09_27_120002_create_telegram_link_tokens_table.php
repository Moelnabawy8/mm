<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time, short-lived tokens that bind a Telegram chat to an ERP user.
 *
 * Flow: the admin/employee opens the Telegram linking page, the app mints a
 * 32-char random token (fits Telegram's 64-char `start` payload limit), shows
 * https://t.me/{bot}?start={token}. The employee taps START in Telegram, and
 * `telegram:dispatch` picks the /start update up via getUpdates, links the
 * chat_id to the token's user, and marks the token used.
 *
 * Random + expiring + single-use beats an encrypted user_id: it cannot be
 * guessed, cannot be replayed, and reveals nothing if leaked in a screenshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('chat_id', 32)->nullable()->comment('chat that consumed the token');
            $table->integer('created_by')->default(0)->comment('company id, NOT Auth::id()');
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_tokens');
    }
};

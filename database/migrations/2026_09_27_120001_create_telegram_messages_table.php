<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox + audit trail for Telegram task notifications.
 *
 * Deliberately a SEPARATE table from `whatsapp_messages` (not a `channel` column):
 * the /log page, the permissions and the per-channel filters can be reused 1:1
 * without touching the WhatsApp logic. Same dual role: delivery queue claimed by
 * `telegram:dispatch` and tracking log shown to the admin. No Laravel queue — the
 * `jobs` table name is taken by the HRM recruitment module.
 *
 * Differences from whatsapp_messages: `chat_id` instead of `phone`, no
 * template_name/language (Telegram sends plain HTML, no templates), plus
 * `body_html` (exact text sent to Bot API).
 *
 * `delivered_at` / `read_at` normally stay NULL: the Bot API offers no delivery
 * receipts (unlike Meta's webhook), so rows go pending -> sending -> sent/failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->comment('recipient');
            $table->string('chat_id', 32)->nullable()->comment('Telegram chat id snapshot at enqueue time');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();

            $table->string('event_type', 32)->comment('assign|stage|comment|due|test');
            $table->text('body_html')->nullable()->comment('exact HTML text sent to Bot API');
            $table->json('payload')->nullable()->comment('extra context (params, app url)');
            $table->text('body_preview')->nullable()->comment('plain-text one-liner for the log UI');

            $table->enum('status', [
                'pending', 'sending', 'sent', 'delivered', 'read', 'failed', 'cancelled',
            ])->default('pending');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('duplicate_hits')->default(0)
                ->comment('times this event re-fired and was deduped away');
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('claim_token', 40)->nullable();

            $table->string('provider_message_id', 128)->nullable()->comment('Bot API message_id');
            $table->integer('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->integer('created_by')->default(0)->comment('company id, NOT Auth::id()');
            $table->string('dedupe_key', 191)->nullable()->unique();

            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
            $table->index('task_id');
            $table->index(['user_id', 'created_at']);
            $table->index(['created_by', 'created_at']);
            $table->index('claim_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};

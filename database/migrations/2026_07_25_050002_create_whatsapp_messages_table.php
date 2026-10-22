<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox + audit trail for WhatsApp task notifications.
 *
 * This one table is both the delivery queue (claimed by `whatsapp:dispatch`) and the
 * tracking log shown to the admin. It deliberately does NOT use Laravel's queue: the
 * `jobs` table name is already taken by the HRM recruitment module.
 *
 * `delivered_at` / `read_at` and the matching statuses stay NULL in phase 1 — capturing
 * them needs a Meta webhook, and this app's Meta app already points its single
 * whatsapp_business_account callback at maya-os.com.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->comment('recipient');
            $table->string('phone', 32)->nullable()->comment('E.164 snapshot at enqueue time');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();

            $table->string('event_type', 32)->comment('assign|stage|comment|due|test');
            $table->string('template_name', 64);
            $table->string('language', 8)->default('ar');

            $table->json('payload')->nullable()->comment('exact JSON POSTed to Graph');
            $table->text('body_preview')->nullable()->comment('rendered text for the log UI');

            $table->enum('status', [
                'pending', 'sending', 'sent', 'delivered', 'read', 'failed', 'cancelled',
            ])->default('pending');

            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('duplicate_hits')->default(0)
                ->comment('times this event re-fired and was deduped away');
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('claim_token', 40)->nullable();

            $table->string('provider_message_id', 128)->nullable()->comment('wamid');
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
        Schema::dropIfExists('whatsapp_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * task_events — append-only audit log of state transitions.
 * Every start / pause / resume / stop / heartbeat creates one row here.
 * Used to:
 *   1. Replay a session timeline for HR investigations.
 *   2. Detect anomalies (e.g. heartbeat gaps) without scanning task_logs.
 *
 * NOTE: heartbeat events are HIGH FREQUENCY (1 row / 60 s / active user).
 * For 50 active employees over an 8-hour day = ~24,000 rows/day. Plan to
 * roll up + prune via scheduler (delete heartbeats older than 30 days).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_events')) return;

        Schema::create('task_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('task_log_id')->nullable()->comment('FK to task_logs.id');
            $table->unsignedBigInteger('task_id')->comment('FK to project_tasks.id (denormalised for fast filter)');
            $table->unsignedBigInteger('user_id');
            $table->enum('event_type', ['start', 'pause', 'resume', 'stop', 'heartbeat']);
            $table->timestamp('occurred_at')->useCurrent();
            $table->json('meta')->nullable()->comment('Optional context: idle_reason, ip, user_agent etc.');

            // Bursty inserts; only need timestamp index for time-range queries
            $table->index(['task_log_id', 'occurred_at']);
            $table->index(['user_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_events');
    }
};

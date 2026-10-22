<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * task_logs — one row per work session.
 * A session opens when the employee starts a timer for a task and closes
 * when they hit Stop. While active, end_time is NULL and active_seconds
 * grows with each heartbeat tick (incremented in TaskTimerService).
 *
 * Status field semantics:
 *   active   — timer running, page heartbeating
 *   paused   — auto-paused after idle threshold OR manual pause
 *   stopped  — finalized, end_time set, totals frozen
 *
 * INVARIANT: at most one row per user with status IN ('active','paused')
 * (enforced in TaskTimerService::startTimer + DB unique index below).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_logs')) return;

        Schema::create('task_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('task_id')->comment('FK to project_tasks.id');
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->enum('status', ['active', 'paused', 'stopped'])->default('active');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();

            // Fast lookup of "what's the user currently working on"
            $table->index(['user_id', 'status']);
            // Fast lookup of "all sessions for a task" (for project reporting)
            $table->index('task_id');
            // Used by scheduler to find stale active timers needing auto-pause
            $table->index('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_logs');
    }
};

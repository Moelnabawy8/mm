<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bottleneck_alerts — detected operational issues, written by the
 * BottleneckDetector service every 5 minutes via scheduler.
 *
 * Detection rules (default thresholds, configurable in settings):
 *   stage_stuck:    > 10 tasks parked in same stage_id    → severity by ratio
 *   task_delay:     elapsed_hrs > 1.5 * estimated_hrs     → severity by ratio
 *   wip_overload:   user has > 5 simultaneously assigned  → severity by ratio
 *
 * `reference_type` + `reference_id` is a soft polymorphic pointer to the
 * underlying entity (stage / task / user). We don't use Laravel's
 * morph relations because the targets live in different tables we don't
 * own (project_tasks, task_stages, users) — direct integer FK is simpler.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bottleneck_alerts')) return;

        Schema::create('bottleneck_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('type', ['stage_stuck', 'task_delay', 'wip_overload']);
            $table->string('reference_type', 32)->comment('"stage" | "task" | "user"');
            $table->unsignedBigInteger('reference_id');
            $table->enum('severity', ['low', 'medium', 'high'])->default('low');
            $table->text('message');
            $table->timestamp('resolved_at')->nullable()
                  ->comment('When the underlying condition cleared. NULL = active alert.');
            $table->timestamps();

            // Most queries: "show me unresolved alerts ordered by severity"
            $table->index(['resolved_at', 'severity']);
            // Dedupe key: avoid creating a new row if same alert is already active
            $table->index(['type', 'reference_type', 'reference_id', 'resolved_at'], 'alert_dedup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bottleneck_alerts');
    }
};

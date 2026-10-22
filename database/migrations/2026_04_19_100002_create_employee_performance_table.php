<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_performance — daily performance snapshot per user.
 * Computed nightly by `php artisan activity:compute-performance` for the
 * previous calendar day. Each row is a frozen score; trends are read by
 * grouping over a date range.
 *
 * Score formula (PerformanceService::calculateScore):
 *   productivity     = active_time / total_time
 *   task_completion  = completed_tasks / total_tasks
 *   idle_ratio       = idle_time / total_time
 *   on_time          = completed_on_time / completed_tasks
 *   score = (productivity * 0.4) + (task_completion * 0.3)
 *         + ((1 - idle_ratio) * 0.2) + (on_time * 0.1)
 *   stars = round(score * 5)   // 0..5
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_performance')) return;

        Schema::create('employee_performance', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->date('date');

            // Raw inputs (for transparency, debugging, and recomputation)
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);
            $table->unsignedSmallInteger('total_tasks')->default(0);
            $table->unsignedSmallInteger('completed_tasks')->default(0);
            $table->unsignedSmallInteger('completed_on_time')->default(0);

            // Derived metrics (0..1 each)
            $table->decimal('productivity',    5, 4)->default(0);
            $table->decimal('task_completion', 5, 4)->default(0);
            $table->decimal('idle_ratio',      5, 4)->default(0);
            $table->decimal('on_time',         5, 4)->default(0);

            // Final composite (0..1) and 0..5 star rating
            $table->decimal('score', 5, 4)->default(0);
            $table->unsignedTinyInteger('stars')->default(0);

            $table->timestamp('computed_at')->useCurrent();
            $table->timestamps();

            // Idempotent: re-running the scheduler for the same day overwrites cleanly
            $table->unique(['user_id', 'date'], 'emp_perf_user_date_unique');
            // For "team performance over date range" queries
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_performance');
    }
};

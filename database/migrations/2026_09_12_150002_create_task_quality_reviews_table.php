<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One quality review per completed task.
 *
 * The reviewable unit is the task, not the milestone: only 152 of 3,047 tasks are
 * attached to a milestone and only 31 of 346 projects have one, so milestones are
 * decorative in this install and a milestone-keyed review would cover 5% of the work.
 *
 * department_id and project_id are copied in at review time rather than joined on
 * demand. A task can be moved between projects and a project reassigned to another
 * department; a score already given belongs to the department that gave it, and the
 * per-department report must not silently rewrite history when somebody re-files a
 * project. It also keeps the reporting queries off a three-table join.
 *
 * There is no "pending" row. A task awaiting review is simply a completed task in a
 * department with no row here — storing pending rows would mean writing one for every
 * task that ever completes and keeping them in step with task edits.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('task_quality_reviews')) {
            return;
        }

        Schema::create('task_quality_reviews', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_task_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('project_id')->default(0);

            $table->unsignedBigInteger('reviewer_id');
            $table->text('assignee_ids')->nullable();   // task.assign_to is a comma list

            // {criterion_id: score}. Criteria change over time; the scores keep their
            // own copy of what was judged so an old review stays readable after a
            // criterion is renamed or retired.
            $table->json('scores')->nullable();
            $table->json('criteria_snapshot')->nullable();

            // 0-100, weighted. Stored so reports do not recompute it per row.
            $table->decimal('total_score', 5, 2)->default(0);

            $table->boolean('was_on_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamps();

            // One review per task — the unique index is what makes the "pending"
            // query (completed tasks with no review) correct and cheap.
            $table->unique('project_task_id');
            $table->index(['department_id', 'reviewed_at']);
            $table->index('reviewer_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_quality_reviews');
    }
};

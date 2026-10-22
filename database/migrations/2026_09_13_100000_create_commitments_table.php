<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commitments — one table behind both "commitments toward supervisors" (task 2) and
 * "weekly commitments per department/employee" (task 3).
 *
 * They are the same object. A commitment is something somebody owes somebody else by a
 * date. Task 3 only adds a cadence and a week. Building them separately would have
 * produced two near-identical modules whose reports disagree.
 *
 * WHY owed_to_id LIVES HERE AND NOT ON employees:
 * This install models no reporting line at all — no supervisor_id, manager_id or
 * reports_to on any table, and the four designations containing the word "Supervisor"
 * hold one employee each, so job titles cannot stand in for one. Rather than ask the
 * company to draw a 68-person org chart before a single commitment can be recorded,
 * each commitment names who it is owed to. A supervisor's view is then simply
 * "commitments where owed_to_id = me", which is the report task 2 actually asks for.
 *
 * user_id XOR department_id carries the owner: an employee owes it, or a department
 * does. MariaDB 10.4 will not enforce that reliably as a CHECK, so the model and
 * controller enforce it and this migration leaves both nullable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('commitments')) {
            return;
        }

        Schema::create('commitments', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            // Exactly one of these is set. Both nullable — see the class comment.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();

            // Who it is owed to. This is the whole of task 2.
            $table->unsignedBigInteger('owed_to_id');

            $table->enum('cadence', ['once', 'weekly'])->default('once');

            $table->date('due_date')->nullable();

            // Monday of the week a weekly commitment belongs to. Stored rather than
            // derived so a week's list is one indexed lookup, and so moving a
            // commitment between weeks is an edit rather than a date reinterpretation.
            $table->date('week_start')->nullable();

            $table->enum('status', ['open', 'done', 'missed', 'cancelled'])->default('open');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();

            // A weekly commitment that should reappear next week. The roll-forward
            // command clones it; recurs_from_id points back at the original so a series
            // can be read as one thing.
            $table->boolean('recurs')->default(false);
            $table->unsignedBigInteger('recurs_from_id')->nullable();

            // Set when the commitment came out of a meeting's minutes (task 4), so a
            // decision can be traced from the meeting that made it to the work it caused.
            $table->unsignedBigInteger('meeting_id')->nullable();

            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamps();

            $table->index(['owed_to_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['department_id', 'week_start']);
            $table->index(['status', 'due_date']);
            $table->index(['cadence', 'week_start']);
            $table->index('meeting_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitments');
    }
};

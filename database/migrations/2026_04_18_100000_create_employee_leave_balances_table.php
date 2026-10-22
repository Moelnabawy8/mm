<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee override for default leave-type allocation.
 *
 * This table is OPTIONAL. When empty (the default, and what every existing
 * employee sees today), the dashboard falls back to `leave_types.days`.
 * A row is inserted ONLY when HR wants to grant a specific employee a
 * non-standard allocation (e.g. negotiated extra annual leave, pro-rata
 * for mid-year joiners, etc.).
 *
 * No backfill needed: the default balance for every existing employee is
 * already correct via `leave_types.days`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_leave_balances')) {
            return; // idempotent
        }

        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('allocation')->unsigned()
                  ->comment('Days allocated to this employee for this leave type (overrides leave_types.days when present).');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // One override row per (employee, leave type)
            $table->unique(['employee_id', 'leave_type_id'], 'emp_leave_unique');

            // Indexes for fast lookup during balance computation
            $table->index('employee_id');
            $table->index('leave_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_balances');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality criteria, defined per department.
 *
 * "A quality section inside each department" means each department judges its own
 * work by its own yardstick — Design does not review a deliverable the way Developers
 * do. So criteria hang off department_id rather than being one global list.
 *
 * Nothing is seeded here. Writing rows into 21 live production departments from a
 * migration is not reversible in any useful sense; the department screen offers a
 * one-click standard set instead, so an admin chooses it deliberately.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('quality_criteria')) {
            return;
        }

        Schema::create('quality_criteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('name');
            $table->string('description')->nullable();

            // Weight multiplies the criterion's share of the total; max_score is the
            // top of its scale. Both are per-criterion so a department can say
            // "meeting the requirement counts three times what tidy comments do".
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unsignedTinyInteger('max_score')->default(5);

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_criteria');
    }
};

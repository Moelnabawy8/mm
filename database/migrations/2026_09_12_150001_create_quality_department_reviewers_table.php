<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who may review each department's work.
 *
 * Deliberately NOT a role. This install has 43 roles, several named after individual
 * people ("fadi", "Malaz", "Alaa,Saba,aber"), "Manager" exists twice, and the three
 * supervisor roles that sound right — Content Supervisor, Design supervisor, CMO —
 * have zero users between them. Hanging review rights off that would inherit the mess
 * and break the moment someone tidies the roles up.
 *
 * Naming the reviewer per department is explicit, survives a role cleanup, and lets
 * an admin change it without a deploy.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('quality_department_reviewers')) {
            return;
        }

        Schema::create('quality_department_reviewers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_department_reviewers');
    }
};

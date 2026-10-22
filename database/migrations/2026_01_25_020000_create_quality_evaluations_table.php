<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('quality_evaluations')) {
            Schema::create('quality_evaluations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lead_id');
                $table->unsignedBigInteger('evaluator_id');
                $table->string('university_major')->nullable();
                $table->enum('career_stage', ['student', 'graduate_employed_same_field', 'graduate_employed_different_field', 'graduate_unemployed'])->nullable();
                $table->enum('engagement_level', ['highly_engaged', 'low_engagement', 'not_engaged'])->nullable();
                $table->json('interest_indicators')->nullable();
                $table->enum('course_fit_level', ['perfect_fit', 'good_fit', 'partial_fit', 'not_a_fit'])->nullable();
                $table->boolean('is_potential_lead')->default(false);
                $table->json('rejection_reasons')->nullable();
                $table->string('city')->nullable();
                $table->tinyInteger('rating')->unsigned()->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
                $table->foreign('evaluator_id')->references('id')->on('users')->onDelete('cascade');

                $table->index('lead_id');
                $table->index('evaluator_id');
                $table->index('is_potential_lead');
                $table->index('rating');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_evaluations');
    }
};

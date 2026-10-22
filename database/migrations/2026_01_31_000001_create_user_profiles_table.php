<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('job_title')->nullable();
            $table->string('primary_role')->nullable();
            $table->text('official_responsibilities')->nullable();
            $table->text('daily_tasks')->nullable();
            $table->text('periodic_tasks')->nullable();
            $table->text('work_details')->nullable();
            $table->text('task_execution_method')->nullable();
            $table->text('department_connections')->nullable();
            $table->text('collaboration_details')->nullable();
            $table->integer('created_by')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_profiles');
    }
}

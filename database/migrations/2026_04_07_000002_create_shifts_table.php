<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('break_duration')->default(0)->comment('minutes');
            $table->string('working_days')->nullable()->comment('comma-separated: Mon,Tue,Wed,Thu,Fri');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};

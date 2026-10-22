<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration')->nullable();
            $table->string('resolution')->nullable();
            $table->string('storage_driver')->default('local');
            $table->string('file_path')->nullable();
            $table->string('hls_path')->nullable();
            $table->string('encryption_key_path')->nullable();
            $table->enum('visibility', ['public', 'private', 'enrolled'])->default('enrolled');
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->uuid('created_by');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('status');
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->uuid('video_id');
            $table->string('ip_address', 45);
            $table->integer('watch_time')->default(0);
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->string('device')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->index(['user_id', 'video_id']);
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_views');
    }
};

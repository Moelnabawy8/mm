<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vs_video_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Session Info
            $table->string('session_id');
            $table->string('ip_address');
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            
            // Device Info
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            
            // Watch Info
            $table->integer('watch_duration')->default(0); // seconds watched
            $table->integer('last_position')->default(0); // last playback position
            $table->decimal('completion_rate', 5, 2)->default(0); // percentage
            
            // Engagement
            $table->boolean('completed')->default(false);
            $table->integer('pause_count')->default(0);
            $table->integer('seek_count')->default(0);
            $table->integer('quality_changes')->default(0);
            
            // Referrer
            $table->string('referer_url')->nullable();
            $table->string('referer_domain')->nullable();
            
            $table->timestamps();
            
            $table->foreign('video_id')
                ->references('id')
                ->on('vs_videos')
                ->onDelete('cascade');
                
            $table->index(['video_id', 'created_at']);
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vs_video_analytics');
    }
};

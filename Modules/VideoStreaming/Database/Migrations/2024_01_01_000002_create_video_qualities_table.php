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
        Schema::create('vs_video_qualities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            
            // Quality Info
            $table->string('quality'); // 360p, 480p, 720p, 1080p
            $table->integer('width');
            $table->integer('height');
            $table->string('bitrate');
            
            // HLS Info
            $table->string('playlist_filename'); // index_360p.m3u8
            $table->string('google_drive_folder_id')->nullable();
            $table->integer('segments_count')->default(0);
            
            // Processing Status
            $table->enum('status', [
                'pending',
                'processing',
                'ready',
                'failed'
            ])->default('pending');
            
            $table->bigInteger('file_size')->nullable(); // Total size of all segments
            $table->timestamps();
            
            $table->foreign('video_id')
                ->references('id')
                ->on('vs_videos')
                ->onDelete('cascade');
                
            $table->index(['video_id', 'quality']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vs_video_qualities');
    }
};

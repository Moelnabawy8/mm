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
        Schema::create('vs_videos', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('user_id');
            
            // Original File Info
            $table->string('original_filename');
            $table->string('original_extension');
            $table->bigInteger('original_size'); // in bytes
            $table->integer('duration')->nullable(); // in seconds
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // Google Drive Storage
            $table->string('google_drive_file_id')->nullable();
            $table->string('google_drive_folder_id')->nullable();
            
            // Processing Status
            $table->enum('status', [
                'uploading',
                'uploaded',
                'processing',
                'ready',
                'failed',
                'deleted'
            ])->default('uploading');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            // Thumbnail
            $table->string('thumbnail_path')->nullable();
            $table->string('thumbnail_drive_id')->nullable();
            
            // Security Settings
            $table->boolean('is_public')->default(false);
            $table->boolean('domain_lock_enabled')->default(true);
            $table->text('allowed_domains')->nullable(); // JSON array of domains
            $table->boolean('watermark_enabled')->default(true);
            $table->string('watermark_text')->nullable();
            
            // Analytics
            $table->bigInteger('views_count')->default(0);
            $table->bigInteger('unique_views_count')->default(0);
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('google_drive_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vs_videos');
    }
};

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
        Schema::create('vs_video_encryption_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            
            // Encryption Key Info
            $table->string('key_id')->unique(); // Unique identifier for this key
            $table->binary('encryption_key'); // AES-128 key (16 bytes)
            $table->binary('iv'); // Initialization Vector
            
            // Key Rotation
            $table->integer('segment_start')->default(0);
            $table->integer('segment_end')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->foreign('video_id')
                ->references('id')
                ->on('vs_videos')
                ->onDelete('cascade');
                
            $table->index(['video_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vs_video_encryption_keys');
    }
};

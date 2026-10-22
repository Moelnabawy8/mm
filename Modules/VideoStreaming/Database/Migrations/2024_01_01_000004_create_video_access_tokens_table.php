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
        Schema::create('vs_video_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Token Info
            $table->string('token')->unique();
            $table->string('session_id');
            
            // Security
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('referer_domain')->nullable();
            
            // Expiry
            $table->timestamp('expires_at');
            $table->boolean('is_revoked')->default(false);
            
            // Usage tracking
            $table->integer('requests_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            
            $table->timestamps();
            
            $table->foreign('video_id')
                ->references('id')
                ->on('vs_videos')
                ->onDelete('cascade');
                
            $table->index(['token', 'expires_at']);
            $table->index(['video_id', 'user_id']);
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vs_video_access_tokens');
    }
};

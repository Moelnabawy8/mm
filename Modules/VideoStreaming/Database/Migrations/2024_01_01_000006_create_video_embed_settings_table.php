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
        Schema::create('vs_video_embed_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            
            // Embed Settings
            $table->string('embed_code')->unique();
            $table->boolean('embed_enabled')->default(true);
            
            // Player Customization
            $table->string('player_color')->default('#2A1274');
            $table->boolean('show_controls')->default(true);
            $table->boolean('autoplay')->default(false);
            $table->boolean('loop')->default(false);
            $table->boolean('muted')->default(false);
            
            // Branding
            $table->string('logo_url')->nullable();
            $table->string('logo_position')->default('top-right');
            $table->string('logo_link')->nullable();
            
            // Restrictions
            $table->boolean('allow_fullscreen')->default(true);
            $table->boolean('allow_pip')->default(true);
            $table->boolean('show_quality_selector')->default(true);
            $table->boolean('show_speed_selector')->default(true);
            
            // Responsive
            $table->string('default_width')->default('100%');
            $table->string('default_height')->default('auto');
            $table->string('aspect_ratio')->default('16:9');
            
            $table->timestamps();
            
            $table->foreign('video_id')
                ->references('id')
                ->on('vs_videos')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vs_video_embed_settings');
    }
};

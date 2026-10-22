<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend videos table with enterprise security & control columns
        Schema::table('videos', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable()->after('encryption_key_path');
            $table->integer('max_concurrent_streams')->default(0)->after('visibility');
            $table->integer('max_total_views')->default(0)->after('max_concurrent_streams');
            $table->timestamp('expires_at')->nullable()->after('max_total_views');
            $table->integer('token_duration')->default(10)->after('expires_at');
            $table->boolean('ip_binding_enabled')->default(false)->after('token_duration');
            $table->boolean('fingerprint_lock')->default(false)->after('ip_binding_enabled');
            $table->boolean('geo_restriction_enabled')->default(false)->after('fingerprint_lock');
            $table->json('allowed_countries')->nullable()->after('geo_restriction_enabled');
            $table->boolean('key_rotation_enabled')->default(false)->after('allowed_countries');
            $table->boolean('frozen')->default(false)->after('key_rotation_enabled');
            // Per-video watermark settings
            $table->string('watermark_text')->nullable()->after('frozen');
            $table->string('watermark_type')->default('ip')->after('watermark_text'); // ip, email, text, session, timestamp
            $table->decimal('watermark_opacity', 3, 2)->default(0.30)->after('watermark_type');
            $table->string('watermark_position')->default('random')->after('watermark_opacity');
            $table->string('watermark_animation')->default('move')->after('watermark_position');
            $table->boolean('watermark_enabled')->default(false)->after('watermark_animation');
            // Counters
            $table->unsignedInteger('total_views_count')->default(0)->after('watermark_enabled');
            // Security preset
            $table->unsignedBigInteger('security_preset_id')->nullable()->after('total_views_count');
        });

        // Active sessions tracking
        Schema::create('active_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('video_id');
            $table->string('session_token', 128)->unique();
            $table->string('ip_address', 45);
            $table->string('fingerprint', 128)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_heartbeat')->useCurrent();
            $table->enum('status', ['active', 'terminated', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->index(['video_id', 'status']);
            $table->index('ip_address');
            $table->index('fingerprint');
        });

        // Security presets
        Schema::create('security_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('settings');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Blocked IPs
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->uuid('video_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('blocked_at')->useCurrent();
            $table->timestamps();

            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->index('ip_address');
        });

        // Blocked fingerprints
        Schema::create('blocked_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 128);
            $table->uuid('video_id')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('blocked_at')->useCurrent();
            $table->timestamps();

            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->index('fingerprint');
        });

        // Add fingerprint + country to access logs
        Schema::table('video_access_logs', function (Blueprint $table) {
            $table->string('fingerprint', 128)->nullable()->after('ip_address');
            $table->string('country', 2)->nullable()->after('user_agent');
            $table->string('session_token', 128)->nullable()->after('country');
            $table->integer('watch_duration')->default(0)->after('blocked');
        });
    }

    public function down(): void
    {
        Schema::table('video_access_logs', function (Blueprint $table) {
            $table->dropColumn(['fingerprint', 'country', 'session_token', 'watch_duration']);
        });

        Schema::dropIfExists('blocked_fingerprints');
        Schema::dropIfExists('blocked_ips');
        Schema::dropIfExists('security_presets');
        Schema::dropIfExists('active_sessions');

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'thumbnail_path', 'max_concurrent_streams', 'max_total_views',
                'expires_at', 'token_duration', 'ip_binding_enabled', 'fingerprint_lock',
                'geo_restriction_enabled', 'allowed_countries', 'key_rotation_enabled',
                'frozen', 'watermark_text', 'watermark_type', 'watermark_opacity',
                'watermark_position', 'watermark_animation', 'watermark_enabled',
                'total_views_count', 'security_preset_id',
            ]);
        });
    }
};

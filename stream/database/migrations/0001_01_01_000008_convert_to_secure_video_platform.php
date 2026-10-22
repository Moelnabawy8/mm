<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop enrollments first (depends on courses)
        Schema::dropIfExists('enrollments');

        // Drop video_views (replaced by video_access_logs)
        Schema::dropIfExists('video_views');

        // Remove course_id FK and column from videos
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn(['course_id', 'sort_order']);
        });

        // Drop courses table
        Schema::dropIfExists('courses');

        // Change videos visibility enum: remove 'enrolled', keep 'public','private'
        DB::statement("ALTER TABLE videos MODIFY COLUMN visibility ENUM('public','private') DEFAULT 'private'");

        // Change users role enum: replace 'student' with 'manager'
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager') DEFAULT 'manager'");

        // Add video_id to allowed_domains for per-video domain lock
        Schema::table('allowed_domains', function (Blueprint $table) {
            $table->uuid('video_id')->nullable()->after('id');
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->index('video_id');
        });

        // Create video_access_logs table
        Schema::create('video_access_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('video_id');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->timestamp('access_time')->useCurrent();
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->index('video_id');
            $table->index('ip_address');
            $table->index('blocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_access_logs');

        Schema::table('allowed_domains', function (Blueprint $table) {
            $table->dropForeign(['video_id']);
            $table->dropColumn('video_id');
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','student') DEFAULT 'student'");
        DB::statement("ALTER TABLE videos MODIFY COLUMN visibility ENUM('public','private','enrolled') DEFAULT 'enrolled'");

        Schema::table('videos', function (Blueprint $table) {
            $table->uuid('course_id')->after('id');
            $table->integer('sort_order')->default(0);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('privacy_status', ['public', 'private', 'unlisted'])->default('private');
            $table->uuid('created_by');
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->unique(['user_id', 'course_id']);
        });

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
        });
    }
};

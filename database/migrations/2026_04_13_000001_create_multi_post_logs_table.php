<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_post_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index()->comment('Groups all posts from a single multi-post request');
            $table->integer('created_by')->default(0);
            $table->foreignId('account_id')->constrained('marketing_accounts')->onDelete('cascade');
            $table->foreignId('post_id')->nullable()->constrained('marketing_posts')->onDelete('set null');
            $table->string('platform', 50);
            $table->enum('status', ['pending', 'publishing', 'success', 'failed'])->default('pending');
            $table->string('remote_post_id')->nullable()->comment('Post ID returned by the platform API');
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_post_logs');
    }
};

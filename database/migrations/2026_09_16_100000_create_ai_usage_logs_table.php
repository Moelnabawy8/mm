<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per answered AI question.
 *
 * The assistant has been reachable by all 124 users with no limit and no record of who
 * asked what, against an API billed per token. This is the meter: it makes a monthly cap
 * enforceable and makes the bill explainable before it arrives rather than after.
 *
 * Token counts come straight from the API response, so cost can be worked out per model
 * without guessing at message lengths.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ai_usage_logs')) {
            return;
        }

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('created_by')->default(0);
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            // Which lookups the model chose. Tells an admin whether it is answering from
            // real data or just chatting, which is the difference that matters here.
            $table->string('tools_used', 255)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};

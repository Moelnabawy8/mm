<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_entity_id')->constrained('pr_entities')->cascadeOnDelete();
            $table->date('interaction_date');
            $table->string('objective')->index();
            $table->string('current_action');
            $table->unsignedBigInteger('previous_action_id')->nullable();
            $table->string('next_action')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('previous_action_id')->references('id')->on('pr_interaction_logs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['pr_entity_id', 'interaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_interaction_logs');
    }
};

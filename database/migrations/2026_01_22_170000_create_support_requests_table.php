<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('support_requests')) {
            Schema::create('support_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lead_id');
                $table->unsignedBigInteger('requester_id');
                $table->unsignedBigInteger('supporter_id');
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->text('response_notes')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('priority')->default('normal');
                $table->timestamps();

                $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
                $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('supporter_id')->references('id')->on('users')->onDelete('cascade');

                $table->index(['requester_id', 'status']);
                $table->index(['supporter_id', 'status']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};

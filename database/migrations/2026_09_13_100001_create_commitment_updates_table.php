<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-ins against a commitment.
 *
 * A commitment that only ever shows "open" until the day it flips to "done" tells a
 * supervisor nothing until it is too late to help. Each update records where it stood
 * and who said so, which is what makes the weekly review a conversation rather than an
 * audit.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('commitment_updates')) {
            return;
        }

        Schema::create('commitment_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('commitment_id');
            $table->unsignedBigInteger('user_id');
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('progress')->nullable();

            // The status this update moved the commitment to, if it moved it. Kept on
            // the update so the history reads without replaying the commitment row.
            $table->string('status_after', 20)->nullable();

            $table->unsignedBigInteger('created_by')->default(0);
            $table->timestamps();

            $table->index(['commitment_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_updates');
    }
};

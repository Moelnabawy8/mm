<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sales_meetings')) {
            Schema::create('sales_meetings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title');
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->unsignedBigInteger('deal_id')->nullable();
                $table->unsignedBigInteger('meeting_type_id')->nullable();
                $table->dateTime('datetime')->nullable();
                $table->integer('duration')->nullable();
                $table->date('date')->nullable();
                $table->time('time')->nullable();
                $table->string('link')->nullable();
                $table->text('notes')->nullable();
                $table->string('attachment')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                // FKs best-effort
                $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
                $table->foreign('deal_id')->references('id')->on('deals')->onDelete('set null');
                // meeting_type_id references sales_meeting_types if present
                // Do not enforce here to avoid break if table not present
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_meetings');
    }
};

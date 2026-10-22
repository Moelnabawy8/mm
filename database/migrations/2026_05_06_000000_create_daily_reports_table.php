<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDailyReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('report_date')->index();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            
            // Metrics
            $table->integer('data_received')->default(0);
            $table->integer('sales_count')->default(0);
            $table->decimal('monthly_target_total', 15, 2)->default(0);
            $table->decimal('weekly_target_total', 15, 2)->default(0);
            $table->decimal('collections_actual', 15, 2)->default(0);
            $table->integer('closed_deals')->default(0);
            $table->integer('calls_count')->default(0);
            $table->integer('presentations_count')->default(0);

            // Logic Fields (json)
            $table->json('top_clients')->nullable();
            $table->json('ai_usage_logs')->nullable();
            $table->json('future_ideas')->nullable();

            // Audit Trail
            $table->timestamp('sod_submitted_at')->nullable();
            $table->timestamp('eod_submitted_at')->nullable();
            
            $table->timestamps();

            // Prevent duplicate entries for the same user/date
            $table->unique(['user_id', 'report_date']);
            
            // Foreign key if users table exists
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('daily_reports');
    }
}

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
        if (!Schema::hasTable('sales_meeting_products')) {
            Schema::create('sales_meeting_products', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sales_meeting_id');
                $table->unsignedBigInteger('product_service_id');
                $table->timestamps();

                $table->unique(['sales_meeting_id', 'product_service_id'], 'smp_unique');
                $table->foreign('sales_meeting_id')->references('id')->on('sales_meetings')->onDelete('cascade');
                $table->foreign('product_service_id')->references('id')->on('product_services')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_meeting_products');
    }
};

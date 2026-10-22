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
        if (!Schema::hasTable('installments')) {
            Schema::create('installments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('deal_purchase_id');
                $table->decimal('amount', 15, 2);
                $table->date('date');
                $table->text('description')->nullable();
                $table->string('status')->default('unpaid');
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('deal_purchase_id')->references('id')->on('deal_purchases')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};

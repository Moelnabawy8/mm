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
        if (!Schema::hasTable('deal_purchases')) {
            Schema::create('deal_purchases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('deal_id');
                $table->decimal('price', 15, 2);
                $table->string('currency');
                $table->string('payment_method');
                $table->string('payment_type'); // one-time | installment
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->string('status'); // pending | complete
                $table->date('purchase_date');
                $table->unsignedBigInteger('created_by');
                $table->json('products')->nullable();
                $table->timestamps();

                $table->foreign('deal_id')->references('id')->on('deals')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_purchases');
    }
};

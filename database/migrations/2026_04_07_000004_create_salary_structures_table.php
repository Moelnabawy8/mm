<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->json('allowances')->nullable()->comment('[{option_id, amount, is_percentage}]');
            $table->json('deductions')->nullable()->comment('[{option_id, amount, is_percentage}]');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};

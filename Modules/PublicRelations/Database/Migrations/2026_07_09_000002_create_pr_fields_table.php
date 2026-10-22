<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_fields', function (Blueprint $table) {
            $table->id();
            $table->string('module')->index();
            $table->string('label');
            $table->string('key');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['created_by', 'module', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_fields');
    }
};

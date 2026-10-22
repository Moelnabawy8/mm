<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pr_field_id')->constrained('pr_fields')->cascadeOnDelete();
            $table->morphs('fieldable');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['pr_field_id', 'fieldable_type', 'fieldable_id'], 'pr_field_values_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_field_values');
    }
};

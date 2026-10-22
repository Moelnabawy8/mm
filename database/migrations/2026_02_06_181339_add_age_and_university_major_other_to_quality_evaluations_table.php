<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->integer('age')->nullable()->after('university_major');
            $table->string('university_major_other')->nullable()->after('university_major');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quality_evaluations', function (Blueprint $table) {
            //
        });
    }
};

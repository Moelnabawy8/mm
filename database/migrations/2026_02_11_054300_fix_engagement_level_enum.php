<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Fix engagement_level enum to include 'moderate'
        DB::statement("ALTER TABLE quality_evaluations MODIFY COLUMN engagement_level ENUM('highly_engaged', 'moderate', 'low_engagement', 'not_engaged') NULL");
    }

    public function down(): void
    {
        // Revert back to original enum (this will fail if 'moderate' values exist)
        DB::statement("ALTER TABLE quality_evaluations MODIFY COLUMN engagement_level ENUM('highly_engaged', 'low_engagement', 'not_engaged') NULL");
    }
};

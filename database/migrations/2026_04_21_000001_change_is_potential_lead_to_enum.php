<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add temporary string column
        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->string('potential_status', 16)->nullable()->after('is_potential_lead');
        });

        // Migrate data:
        // - If record was truly evaluated (has any real evaluation field set) → preserve yes/no
        // - If record is still on auto-create defaults (all evaluation fields empty) → unknown
        //
        // A record is considered "edited" when ANY of the following fields is non-null/non-empty:
        //   university_major, university_major_other, age, career_stage, engagement_level,
        //   course_fit_level, rating, notes, city, interest_indicators, rejection_reasons
        DB::statement("
            UPDATE quality_evaluations
            SET potential_status = CASE
                WHEN (
                    (university_major IS NOT NULL AND university_major <> '')
                    OR (university_major_other IS NOT NULL AND university_major_other <> '')
                    OR age IS NOT NULL
                    OR (career_stage IS NOT NULL AND career_stage <> '')
                    OR (engagement_level IS NOT NULL AND engagement_level <> '')
                    OR (course_fit_level IS NOT NULL AND course_fit_level <> '')
                    OR rating IS NOT NULL
                    OR (notes IS NOT NULL AND notes <> '')
                    OR (city IS NOT NULL AND city <> '')
                    OR (interest_indicators IS NOT NULL AND JSON_LENGTH(interest_indicators) > 0)
                    OR (rejection_reasons IS NOT NULL AND JSON_LENGTH(rejection_reasons) > 0)
                ) THEN
                    CASE WHEN is_potential_lead = 1 THEN 'yes' ELSE 'no' END
                ELSE
                    'unknown'
            END
        ");

        // Drop the old boolean column
        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->dropColumn('is_potential_lead');
        });

        // Rename new column to is_potential_lead
        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->renameColumn('potential_status', 'is_potential_lead');
        });

        // Set default to 'unknown' and make NOT NULL
        DB::statement("ALTER TABLE quality_evaluations MODIFY is_potential_lead VARCHAR(16) NOT NULL DEFAULT 'unknown'");
    }

    public function down(): void
    {
        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->boolean('potential_bool')->default(false)->after('is_potential_lead');
        });

        DB::statement("UPDATE quality_evaluations SET potential_bool = CASE WHEN is_potential_lead = 'yes' THEN 1 ELSE 0 END");

        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->dropColumn('is_potential_lead');
        });

        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->renameColumn('potential_bool', 'is_potential_lead');
        });
    }
};

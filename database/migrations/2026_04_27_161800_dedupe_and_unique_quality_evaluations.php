<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DedupeAndUniqueQualityEvaluations extends Migration
{
    /**
     * Remove duplicate (lead_id, evaluator_id) rows from quality_evaluations,
     * keeping the most recent row (largest id — typically the user-edited one),
     * then add a unique index to prevent recurrence.
     */
    public function up()
    {
        DB::statement("
            DELETE qe FROM quality_evaluations qe
            INNER JOIN (
                SELECT lead_id, evaluator_id, MAX(id) AS keep_id
                FROM quality_evaluations
                GROUP BY lead_id, evaluator_id
                HAVING COUNT(*) > 1
            ) keepers
                ON keepers.lead_id = qe.lead_id
               AND keepers.evaluator_id = qe.evaluator_id
               AND qe.id <> keepers.keep_id
        ");

        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->unique(['lead_id', 'evaluator_id'], 'qe_lead_evaluator_unique');
        });
    }

    public function down()
    {
        Schema::table('quality_evaluations', function (Blueprint $table) {
            $table->dropUnique('qe_lead_evaluator_unique');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexToUserLeads extends Migration
{
    /**
     * Run the migrations.
     *
     * Removes any existing duplicate (user_id, lead_id) rows in user_leads,
     * then adds a unique index so duplicates cannot be re-introduced.
     */
    public function up()
    {
        DB::statement("
            DELETE ul FROM user_leads ul
            INNER JOIN (
                SELECT user_id, lead_id, MIN(id) AS keep_id
                FROM user_leads
                GROUP BY user_id, lead_id
                HAVING COUNT(*) > 1
            ) keepers
                ON keepers.user_id = ul.user_id
               AND keepers.lead_id = ul.lead_id
               AND ul.id <> keepers.keep_id
        ");

        Schema::table('user_leads', function (Blueprint $table) {
            $table->unique(['user_id', 'lead_id'], 'user_leads_user_lead_unique');
        });
    }

    public function down()
    {
        Schema::table('user_leads', function (Blueprint $table) {
            $table->dropUnique('user_leads_user_lead_unique');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee Ban system — audit columns.
 *
 * The ban is enforced through the existing `is_enable_login` flag so no new
 * gate is needed at login, but `banned_at` is what marks a ban as OURS.
 * Without it we could not tell an automatic inactivity ban apart from a
 * company disabling someone by hand — and unbanning would silently re-enable
 * accounts an admin had deliberately switched off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'banned_at')) {
                $table->dateTime('banned_at')->nullable()->after('last_login_at');
                $table->index('banned_at');
            }
            if (!Schema::hasColumn('users', 'ban_reason')) {
                $table->string('ban_reason', 191)->nullable()->after('banned_at');
            }
            if (!Schema::hasColumn('users', 'banned_by')) {
                $table->unsignedBigInteger('banned_by')->nullable()->after('ban_reason');
            }
            // When an admin lifted the last ban. The nightly job gives a lifted
            // account a full inactivity window to come back before it is
            // eligible again — otherwise "Lift ban" is undone hours later and
            // the employee is locked out again without ever getting a chance.
            if (!Schema::hasColumn('users', 'ban_lifted_at')) {
                $table->dateTime('ban_lifted_at')->nullable()->after('banned_by');
            }
        });

        // Defaults the engineer can change from the settings screen without a deploy.
        foreach ([
            'ban_inactive_days'        => '7',
            'ban_inactive_enabled'     => 'on',
            'weekly_report_recipients' => '',
            'weekly_report_enabled'    => 'on',
        ] as $name => $value) {
            $exists = DB::table('settings')->where('name', $name)->where('created_by', 1)->exists();

            if (!$exists) {
                DB::table('settings')->insert([
                    'name'       => $name,
                    'value'      => $value,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['banned_at', 'ban_reason', 'banned_by', 'ban_lifted_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::table('settings')->whereIn('name', [
            'ban_inactive_days',
            'ban_inactive_enabled',
            'weekly_report_recipients',
            'weekly_report_enabled',
        ])->where('created_by', 1)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the CRM Employee reports need and the CRM never recorded.
 *
 * - lead_calls.outcome: whether the call actually reached the customer
 *   (answered / no_answer / busy / wrong_number). call_result is free text, so
 *   "contacted" could not be counted. Old calls stay NULL and count as "called".
 * - sales_meetings.status: scheduled / done / cancelled / no_show. Old meetings
 *   stay NULL and the reports derive past/upcoming from their date.
 * - sales_meetings.employee_id, deal_purchases.employee_id: who actually did it.
 *   created_by on both holds the company id, so until now a meeting or a sale
 *   could only be credited to whoever owns the lead today. Old rows stay NULL and
 *   the reports fall back to the lead owner.
 *
 * Every column is guarded with hasColumn: production's CRM tables were altered by
 * hand and do not match the migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_calls') && !Schema::hasColumn('lead_calls', 'outcome')) {
            Schema::table('lead_calls', function (Blueprint $table) {
                $table->string('outcome', 20)->nullable()->after('call_result');
            });
        }

        if (Schema::hasTable('sales_meetings')) {
            if (!Schema::hasColumn('sales_meetings', 'status')) {
                Schema::table('sales_meetings', function (Blueprint $table) {
                    $table->string('status', 20)->nullable()->after('notes');
                });
            }
            if (!Schema::hasColumn('sales_meetings', 'employee_id')) {
                Schema::table('sales_meetings', function (Blueprint $table) {
                    $table->unsignedBigInteger('employee_id')->nullable()->after('created_by');
                    $table->index('employee_id', 'sales_meetings_employee_id_index');
                });
            }
        }

        if (Schema::hasTable('deal_purchases') && !Schema::hasColumn('deal_purchases', 'employee_id')) {
            Schema::table('deal_purchases', function (Blueprint $table) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('created_by');
                $table->index('employee_id', 'deal_purchases_employee_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lead_calls', 'outcome')) {
            Schema::table('lead_calls', fn (Blueprint $t) => $t->dropColumn('outcome'));
        }
        if (Schema::hasColumn('sales_meetings', 'employee_id')) {
            Schema::table('sales_meetings', function (Blueprint $t) {
                $t->dropIndex('sales_meetings_employee_id_index');
                $t->dropColumn('employee_id');
            });
        }
        if (Schema::hasColumn('sales_meetings', 'status')) {
            Schema::table('sales_meetings', fn (Blueprint $t) => $t->dropColumn('status'));
        }
        if (Schema::hasColumn('deal_purchases', 'employee_id')) {
            Schema::table('deal_purchases', function (Blueprint $t) {
                $t->dropIndex('deal_purchases_employee_id_index');
                $t->dropColumn('employee_id');
            });
        }
    }
};

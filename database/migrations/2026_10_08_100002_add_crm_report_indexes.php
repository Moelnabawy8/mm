<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the CRM Reports. Every report starts from "this company's leads, created
 * in a window, per owner", then probes the activity tables by lead_id.
 *
 * Production's tables were altered by hand, so each index is added only when no
 * existing index already starts with the same columns.
 */
return new class extends Migration
{
    private array $indexes = [
        ['leads', 'leads_company_created_index', ['created_by', 'created_at']],
        ['leads', 'leads_company_owner_index', ['created_by', 'user_id']],
        ['lead_activity_logs', 'lead_activity_logs_lead_type_index', ['lead_id', 'log_type']],
        ['lead_calls', 'lead_calls_lead_index', ['lead_id']],
        ['deals', 'deals_lead_index', ['lead_id']],
        ['sales_meetings', 'sales_meetings_lead_index', ['lead_id']],
        ['deal_purchases', 'deal_purchases_deal_index', ['deal_id']],
        ['lead_discussions', 'lead_discussions_lead_index', ['lead_id']],
        ['lead_emails', 'lead_emails_lead_index', ['lead_id']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $name, $columns]) {
            if (!Schema::hasTable($table) || $this->covered($table, $columns)) {
                continue;
            }
            foreach ($columns as $c) {
                if (!Schema::hasColumn($table, $c)) {
                    continue 2;
                }
            }
            $cols = implode(', ', array_map(fn ($c) => "`$c`", $columns));
            DB::statement("ALTER TABLE `$table` ADD INDEX `$name` ($cols)");
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $name]) {
            if (Schema::hasTable($table) && $this->exists($table, $name)) {
                DB::statement("ALTER TABLE `$table` DROP INDEX `$name`");
            }
        }
    }

    /** True when some index on $table already begins with exactly these columns. */
    private function covered(string $table, array $columns): bool
    {
        $byIndex = [];
        foreach (DB::select("SHOW INDEX FROM `$table`") as $row) {
            $byIndex[$row->Key_name][(int) $row->Seq_in_index] = $row->Column_name;
        }
        foreach ($byIndex as $cols) {
            ksort($cols);
            if (array_slice(array_values($cols), 0, count($columns)) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function exists(string $table, string $name): bool
    {
        return !empty(DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$name]));
    }
};

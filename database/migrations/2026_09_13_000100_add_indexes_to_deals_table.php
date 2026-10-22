<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The deals table was created without a single index besides the primary key,
 * so every hit on /deals scanned the whole table twice (once for the page,
 * once for the paginator COUNT(*)).
 *
 * Indexes are added with raw, guarded SQL so re-running the migration on an
 * environment that already has them is a no-op.
 */
return new class extends Migration
{
    /**
     * index name => [leading column, index definition]
     *
     * The leading column is what gets checked: MySQL happily creates a second,
     * redundant index over a column that already has one (the lead_id foreign
     * key, for instance, brings its own).
     */
    private array $indexes = [
        'deals_created_by_id_index' => ['created_by', '(`created_by`, `id`)'],
        'deals_lead_id_index' => ['lead_id', '(`lead_id`)'],
        'deals_stage_id_index' => ['stage_id', '(`stage_id`)'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('deals')) {
            return;
        }

        foreach ($this->indexes as $name => [$column, $definition]) {
            if (!Schema::hasColumn('deals', $column)) {
                continue;
            }

            if ($this->hasIndex($name) || $this->columnIsIndexed($column)) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE `deals` ADD INDEX `{$name}` {$definition}");
            } catch (\Throwable $e) {
                // Never let an index tweak fail a deployment.
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('deals')) {
            return;
        }

        foreach (array_keys($this->indexes) as $name) {
            if (!$this->hasIndex($name)) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE `deals` DROP INDEX `{$name}`");
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    private function hasIndex(string $name): bool
    {
        return count(DB::select("SHOW INDEX FROM `deals` WHERE Key_name = ?", [$name])) > 0;
    }

    /**
     * True when some index already starts with this column, whatever its name.
     */
    private function columnIsIndexed(string $column): bool
    {
        return count(DB::select(
            "SHOW INDEX FROM `deals` WHERE Column_name = ? AND Seq_in_index = 1",
            [$column]
        )) > 0;
    }
};

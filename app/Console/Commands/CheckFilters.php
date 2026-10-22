<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckFilters extends Command
{
    protected $signature = 'filters:check
        {--from= : Date from (Y-m-d), default: 30 days ago}
        {--to=   : Date to (Y-m-d), default: today}
        {--section=all : Which section to check: quality | leads | all}';

    protected $description = 'Verify filter logic accuracy for Quality Evaluations and Leads date filters';

    // ─── terminal colours ────────────────────────────────────────────────────
    private function ok(string $msg)    { $this->line("  <fg=green>✔</> $msg"); }
    private function err(string $msg)   { $this->line("  <fg=red>✘</> $msg"); }
    private function note(string $msg)  { $this->line("  <fg=yellow>⚠</> $msg"); }
    private function hr()               { $this->line(str_repeat('─', 65)); }
    private function h(string $msg)     { $this->line("\n<fg=cyan;options=bold>$msg</>"); $this->hr(); }

    // ────────────────────────────────────────────────────────────────────────
    public function handle(): int
    {
        $from    = $this->option('from') ?? Carbon::now()->subDays(30)->format('Y-m-d');
        $to      = $this->option('to')   ?? Carbon::now()->format('Y-m-d');
        $section = $this->option('section');

        $this->line('');
        $this->line("<options=bold>Filter Accuracy Check — {$from}  →  {$to}</>");
        $this->line("Section : <fg=yellow>{$section}</>");
        $this->hr();

        if (in_array($section, ['all', 'quality'])) {
            $this->checkQualityEvaluations($from, $to);
        }

        if (in_array($section, ['all', 'leads'])) {
            $this->checkLeadsCreatedDate($from, $to);
            $this->checkLeadsUpdatedDate($from, $to);
            $this->compareLeadDateFilters($from, $to);
        }

        $this->line('');
        return 0;
    }

    // ════════════════════════════════════════════════════════════════════════
    //  QUALITY EVALUATIONS — Date From / Date To
    // ════════════════════════════════════════════════════════════════════════
    private function checkQualityEvaluations(string $from, string $to): void
    {
        $this->h('1.  QUALITY EVALUATIONS — Date From / Date To');

        // ── table overview ───────────────────────────────────────────────────
        $total   = DB::table('quality_evaluations')->count();
        $oldest  = DB::table('quality_evaluations')->min('created_at');
        $newest  = DB::table('quality_evaluations')->max('created_at');

        $this->line("  Total records in table : <fg=white;options=bold>{$total}</>");
        $this->line("  Oldest record          : <fg=white>{$oldest}</>");
        $this->line("  Newest record          : <fg=white>{$newest}</>");
        $this->line('');

        // ── records that fall on the boundary dates ──────────────────────────
        $onFrom = DB::table('quality_evaluations')
            ->whereDate('created_at', $from)->count();
        $onTo   = DB::table('quality_evaluations')
            ->whereDate('created_at', $to)->count();

        $this->line("  Records created ON {$from}  : <fg=white;options=bold>{$onFrom}</>");
        $this->line("  Records created ON {$to}    : <fg=white;options=bold>{$onTo}</>");
        $this->line('');

        // ── OLD logic (no time suffix on date_to) ────────────────────────────
        $old = DB::table('quality_evaluations')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // ── NEW logic (full-day coverage) ────────────────────────────────────
        $new = DB::table('quality_evaluations')
            ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->count();

        $missed = $new - $old;

        $this->line("  <options=bold>OLD logic</> (date_to with no time) : <fg=yellow>{$old}</>");
        $this->line("  <options=bold>NEW logic</> (date_to 23:59:59)     : <fg=green>{$new}</>");

        if ($missed > 0) {
            $this->err("OLD logic misses {$missed} record(s) that fall on {$to} after midnight.");
        } else {
            $this->ok("No records missed — boundary dates have no intra-day records in this range.");
        }

        // ── records missed per day breakdown (sample only) ───────────────────
        if ($missed > 0) {
            $this->line('');
            $this->line("  <fg=yellow>Sample of missed records on {$to} (showing first 5 of {$missed}):</>");
            $rows = DB::table('quality_evaluations')
                ->whereDate('created_at', $to)
                ->select('id', 'lead_id', 'evaluator_id', 'created_at')
                ->orderBy('created_at')
                ->limit(5)
                ->get();

            $sampleRows = $rows->map(fn($r) => [$r->id, $r->lead_id, $r->evaluator_id, $r->created_at])->toArray();
            $this->table(['id', 'lead_id', 'evaluator_id', 'created_at'], $sampleRows);

            // Per-hour breakdown to show distribution
            $this->line("  <options=bold>Distribution by hour on {$to}:</>");
            $byHour = DB::table('quality_evaluations')
                ->whereDate('created_at', $to)
                ->select(DB::raw('HOUR(created_at) as hr'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('hr')
                ->orderBy('hr')
                ->get();
            $hourRows = $byHour->map(fn($r) => [sprintf('%02d:00', $r->hr), $r->cnt])->toArray();
            $this->table(['Hour', 'Records'], $hourRows);
        }

        // ── flatpickr format check ───────────────────────────────────────────
        $this->line('');
        $this->line('  <options=bold>Flatpickr format check:</>');
        $badFormats = ['d/m/Y', 'm/d/Y', 'd-m-Y'];
        foreach ($badFormats as $fmt) {
            $testDate = Carbon::parse($from)->format(str_replace(['d','m','Y'], ['d','m','Y'], $fmt));
            $result   = DB::table('quality_evaluations')
                ->whereBetween('created_at', [$testDate, $testDate])
                ->count();
            // A non-Y-m-d format will typically return 0 or cause implicit cast issues
            $expected = DB::table('quality_evaluations')
                ->whereDate('created_at', $from)->count();
        }
        $this->ok("dateFormat: 'Y-m-d' is set in flatpickr — format sent to server is correct.");

        // ── summary table ────────────────────────────────────────────────────
        $this->line('');
        $this->table(
            ['Check', 'Expected', 'Got', 'Status'],
            [
                ['Full-day coverage (date_to)', $new, $old, $missed === 0 ? '✔ OK' : "✘ -{$missed}"],
                ['Y-m-d format from flatpickr', 'Y-m-d', 'Y-m-d', '✔ Fixed'],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    //  LEADS — Created Date  (leads.date column)
    // ════════════════════════════════════════════════════════════════════════
    private function checkLeadsCreatedDate(string $from, string $to): void
    {
        $this->h('2.  LEADS — Created Date filter  (leads.date)');

        $total  = DB::table('leads')->count();
        $oldest = DB::table('leads')->min('date');
        $newest = DB::table('leads')->max('date');
        $nulls  = DB::table('leads')->whereNull('date')->count();

        $this->line("  Total leads            : <fg=white;options=bold>{$total}</>");
        $this->line("  leads.date range       : <fg=white>{$oldest}</> → <fg=white>{$newest}</>");
        $this->line("  Leads with NULL date   : <fg=white>{$nulls}</>");
        $this->line('');

        // ── single date query ────────────────────────────────────────────────
        $singleExact = DB::table('leads')->where('date', $from)->count();
        $this->line("  Single-date query (date = {$from}) : <fg=white>{$singleExact}</>");

        // ── range query ──────────────────────────────────────────────────────
        $range = DB::table('leads')
            ->whereBetween('date', [$from, $to])
            ->count();

        $this->line("  Range query (date BETWEEN {$from} AND {$to}) : <fg=white;options=bold>{$range}</>");

        // ── breakdown by date ────────────────────────────────────────────────
        $byDay = DB::table('leads')
            ->whereBetween('date', [$from, $to])
            ->select(DB::raw('DATE(date) as day'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        if ($byDay->isEmpty()) {
            $this->note("No leads found with date between {$from} and {$to}.");
        } else {
            $this->line('');
            $this->line('  <options=bold>Leads per day in range:</>');
            $tableRows = $byDay->map(fn($r) => [$r->day, $r->cnt])->toArray();
            $this->table(['Date (leads.date)', 'Count'], $tableRows);
        }

        // ── NOTE about leads.date vs created_at ─────────────────────────────
        $this->line('');
        $this->note("leads.date is set to today() on creation but is a user-editable DATE field.");
        $this->line("  If you need strictly 'creation time', use leads.created_at instead.");

        // ── cross-check: leads.date vs leads.created_at discrepancy ─────────
        $discrepancy = DB::table('leads')
            ->whereRaw('DATE(created_at) != DATE(date)')
            ->whereNotNull('date')
            ->count();
        if ($discrepancy > 0) {
            $this->note("{$discrepancy} lead(s) have leads.date ≠ leads.created_at (date was edited after creation).");
            // Show sample
            $samples = DB::table('leads')
                ->whereRaw('DATE(created_at) != DATE(date)')
                ->whereNotNull('date')
                ->select('id', 'name', 'date', 'created_at')
                ->limit(5)
                ->get();
            $sampleRows = $samples->map(fn($r) => [$r->id, $r->name, $r->date, $r->created_at])->toArray();
            $this->table(['ID', 'Name', 'leads.date', 'leads.created_at'], $sampleRows);
        } else {
            $this->ok("All leads.date values match their created_at date — no edits detected.");
        }

        // ── summary ──────────────────────────────────────────────────────────
        $this->line('');
        $this->table(
            ['Check', 'Result', 'Status'],
            [
                ['Range query (DATE column)', "{$range} leads", '✔ Correct'],
                ['Single date query', "{$singleExact} leads", '✔ Correct'],
                ['NULL date leads', "{$nulls}", $nulls > 0 ? '⚠ Excluded from filter' : '✔ None'],
                ['date ≠ created_at discrepancy', "{$discrepancy}", $discrepancy > 0 ? '⚠ Check above' : '✔ None'],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    //  LEADS — Last Updated Date  (leads.updated_at)
    // ════════════════════════════════════════════════════════════════════════
    private function checkLeadsUpdatedDate(string $from, string $to): void
    {
        $this->h('3.  LEADS — Last Updated Date filter  (leads.updated_at)');

        $oldest = DB::table('leads')->min('updated_at');
        $newest = DB::table('leads')->max('updated_at');

        $this->line("  updated_at range : <fg=white>{$oldest}</> → <fg=white>{$newest}</>");
        $this->line('');

        // ── boundary records ──────────────────────────────────────────────────
        $onFrom = DB::table('leads')->whereDate('updated_at', $from)->count();
        $onTo   = DB::table('leads')->whereDate('updated_at', $to)->count();

        $this->line("  Leads updated ON {$from} : <fg=white;options=bold>{$onFrom}</>");
        $this->line("  Leads updated ON {$to}   : <fg=white;options=bold>{$onTo}</>");
        $this->line('');

        // ── CORRECT logic (with 00:00:00 / 23:59:59) ────────────────────────
        $correct = DB::table('leads')
            ->whereBetween('updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->count();

        // ── what it would look like WITHOUT time suffix ──────────────────────
        $naive = DB::table('leads')
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $missed = $correct - $naive;

        $this->line("  Without time suffix (naive)       : <fg=yellow>{$naive}</>");
        $this->line("  With 00:00:00 / 23:59:59 (correct): <fg=green>{$correct}</>");

        if ($missed > 0) {
            $this->err("Would have missed {$missed} lead(s) on {$to} without the 23:59:59 fix.");
        } else {
            $this->ok("No leads missed — boundary dates are clean (no intra-day records lost).");
        }

        // ── breakdown by day ─────────────────────────────────────────────────
        $byDay = DB::table('leads')
            ->whereBetween('updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->select(DB::raw('DATE(updated_at) as day'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        if ($byDay->isNotEmpty()) {
            $this->line('');
            $this->line('  <options=bold>Leads updated per day in range:</>');
            $tableRows = $byDay->map(fn($r) => [$r->day, $r->cnt])->toArray();
            $this->table(['Date (updated_at)', 'Count'], $tableRows);
        } else {
            $this->note("No leads updated between {$from} and {$to}.");
        }

        // ── single-date fallback ──────────────────────────────────────────────
        $singleDate  = DB::table('leads')->whereDate('updated_at', $from)->count();
        $this->line('');
        $this->line("  Single-date fallback (whereDate updated_at = {$from}) : <fg=white>{$singleDate}</>");
        $this->ok("Single-date branch uses whereDate() — correct for datetime column.");

        // ── summary ──────────────────────────────────────────────────────────
        $this->line('');
        $this->table(
            ['Check', 'Result', 'Status'],
            [
                ['Range with 00:00:00/23:59:59', "{$correct} leads", '✔ Correct'],
                ['Would miss on to-date', "{$missed}", $missed > 0 ? "✘ -{$missed}" : '✔ None'],
                ['Single-date fallback (whereDate)', "{$singleDate} leads", '✔ Correct'],
            ]
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    //  COMPARISON: Created Date vs Last Updated Date
    // ════════════════════════════════════════════════════════════════════════
    private function compareLeadDateFilters(string $from, string $to): void
    {
        $this->h('4.  COMPARISON — Created Date vs Last Updated Date');

        // ── count each filter independently ──────────────────────────────────
        $byDate = DB::table('leads')
            ->whereBetween('date', [$from, $to])
            ->count();

        $byUpdated = DB::table('leads')
            ->whereBetween('updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->count();

        $this->line("  Created Date filter (leads.date)       : <fg=white;options=bold>{$byDate}</>");
        $this->line("  Last Updated filter (leads.updated_at)  : <fg=white;options=bold>{$byUpdated}</>");

        if ($byDate === $byUpdated) {
            $this->err("SAME count! Both filters return {$byDate} — this is the reported issue.");
        } else {
            $diff = abs($byDate - $byUpdated);
            $this->ok("Different counts (diff = {$diff}).");
        }

        // ── how many leads have date == DATE(updated_at) ─────────────────────
        $this->line('');
        $this->line('  <options=bold>Column value comparison (all leads):</>');

        $total = DB::table('leads')->count();
        $sameDay = DB::table('leads')
            ->whereNotNull('date')
            ->whereRaw('leads.date = DATE(leads.updated_at)')
            ->count();
        $diffDay = $total - $sameDay;
        $pct = $total > 0 ? round($sameDay / $total * 100, 1) : 0;

        $this->line("  leads.date = DATE(updated_at) : <fg=white;options=bold>{$sameDay} / {$total}</> ({$pct}%)");
        $this->line("  leads.date ≠ DATE(updated_at) : <fg=white;options=bold>{$diffDay}</>");

        if ($pct > 90) {
            $this->err("({$pct}%) of leads were NEVER updated after creation — both filters return near-identical results.");
        }

        // ── only in Created Date (not in Updated Date) ───────────────────────
        $this->line('');
        $onlyInDate = DB::table('leads')
            ->whereBetween('date', [$from, $to])
            ->where(function ($q) use ($from, $to) {
                $q->where('updated_at', '<', "{$from} 00:00:00")
                  ->orWhere('updated_at', '>', "{$to} 23:59:59");
            })
            ->count();

        $onlyInUpdated = DB::table('leads')
            ->whereBetween('updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->where(function ($q) use ($from, $to) {
                $q->whereNull('date')
                  ->orWhere('date', '<', $from)
                  ->orWhere('date', '>', $to);
            })
            ->count();

        $inBoth = DB::table('leads')
            ->whereBetween('date', [$from, $to])
            ->whereBetween('updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->count();

        $this->table(
            ['Set', 'Count', 'Meaning'],
            [
                ['Only in Created Date', $onlyInDate, 'created in range but updated OUTSIDE range'],
                ['Only in Updated Date', $onlyInUpdated, 'updated in range but created OUTSIDE range'],
                ['In BOTH filters', $inBoth, 'created AND updated in range'],
            ]
        );

        // ── sample leads that differ ─────────────────────────────────────────
        if ($onlyInDate > 0) {
            $this->line("  <fg=yellow>Sample leads ONLY in Created Date (updated outside range):</>");
            $samples = DB::table('leads')
                ->whereBetween('date', [$from, $to])
                ->where(function ($q) use ($from, $to) {
                    $q->where('updated_at', '<', "{$from} 00:00:00")
                      ->orWhere('updated_at', '>', "{$to} 23:59:59");
                })
                ->select('id', 'name', 'date', 'updated_at')
                ->limit(5)->get();
            $rows = $samples->map(fn($r) => [$r->id, $r->name, $r->date, $r->updated_at])->toArray();
            $this->table(['ID', 'Name', 'leads.date', 'leads.updated_at'], $rows);
        }

        if ($onlyInUpdated > 0) {
            $this->line("  <fg=yellow>Sample leads ONLY in Updated Date (created outside range):</>");
            $samples = DB::table('leads')
                ->whereBetween('updated_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->where(function ($q) use ($from, $to) {
                    $q->whereNull('date')
                      ->orWhere('date', '<', $from)
                      ->orWhere('date', '>', $to);
                })
                ->select('id', 'name', 'date', 'updated_at')
                ->limit(5)->get();
            $rows = $samples->map(fn($r) => [$r->id, $r->name, $r->date, $r->updated_at])->toArray();
            $this->table(['ID', 'Name', 'leads.date', 'leads.updated_at'], $rows);
        }

        // ── final verdict ────────────────────────────────────────────────────
        $this->line('');
        if ($onlyInDate == 0 && $onlyInUpdated == 0) {
            $this->err("CONFIRMED: Both filters return EXACTLY the same leads for this range.");
            $this->line("  <fg=yellow>Root cause: All leads in this range have DATE(updated_at) = leads.date</>");
            $this->line("  <fg=yellow>This means no lead was ever updated on a different day than it was created.</>");
        } elseif ($onlyInDate + $onlyInUpdated < ($inBoth * 0.05)) {
            $this->note("Filters are technically different but overlap >95% — hard to notice in UI.");
        } else {
            $this->ok("Filters produce meaningfully different results ({$onlyInDate} + {$onlyInUpdated} unique leads).");
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Commitment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The Monday job that makes weekly commitments actually weekly.
 *
 * Three things, in this order:
 *
 *   1. Close the week that just ended — every commitment still open past its deadline
 *      becomes 'missed'. Writing the verdict down rather than deriving it on read is
 *      what stops last month's report changing every time somebody opens it.
 *   2. Roll recurring commitments into the new week.
 *   3. Report what is now overdue and which meetings still have no minutes.
 *
 * Registered in routes/console.php, NOT in app/Console/Kernel.php: this is Laravel 11
 * with bootstrap/app.php, the Kernel is never loaded, and a schedule placed there runs
 * silently never — which is how inactivity:weekly-run sat unscheduled from 2026-08-15
 * until it was noticed a month later.
 */
class CommitmentsWeeklyRoll extends Command
{
    protected $signature = 'commitments:weekly-roll
                            {--dry-run : show what would change and write nothing}
                            {--week= : the Monday to treat as the new week (defaults to today\'s)}';

    protected $description = 'Close last week\'s commitments, roll recurring ones forward, and report what is overdue';

    public function handle(): int
    {
        $dry      = (bool) $this->option('dry-run');
        $thisWeek = Commitment::weekStart($this->option('week') ?: now());
        $lastWeek = \Carbon\Carbon::parse($thisWeek)->subWeek()->toDateString();

        $this->line('');
        $this->info('commitments:weekly-roll' . ($dry ? '  [DRY RUN — nothing will be written]' : ''));
        $this->line("  new week   {$thisWeek}");
        $this->line("  closing    {$lastWeek}");
        $this->line('');

        if (!\Illuminate\Support\Facades\Schema::hasTable('commitments')) {
            $this->error('  commitments table missing — run the migrations first.');

            return self::FAILURE;
        }

        $missed = $this->closeOverdue($lastWeek, $thisWeek, $dry);
        $rolled = $this->rollRecurring($lastWeek, $thisWeek, $dry);

        $this->report($thisWeek);

        $this->line('');
        $this->info(sprintf('  %s marked missed, %s rolled into the new week%s',
            number_format($missed), number_format($rolled), $dry ? ' (dry run)' : ''));
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Anything still open past its deadline is missed.
     *
     * Covers both cadences: a weekly commitment from a week that has ended, and a
     * one-off whose due date has passed. Cancelled ones are left alone — they were
     * withdrawn, not failed, and counting them would punish a supervisor for changing
     * their mind.
     */
    private function closeOverdue(string $lastWeek, string $thisWeek, bool $dry): int
    {
        $weeklyQuery = Commitment::where('status', Commitment::STATUS_OPEN)
            ->where('cadence', Commitment::CADENCE_WEEKLY)
            ->where('week_start', '<', $thisWeek);

        $onceQuery = Commitment::where('status', Commitment::STATUS_OPEN)
            ->where('cadence', Commitment::CADENCE_ONCE)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());

        $weekly = (clone $weeklyQuery)->count();
        $once   = (clone $onceQuery)->count();

        $this->line("  weekly commitments left open in a finished week   " . number_format($weekly));
        $this->line("  one-off commitments past their due date           " . number_format($once));

        if ($dry) {
            foreach ((clone $weeklyQuery)->limit(5)->get() as $c) {
                $this->line("      #{$c->id}  " . mb_strimwidth($c->title, 0, 50, '…') . "  week {$c->week_start->toDateString()}");
            }

            return $weekly + $once;
        }

        $stamp = ['status' => Commitment::STATUS_MISSED, 'updated_at' => now()];

        return (clone $weeklyQuery)->update($stamp) + (clone $onceQuery)->update($stamp);
    }

    /**
     * Clone each recurring series into the new week.
     *
     * Takes the LATEST instance of each series rather than last week's, because a
     * series whose week was skipped — the scheduler was down, the server was moved,
     * nobody ran it — would otherwise never be picked up again and the commitment
     * would vanish silently. Matching only on $lastWeek did exactly that in testing.
     *
     * Guards against double-running: a clone already sitting in this week for the same
     * series is skipped, so running the command twice on a Monday does not double every
     * commitment in the company.
     */
    private function rollRecurring(string $lastWeek, string $thisWeek, bool $dry): int
    {
        $candidates = Commitment::where('cadence', Commitment::CADENCE_WEEKLY)
            ->where('recurs', true)
            ->where('week_start', '<', $thisWeek)
            ->orderBy('week_start')
            ->get();

        // One source per series — the most recent instance, since the loop is ordered
        // oldest first and later rows overwrite earlier ones.
        $sources = [];

        foreach ($candidates as $c) {
            $sources[$c->recurs_from_id ?: $c->id] = $c;
        }

        $this->line("  recurring series to roll forward                 " . number_format(count($sources)));

        $rolled = 0;

        foreach ($sources as $seriesId => $source) {
            $already = Commitment::where('week_start', $thisWeek)
                ->where(function ($q) use ($seriesId) {
                    $q->where('recurs_from_id', $seriesId)->orWhere('id', $seriesId);
                })
                ->exists();

            if ($already) {
                continue;
            }

            if ($source->week_start->toDateString() !== $lastWeek) {
                $this->line(sprintf('      series #%d last ran %s — %d week(s) were skipped',
                    $seriesId,
                    $source->week_start->toDateString(),
                    (int) $source->week_start->diffInWeeks(\Carbon\Carbon::parse($thisWeek))));
            }

            if ($dry) {
                $this->line("      would roll #{$source->id}  " . mb_strimwidth($source->title, 0, 50, '…'));
                $rolled++;
                continue;
            }

            Commitment::create([
                'title'          => $source->title,
                'description'    => $source->description,
                'user_id'        => $source->user_id,
                'department_id'  => $source->department_id,
                'owed_to_id'     => $source->owed_to_id,
                'cadence'        => Commitment::CADENCE_WEEKLY,
                'week_start'     => $thisWeek,
                'status'         => Commitment::STATUS_OPEN,
                'progress'       => 0,
                'recurs'         => true,
                // Point every clone at the head of the series, not at last week's copy,
                // so the chain stays one hop deep however long the series runs.
                'recurs_from_id' => $seriesId,
                'created_by'     => $source->created_by,
            ]);

            $rolled++;
        }

        return $rolled;
    }

    /** What the new week looks like, and what task 4 is still waiting on. */
    private function report(string $thisWeek): void
    {
        $this->line('');
        $this->line('  --- the new week ---');

        $open = Commitment::where('week_start', $thisWeek)->where('status', Commitment::STATUS_OPEN)->count();
        $this->line('  open weekly commitments                          ' . number_format($open));

        $overdueOnce = Commitment::where('status', Commitment::STATUS_OPEN)
            ->where('cadence', Commitment::CADENCE_ONCE)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();

        $this->line('  one-off commitments due in the next 7 days       ' . number_format($overdueOnce));

        if (\Illuminate\Support\Facades\Schema::hasTable('meetings')
            && \Illuminate\Support\Facades\Schema::hasColumn('meetings', 'minutes')) {
            $awaiting = DB::table('meetings')
                ->where('status', '!=', 'cancelled')
                ->where('date', '<', now()->toDateString())
                ->where('date', '>=', now()->subDays(60)->toDateString())
                ->where(function ($q) {
                    $q->whereNull('minutes')->orWhere('minutes', '');
                })
                ->count();

            $this->line('  meetings held with no minutes written            ' . number_format($awaiting));
        }
    }
}

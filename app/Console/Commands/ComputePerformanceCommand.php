<?php

namespace App\Console\Commands;

use App\Services\PerformanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Computes employee_performance rows for a given date (default: yesterday).
 * Idempotent — re-running the same day overwrites the existing snapshot.
 *
 * Usage:
 *   php artisan activity:compute-performance              # yesterday for everyone with logs
 *   php artisan activity:compute-performance --date=2026-04-15
 *   php artisan activity:compute-performance --user=42 --date=2026-04-15
 */
class ComputePerformanceCommand extends Command
{
    protected $signature = 'activity:compute-performance {--date=} {--user=}';
    protected $description = 'Compute daily performance snapshots from task_logs.';

    public function handle(): int
    {
        $service = new PerformanceService();
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        if ($this->option('user')) {
            $userId = (int) $this->option('user');
            $row = $service->computeForUser($userId, $date);
            $this->info("Computed user={$userId} on {$date->toDateString()} → score=" . round($row->score, 4) . " stars={$row->stars}");
            return self::SUCCESS;
        }

        // Bulk: every user with at least one task_log row that day
        $count = $service->computeYesterdayForAll();
        $this->info("Computed performance for {$count} user(s) on " . $date->toDateString());
        return self::SUCCESS;
    }
}

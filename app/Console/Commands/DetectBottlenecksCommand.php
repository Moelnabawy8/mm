<?php

namespace App\Console\Commands;

use App\Services\BottleneckDetector;
use App\Services\TaskTimerService;
use Illuminate\Console\Command;

/**
 * Runs the BottleneckDetector AND auto-pauses stale timers.
 *
 * Two responsibilities in one cron tick because they're both 5-minute
 * cadence cleanup jobs and share a transaction-friendly window.
 *
 * Usage:
 *   php artisan activity:detect-bottlenecks
 */
class DetectBottlenecksCommand extends Command
{
    protected $signature = 'activity:detect-bottlenecks';
    protected $description = 'Detect operational bottlenecks AND auto-pause idle timers (run every 5 min).';

    public function handle(): int
    {
        $paused = (new TaskTimerService())->autoPauseStale();
        $this->info("Auto-paused {$paused} stale timer(s).");

        $result = (new BottleneckDetector())->run();
        $this->info("Bottleneck pass: " .
            "stage_stuck={$result['stage_stuck']} " .
            "task_delay={$result['task_delay']} " .
            "wip_overload={$result['wip_overload']} " .
            "auto_resolved={$result['auto_resolved']}");

        return self::SUCCESS;
    }
}

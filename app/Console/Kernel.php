<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('attendance:auto-checkout')->dailyAt('00:05');

        // Activity Tracking module ---------------------------------------
        // Compute yesterday's performance snapshot for every active employee.
        // Runs at 02:00 to avoid the midnight load and gives task_logs a
        // grace window for late writes.
        $schedule->command('activity:compute-performance')->dailyAt('02:00');

        // Detect bottlenecks every 5 minutes AND auto-pause stale timers.
        $schedule->command('activity:detect-bottlenecks')->everyFiveMinutes();

        // Marketing: refresh Facebook/Instagram Page tokens from the long-lived user
        // token daily so stored tokens never silently expire. No-op if META_USER_TOKEN
        // is not set. Runs at 03:00 (low traffic).
        $schedule->command('marketing:sync-meta-tokens')->dailyAt('03:00');

        // Marketing: pre-compute each account's all-time Meta totals (heavy: many API
        // calls) so the Real-time view shows grand totals instantly. Runs at 03:30,
        // after the token refresh.
        $schedule->command('marketing:compute-lifetime-totals')->dailyAt('03:30');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

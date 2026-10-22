<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Activity Tracking module ─────────────────────────────────────
// Compute yesterday's performance snapshot for every active employee
// every night at 02:00 (after a grace window for late task_log writes).
Schedule::command('activity:compute-performance')->dailyAt('02:00');

// Detect operational bottlenecks AND auto-pause stale (idle > 5min) timers
// every 5 minutes during the working day. The detector itself is idempotent
// so running it more often than needed is safe.
Schedule::command('activity:detect-bottlenecks')->everyFiveMinutes();



// Inactivity — weekly report to management. The command existed since
// 2026-08-15 but was never scheduled anywhere, so it had never actually run.
// Scheduling lives here, not in app/Console/Kernel.php: on Laravel 11 the
// console kernel is not loaded and anything scheduled there silently dies.
Schedule::command('inactivity:weekly-run')->weeklyOn(1, '08:00')->withoutOverlapping();

// Monday: close last week's commitments, roll recurring ones forward, and report
// what is overdue and which meetings still have no minutes.
Schedule::command('commitments:weekly-roll')->weeklyOn(1, '07:00')->withoutOverlapping();

if (config('academy_centers.security_checks_enabled')) {
    Schedule::command('academy:security-check')->dailyAt('03:00')->withoutOverlapping();
}

Schedule::command('academy:sync-cancellations')->dailyAt('03:10')->withoutOverlapping();

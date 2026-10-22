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

// Telegram task notifications ────────────────────────────────────
// Long-polling dispatcher: links chats via getUpdates (/start tokens) and drains
// the telegram_messages outbox. Every minute; the command itself bows out after
// ~50s (max-runtime) so ticks never overlap. Registered here — NOT in
// app/Console/Kernel.php, which is dead code on Laravel 11 (never loaded, so
// anything scheduled there silently dies). Verify with `php artisan schedule:list`.
//
// REQUIRES `php artisan schedule:run` every minute in the site user's crontab
// (crontab -u erpmy5243 -l). If schedule:run is not there, add a direct line
// instead, mirroring the whatsapp:dispatch cron entry.
Schedule::command('telegram:dispatch')->everyMinute()->withoutOverlapping();

// Due-date reminders for Telegram (today/tomorrow only — never overdue tasks).
Schedule::command('telegram:task-reminders')->dailyAt('08:00')->withoutOverlapping();

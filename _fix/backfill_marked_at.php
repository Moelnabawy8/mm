<?php

/**
 * backfill_marked_at — restore completion dates the old Done-drag bug destroyed.
 *
 * ProjectTaskController used to wipe project_tasks.marked_at whenever a card was
 * dragged into a completion stage (the bug its completed_stages comment describes).
 * 1,629 of 2,037 completed tasks carry no completion date as a result.
 *
 * The same action writes an activity_logs row — log_type 'Move Task', remark a JSON
 * blob naming old_stage and new_stage, created_at stamped at the moment of the move.
 * Where a task's LAST such row moved it into a stage flagged task_stages.complete = 1,
 * that row's date is the completion date.
 *
 * 575 of the 1,629 can be dated this way. The other 1,005 completed before the activity
 * log starts (2025-11-09) and are simply not recoverable — this script leaves them alone
 * rather than guessing from updated_at, which moves on any edit.
 *
 * Safety:
 *   - Writes ONLY where is_complete = 1 AND marked_at IS NULL. Never overwrites a date.
 *   - Skips any task whose recovered date precedes its created_at — that would mean the
 *     match is wrong, and one bad match is reason to distrust the row, not to round it.
 *   - Writes _fix/rollback_marked_at_<stamp>.sql before touching anything, and a CSV of
 *     every change, so the whole run can be undone and audited.
 *   - Runs inside a transaction.
 *
 *   php _fix/backfill_marked_at.php --dry-run     <- always run this first
 *   php _fix/backfill_marked_at.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$dry = in_array('--dry-run', $argv, true);

echo $dry ? "DRY RUN — nothing will be written\n\n" : "LIVE RUN\n\n";

if (!Schema::hasTable('activity_logs')) {
    fwrite(STDERR, "activity_logs does not exist — nothing to recover from.\n");
    exit(1);
}

/* ------------------------------------------------- which stages mean "finished" */

$names = Schema::hasColumn('task_stages', 'complete')
    ? DB::table('task_stages')->where('complete', 1)->pluck('name')
    : collect();

if ($names->isEmpty()) {
    fwrite(STDERR, "No stage carries task_stages.complete = 1 — refusing to guess.\n");
    exit(1);
}

$names = $names->map(fn($n) => trim($n))->unique()->values();

echo "completion stages: " . $names->implode(' | ') . "\n";

/* ----------------------------------------------------- find the recoverable set */

$undated = DB::table('project_tasks')
    ->where('is_complete', 1)
    ->whereNull('marked_at')
    ->get(['id', 'created_at'])
    ->keyBy('id');

echo "undated completed tasks: " . number_format($undated->count()) . "\n\n";

if ($undated->isEmpty()) {
    echo "Nothing to do.\n";
    exit(0);
}

$updates = [];
$skippedBeforeCreated = [];

foreach ($undated->keys()->chunk(500) as $chunk) {
    $logs = DB::table('activity_logs')
        ->where('log_type', 'Move Task')
        ->whereIn('task_id', $chunk->all())
        ->orderBy('created_at')
        ->get(['task_id', 'remark', 'created_at']);

    $last = [];

    foreach ($logs as $log) {
        $r = json_decode((string) $log->remark, true);

        if (!is_array($r) || !isset($r['new_stage'])) {
            continue;
        }

        foreach ($names as $n) {
            if (strcasecmp(trim((string) $r['new_stage']), $n) === 0) {
                $last[$log->task_id] = $log->created_at;   // ordered asc, so last wins
                break;
            }
        }
    }

    foreach ($last as $taskId => $when) {
        $date    = substr((string) $when, 0, 10);
        $created = substr((string) ($undated[$taskId]->created_at ?? ''), 0, 10);

        // A completion that predates the task is not a completion; it is a bad match.
        if ($created !== '' && $date < $created) {
            $skippedBeforeCreated[$taskId] = [$date, $created];
            continue;
        }

        $updates[$taskId] = $date;
    }
}

printf("recoverable      %s\n", number_format(count($updates)));
printf("skipped (date before the task existed)  %s\n", number_format(count($skippedBeforeCreated)));

foreach (array_slice($skippedBeforeCreated, 0, 5, true) as $tid => [$d, $c]) {
    printf("    #%-7s recovered %s but created %s\n", $tid, $d, $c);
}

if (!$updates) {
    echo "\nNothing to write.\n";
    exit(0);
}

/* ----------------------------------------------------------------- what changes */

$byYearMonth = [];

foreach ($updates as $date) {
    $byYearMonth[substr($date, 0, 7)] = ($byYearMonth[substr($date, 0, 7)] ?? 0) + 1;
}

krsort($byYearMonth);

echo "\ndates being restored, by month:\n";
foreach (array_slice($byYearMonth, 0, 14, true) as $m => $n) {
    printf("    %-9s %5s\n", $m, number_format($n));
}

$window = 180;
$cutoff = date('Y-m-d', strtotime("-{$window} days"));
$inWindow = count(array_filter($updates, fn($d) => $d >= $cutoff));

printf("\n%s of these land inside the %d-day review window — the quality queue grows by that much.\n",
    number_format($inWindow), $window);

if ($dry) {
    echo "\nDry run — nothing written. Re-run without --dry-run to apply.\n";
    exit(0);
}

/* ------------------------------------------------------------ rollback + record */

$stamp    = date('Ymd-His');
$rollback = __DIR__ . "/rollback_marked_at_{$stamp}.sql";
$csv      = __DIR__ . "/backfill_marked_at_{$stamp}.csv";

$ids = implode(',', array_map('intval', array_keys($updates)));

file_put_contents($rollback,
    "-- Undo backfill_marked_at run {$stamp}.\n"
  . "-- Every id below had marked_at = NULL before the run; this puts it back.\n"
  . "UPDATE project_tasks SET marked_at = NULL WHERE id IN ({$ids});\n");

$fh = fopen($csv, 'w');
fputcsv($fh, ['task_id', 'restored_marked_at', 'task_created_at']);

foreach ($updates as $taskId => $date) {
    fputcsv($fh, [$taskId, $date, substr((string) ($undated[$taskId]->created_at ?? ''), 0, 10)]);
}

fclose($fh);

echo "\nrollback  " . basename($rollback) . "\n";
echo "record    " . basename($csv) . "\n";

/* ----------------------------------------------------------------------- apply */

$written = 0;

DB::transaction(function () use ($updates, &$written) {
    foreach ($updates as $taskId => $date) {
        // The NULL guard is repeated here so a concurrent completion during the run
        // cannot be overwritten by a recovered date.
        $written += DB::table('project_tasks')
            ->where('id', $taskId)
            ->where('is_complete', 1)
            ->whereNull('marked_at')
            ->update(['marked_at' => $date]);
    }
});

printf("\nwrote %s rows\n", number_format($written));

$remaining = DB::table('project_tasks')->where('is_complete', 1)->whereNull('marked_at')->count();
$dated     = DB::table('project_tasks')->where('is_complete', 1)->whereNotNull('marked_at')->count();

printf("completed tasks now dated: %s   still undated: %s\n", number_format($dated), number_format($remaining));

echo "\nNow run:  php artisan optimize:clear\n";

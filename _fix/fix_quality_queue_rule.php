<?php

/**
 * fix_quality_queue_rule — stop treating updated_at as a completion date.
 *
 * QualityBoard counted a task as reviewable if marked_at fell in the window, OR if
 * marked_at was null and updated_at did. The fallback was meant to catch tasks
 * finished before completion dates were recorded. In production it catches almost
 * everything: the queue came out at 1,444 against a 90-day marked_at count of 201,
 * and every task on screen showed no completion date at all.
 *
 * updated_at moves on any edit — a rename, a stage change, a comment, a bulk touch —
 * so it never meant "finished recently". Worse, ProjectTaskController used to wipe
 * marked_at whenever a card was dragged into Done (see the comment around its
 * completed_stages block), so thousands of genuinely old tasks have no completion
 * date and a recent updated_at. Those are exactly the rows the fallback promoted.
 *
 * After this the queue is built on marked_at alone. A task with no completion date is
 * not "recently completed" — nobody can say when it was finished, so chasing a review
 * for it is noise. The screens say so rather than silently dropping them.
 *
 *   php _fix/fix_quality_queue_rule.php --dry-run
 *   php _fix/fix_quality_queue_rule.php
 */

$dry  = in_array('--dry-run', $argv, true);
$file = __DIR__ . '/../app/Services/Quality/QualityBoard.php';

if (!is_file($file)) {
    fwrite(STDERR, "QualityBoard.php not found — run from the Laravel root.\n");
    exit(1);
}

$raw = file_get_contents($file);
$eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
$src = str_replace("\r\n", "\n", $raw);

$old = <<<'OLD'
            ->where(function ($q) {
                $cutoff = now()->subDays(self::REVIEW_WINDOW_DAYS)->toDateString();
                $q->where('project_tasks.marked_at', '>=', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('project_tasks.marked_at')
                         ->where('project_tasks.updated_at', '>=', $cutoff);
                  });
            });
OLD;

$new = <<<'NEW'
            // marked_at only. updated_at moves on any edit, and a historical bug in
            // ProjectTaskController wiped marked_at on every card dragged into Done,
            // so "null marked_at + recent updated_at" describes thousands of old
            // tasks rather than recent work. Including it put 1,444 rows in a queue
            // whose honest size was a couple of hundred.
            ->whereNotNull('project_tasks.marked_at')
            ->where('project_tasks.marked_at', '>=', now()->subDays(self::REVIEW_WINDOW_DAYS)->toDateString());
NEW;

if (strpos($src, "whereNotNull('project_tasks.marked_at')") !== false) {
    echo "  already fixed — the queue is built on marked_at alone.\n";
    exit(0);
}

$hits = substr_count($src, $old);

if ($hits !== 1) {
    fwrite(STDERR, "  expected the old window clause exactly once, found {$hits} — refusing.\n");
    exit(1);
}

$src = str_replace($old, $new, $src);
echo "  completedTasksQuery(): dropped the updated_at fallback\n";

// The ordering clause also leaned on updated_at as a stand-in for a completion date.
$oldOrder = "->orderByRaw('COALESCE(project_tasks.marked_at, project_tasks.updated_at) DESC')";
$newOrder = "->orderByDesc('project_tasks.marked_at')";

if (strpos($src, $oldOrder) !== false) {
    $src = str_replace($oldOrder, $newOrder, $src);
    echo "  pendingTasks(): ordering now uses marked_at directly\n";
}

$tmp = sys_get_temp_dir() . '/qb_' . getmypid() . '.php';
file_put_contents($tmp, str_replace("\n", $eol, $src));
exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
unlink($tmp);

if ($rc !== 0) {
    fwrite(STDERR, "  PHP SYNTAX ERROR — nothing written:\n  " . implode("\n  ", $out) . "\n");
    exit(1);
}

echo "  result is valid PHP\n";

if ($dry) {
    echo "\n  dry run — nothing written.\n";
    exit(0);
}

copy($file, $file . '.bak-' . date('Ymd-His'));
file_put_contents($file, str_replace("\n", $eol, $src));
clearstatcache();

echo "\n  written app/Services/Quality/QualityBoard.php\n";
echo "\nNow run:  php artisan optimize:clear\n";

<?php

/**
 * audit_marked_at — how reliable is project_tasks.marked_at as a completion date?
 *
 * READ ONLY.
 *
 * QualityBoard treats a task as reviewable if marked_at falls in the window, OR if
 * marked_at is null and updated_at does. That fallback was meant as a safety net for
 * tasks completed before marked_at was recorded. On production it returns 1,444 tasks
 * where the 90-day marked_at count was 201, and every task on the Paid Marketing queue
 * shows no completion date at all — so the fallback is not a net, it is the whole catch.
 *
 * updated_at moves on any edit (rename, stage change, comment, bulk touch), so it does
 * not mean "finished recently". This measures how much of the queue rests on it.
 *
 *   php _audit/audit_marked_at.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

function head(string $t): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$t}\n" . str_repeat('=', 74) . "\n";
}

head('1. HOW MANY COMPLETED TASKS EVEN HAVE A COMPLETION DATE');

$total   = DB::table('project_tasks')->where('is_complete', 1)->count();
$withMA  = DB::table('project_tasks')->where('is_complete', 1)->whereNotNull('marked_at')->count();
$nullMA  = $total - $withMA;

printf("  completed tasks                 %s\n", number_format($total));
printf("  with marked_at                  %s  (%s%%)\n", number_format($withMA), $total ? round($withMA / $total * 100) : 0);
printf("  WITHOUT marked_at               %s  (%s%%)\n", number_format($nullMA), $total ? round($nullMA / $total * 100) : 0);

head('2. WHAT EACH RULE WOULD PUT IN THE QUEUE');

$base = fn() => DB::table('project_tasks as t')
    ->join('projects as p', 'p.id', '=', 't.project_id')
    ->where('t.is_complete', 1)
    ->whereNotNull('p.department_id')
    ->where('p.department_id', '>', 0);

foreach ([30, 90, 180, 365] as $days) {
    $cut = now()->subDays($days)->toDateString();

    $strict = (clone $base())->where('t.marked_at', '>=', $cut)->count();

    $loose = (clone $base())->where(function ($q) use ($cut) {
        $q->where('t.marked_at', '>=', $cut)
          ->orWhere(function ($q2) use ($cut) {
              $q2->whereNull('t.marked_at')->where('t.updated_at', '>=', $cut);
          });
    })->count();

    printf("  last %3d days   marked_at only: %6s   with updated_at fallback: %6s   (fallback adds %s)\n",
        $days, number_format($strict), number_format($loose), number_format($loose - $strict));
}

head('3. WHEN DID THE updated_at-ONLY TASKS ACTUALLY GET TOUCHED');

echo "  If these cluster on a handful of days, something bulk-edited them and the\n";
echo "  date says nothing about when the work was finished.\n\n";

$rows = DB::table('project_tasks')
    ->where('is_complete', 1)
    ->whereNull('marked_at')
    ->select(DB::raw('DATE(updated_at) as d'), DB::raw('COUNT(*) as n'))
    ->groupBy(DB::raw('DATE(updated_at)'))
    ->orderByDesc('n')
    ->limit(12)
    ->get();

foreach ($rows as $r) {
    printf("    %-12s %6s tasks\n", $r->d, number_format($r->n));
}

head('4. THE SAME, FOR TASKS THAT DO HAVE marked_at (the honest signal)');

$rows = DB::table('project_tasks')
    ->where('is_complete', 1)
    ->whereNotNull('marked_at')
    ->select(DB::raw('DATE_FORMAT(marked_at, "%Y-%m") as m'), DB::raw('COUNT(*) as n'))
    ->groupBy(DB::raw('DATE_FORMAT(marked_at, "%Y-%m")'))
    ->orderByDesc('m')
    ->limit(10)
    ->get();

foreach ($rows as $r) {
    printf("    %-10s %6s tasks\n", $r->m, number_format($r->n));
}

head('5. DOES THE APP STILL RECORD marked_at? (recent completions)');

$recent = DB::table('project_tasks')
    ->where('is_complete', 1)
    ->where('updated_at', '>=', now()->subDays(60))
    ->select(
        DB::raw('SUM(marked_at IS NOT NULL) as with_date'),
        DB::raw('SUM(marked_at IS NULL) as without_date')
    )
    ->first();

printf("  completed tasks touched in the last 60 days:\n");
printf("    with marked_at     %s\n", number_format($recent->with_date ?? 0));
printf("    without marked_at  %s\n", number_format($recent->without_date ?? 0));
echo "\n  (if 'without' dominates here too, completion simply is not being dated and the\n"
   . "   queue has to be built on something else)\n";

echo "\n" . str_repeat('=', 74) . "\n  done — nothing was written\n" . str_repeat('=', 74) . "\n";

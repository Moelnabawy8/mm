<?php
/**
 * audit_task1_shape — the three things still unknown before designing the
 * per-department Quality feature.
 *
 * READ ONLY. No writes.
 *
 *   1. Do the duplicate departments actually carry work? If projects only ever
 *      use one id per name, the duplicates are dead rows and the feature can
 *      ignore them. If work is split across both, they have to be merged first
 *      or every department score will be wrong.
 *   2. Are milestones usable as "deliverables", or is the task the only real unit?
 *   3. Who would do the reviewing — is there an existing QA role, and does the
 *      completed-task volume make per-task review realistic?
 *
 *   php _audit/audit_task1_shape.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function head(string $t): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$t}\n" . str_repeat('=', 74) . "\n";
}

head('1. WHICH DEPARTMENT IDS ACTUALLY CARRY WORK');

$rows = DB::table('departments as d')
    ->leftJoin('projects as p', 'p.department_id', '=', 'd.id')
    ->select('d.id', 'd.name', DB::raw('COUNT(p.id) as projects'))
    ->groupBy('d.id', 'd.name')
    ->orderBy('d.name')
    ->orderBy('d.id')
    ->get();

$tasksByDept = DB::table('project_tasks as t')
    ->join('projects as p', 'p.id', '=', 't.project_id')
    ->select('p.department_id', DB::raw('COUNT(*) as n'), DB::raw('SUM(t.is_complete = 1) as done'))
    ->groupBy('p.department_id')
    ->pluck('n', 'department_id');

$doneByDept = DB::table('project_tasks as t')
    ->join('projects as p', 'p.id', '=', 't.project_id')
    ->where('t.is_complete', 1)
    ->select('p.department_id', DB::raw('COUNT(*) as n'))
    ->groupBy('p.department_id')
    ->pluck('n', 'department_id');

$empByDept = Schema::hasColumn('employees', 'department_id')
    ? DB::table('employees')->select('department_id', DB::raw('COUNT(*) as n'))->groupBy('department_id')->pluck('n', 'department_id')
    : collect();

printf("  %-4s %-30s %9s %9s %9s %6s\n", 'id', 'name', 'projects', 'tasks', 'done', 'staff');
echo '  ' . str_repeat('-', 70) . "\n";

$byName = [];
foreach ($rows as $r) {
    printf("  %-4d %-30s %9s %9s %9s %6s\n",
        $r->id,
        mb_strimwidth($r->name ?? '(unnamed)', 0, 30, '…'),
        number_format($r->projects),
        number_format($tasksByDept[$r->id] ?? 0),
        number_format($doneByDept[$r->id] ?? 0),
        number_format($empByDept[$r->id] ?? 0));

    $byName[mb_strtolower(trim($r->name ?? ''))][] = $r->id;
}

echo "\n  duplicate names:\n";
$dupes = array_filter($byName, fn($ids) => count($ids) > 1);

if (!$dupes) {
    echo "    none\n";
}

foreach ($dupes as $name => $ids) {
    $live = array_values(array_filter($ids, fn($i) =>
        ($tasksByDept[$i] ?? 0) > 0 || ($empByDept[$i] ?? 0) > 0));

    printf("    %-26s ids %-18s carrying work: %s\n",
        $name,
        implode(', ', $ids),
        $live ? implode(', ', $live) : 'NONE (all dead rows)');
}

head('2. MILESTONES — usable as deliverables?');

printf("  milestones total                 %s\n", number_format(DB::table('milestones')->count()));
printf("  projects that have at least one  %s of %s\n",
    number_format(DB::table('milestones')->distinct()->count('project_id')),
    number_format(DB::table('projects')->count()));

$linked = DB::table('project_tasks')->where('milestone_id', '>', 0)->count();
printf("  tasks attached to a milestone    %s of %s\n",
    number_format($linked),
    number_format(DB::table('project_tasks')->count()));

echo "\n  (if this last number is small, the milestone is decorative here and the\n"
   . "   reviewable unit has to be the task itself)\n";

head('3. VOLUME — is per-task review realistic?');

$since = now()->subDays(90)->toDateString();

$recent = DB::table('project_tasks')
    ->where('is_complete', 1)
    ->whereNotNull('marked_at')
    ->where('marked_at', '>=', $since)
    ->count();

printf("  tasks completed in the last 90 days   %s  (~%s / week)\n",
    number_format($recent),
    number_format(round($recent / 13, 1), 1));

echo "\n  completions per department, last 90 days:\n";

$perDept = DB::table('project_tasks as t')
    ->join('projects as p', 'p.id', '=', 't.project_id')
    ->join('departments as d', 'd.id', '=', 'p.department_id')
    ->where('t.is_complete', 1)
    ->whereNotNull('t.marked_at')
    ->where('t.marked_at', '>=', $since)
    ->select('d.id', 'd.name', DB::raw('COUNT(*) as n'))
    ->groupBy('d.id', 'd.name')
    ->orderByDesc('n')
    ->get();

if ($perDept->isEmpty()) {
    echo "    none — marked_at may not be populated; falling back to updated_at\n";

    $perDept = DB::table('project_tasks as t')
        ->join('projects as p', 'p.id', '=', 't.project_id')
        ->join('departments as d', 'd.id', '=', 'p.department_id')
        ->where('t.is_complete', 1)
        ->where('t.updated_at', '>=', $since)
        ->select('d.id', 'd.name', DB::raw('COUNT(*) as n'))
        ->groupBy('d.id', 'd.name')
        ->orderByDesc('n')
        ->get();
}

foreach ($perDept as $d) {
    printf("    %4d  %-30s %6s\n", $d->id, mb_strimwidth($d->name, 0, 30, '…'), number_format($d->n));
}

head('4. WHO COULD REVIEW — roles as they exist');

foreach (DB::table('roles')->orderBy('name')->get() as $r) {
    $n = DB::table('model_has_roles')->where('role_id', $r->id)->count();
    printf("  %-34s %4s users\n", $r->name, number_format($n));
}

echo "\n" . str_repeat('=', 74) . "\n  done — nothing was written\n" . str_repeat('=', 74) . "\n";

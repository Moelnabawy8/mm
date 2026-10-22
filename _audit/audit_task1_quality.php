<?php
/**
 * audit_task1_quality — what does production already have for "a Quality section
 * inside each department, linked to tasks/deliverables"?
 *
 * READ ONLY. No writes, no migrations, no cache clearing. Safe to run on prod.
 *
 * The local checkout has a `quality_evaluations` module, but it evaluates sales
 * *leads* (university major, course fit, is_potential_lead) and has no department
 * and no task. The names collide; the features do not. This script asks the server
 * directly rather than inferring anything from local code — the inactivity system
 * was built straight onto the server once already and existed nowhere in the repo.
 *
 *   php _audit/audit_task1_quality.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

function head(string $t): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$t}\n" . str_repeat('=', 74) . "\n";
}

function yn(bool $b, string $yes = 'YES', string $no = 'no'): string
{
    return $b ? $yes : $no;
}

head('1. TABLES — is there any department-level quality storage?');

$tables = array_map(
    fn($r) => array_values((array) $r)[0],
    DB::select('SHOW TABLES')
);

$qualityish = array_values(array_filter(
    $tables,
    fn($t) => preg_match('/qualit|kpi|evaluat|score|rating|review|deliver|commit/i', $t)
));

if (!$qualityish) {
    echo "  none\n";
}

foreach ($qualityish as $t) {
    $n    = DB::table($t)->count();
    $cols = array_map(fn($c) => $c->Field, DB::select("SHOW COLUMNS FROM `{$t}`"));

    printf("  %-34s %7s rows\n", $t, number_format($n));
    echo "      " . implode(', ', $cols) . "\n";

    // The two links the task actually requires.
    $hasDept = (bool) preg_grep('/^department_id$/', $cols);
    $hasTask = (bool) preg_grep('/^(project_)?task_id$/', $cols);
    echo "      linked to a department: " . yn($hasDept)
       . "   |   linked to a task: " . yn($hasTask) . "\n\n";
}

head('2. ROUTES — anything quality-shaped already reachable?');

$names = [];
foreach (Route::getRoutes() as $r) {
    if ($r->getName() && preg_match('/qualit|kpi|evaluat|score|review/i', $r->getName())) {
        $names[] = sprintf('  %-8s %-46s %s', implode('|', $r->methods()), $r->uri(), $r->getName());
    }
}

echo $names ? implode("\n", array_unique($names)) . "\n" : "  none\n";

head('3. PERMISSIONS — who can see a quality feature today?');

foreach (DB::table('permissions')->where('name', 'like', '%qualit%')->orderBy('name')->get() as $p) {
    $roles = DB::table('role_has_permissions')
        ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
        ->where('role_has_permissions.permission_id', $p->id)
        ->pluck('roles.name')
        ->implode(', ');

    printf("  %-34s  %s\n", $p->name, $roles !== '' ? $roles : '(no role)');
}

head('4. FILES — is there server-only quality code the repo never saw?');

$roots = ['app/Http/Controllers', 'app/Models', 'app/Services', 'app/Support', 'resources/views'];
$found = [];

foreach ($roots as $rel) {
    $dir = __DIR__ . '/../' . $rel;
    if (!is_dir($dir)) {
        continue;
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $f) {
        if (!$f->isFile() || !preg_match('/qualit|kpi|evaluat/i', $f->getFilename())) {
            continue;
        }
        $found[] = sprintf('  %9s  %s', number_format($f->getSize()), $rel . '/' . $f->getBasename());
    }
}

echo $found ? implode("\n", $found) . "\n" : "  none\n";

head('5. THE GROUND TO BUILD ON — departments, projects, tasks');

printf("  departments                       %s\n", number_format(DB::table('departments')->count()));

$empDept = Schema::hasColumn('employees', 'department_id')
    ? DB::table('employees')->whereNotNull('department_id')->where('department_id', '>', 0)->count()
    : null;
printf("  employees with a department       %s\n", $empDept === null ? 'column missing' : number_format($empDept));

$projDept = Schema::hasColumn('projects', 'department_id')
    ? DB::table('projects')->whereNotNull('department_id')->where('department_id', '>', 0)->count()
    : null;
printf("  projects with a department        %s  of %s\n",
    $projDept === null ? 'column missing' : number_format($projDept),
    number_format(DB::table('projects')->count()));

printf("  project_tasks                     %s  (%s complete)\n",
    number_format(DB::table('project_tasks')->count()),
    number_format(DB::table('project_tasks')->where('is_complete', 1)->count()));

printf("  milestones (nearest deliverable)  %s\n", number_format(DB::table('milestones')->count()));

// Can a task be traced to a department at all today? That path is what the feature needs.
if (Schema::hasColumn('projects', 'department_id')) {
    $traceable = DB::table('project_tasks')
        ->join('projects', 'projects.id', '=', 'project_tasks.project_id')
        ->whereNotNull('projects.department_id')
        ->where('projects.department_id', '>', 0)
        ->count();

    printf("\n  tasks reachable from a department via project: %s\n", number_format($traceable));
    echo "  (this is the join the feature would hang on — if it is near zero,\n"
       . "   department_id on projects is not being filled in and the link has\n"
       . "   to come from the assignee's employee record instead)\n";
}

head('6. DEPARTMENTS AS THEY ACTUALLY ARE');

foreach (DB::table('departments')->orderBy('id')->get() as $d) {
    $n = Schema::hasColumn('employees', 'department_id')
        ? DB::table('employees')->where('department_id', $d->id)->count()
        : 0;
    printf("  %4d  %-38s %3s employees\n", $d->id, $d->name ?? '(unnamed)', $n);
}

echo "\n" . str_repeat('=', 74) . "\n  done — nothing was written\n" . str_repeat('=', 74) . "\n";

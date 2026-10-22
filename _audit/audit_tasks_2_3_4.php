<?php

/**
 * audit_tasks_2_3_4 — what production already has for commitments and meetings.
 *
 * READ ONLY. Nothing is written.
 *
 * Task 2 — commitments toward supervisors          (due 20/09)
 * Task 3 — weekly commitments per department/employee (due 21/09)
 * Task 4 — meeting scheduling, ensuring minutes are recorded (due 22/09)
 *
 * The local checkout suggests: `goals` is a financial target table (name, type, amount),
 * `meetings` holds title/date/time/note with no minutes, attendance or decisions, and
 * NOTHING anywhere models who supervises whom. But the inactivity system was built
 * straight onto this server and existed nowhere in the repo, so none of that is trusted
 * until the server says so.
 *
 *   php _audit/audit_tasks_2_3_4.php
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

$tables = array_map(fn($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));

head('1. SERVER-ONLY TABLES — anything already built for these three?');

$interesting = array_values(array_filter($tables, fn($t) => preg_match(
    '/commit|pledge|obligat|weekly|okr|goal|target|objective|minute|agenda|attend|supervis/i', $t
)));

if (!$interesting) {
    echo "  none\n";
}

foreach ($interesting as $t) {
    $n    = DB::table($t)->count();
    $cols = array_map(fn($c) => $c->Field, DB::select("SHOW COLUMNS FROM `{$t}`"));
    printf("  %-34s %8s rows\n", $t, number_format($n));
    echo "      " . implode(', ', $cols) . "\n\n";
}

head('2. IS `goals` A COMMITMENT TABLE OR A FINANCIAL ONE?');

if (Schema::hasTable('goals')) {
    printf("  rows %s\n", number_format(DB::table('goals')->count()));

    foreach (DB::table('goals')->orderByDesc('id')->limit(6)->get() as $g) {
        printf("    %-30s type=%-14s amount=%s\n",
            mb_strimwidth((string) ($g->name ?? ''), 0, 29, '…'),
            (string) ($g->type ?? ''),
            number_format((float) ($g->amount ?? 0), 2));
    }

    echo "\n  (an amount column carrying money means this is an accounting target,\n"
       . "   not a commitment somebody owes a supervisor)\n";
} else {
    echo "  no goals table\n";
}

head('3. MEETINGS — how much is actually used, and is anything recorded after?');

foreach (['meetings', 'meeting_employees', 'zoom_meetings', 'sales_meetings'] as $t) {
    if (!Schema::hasTable($t)) {
        printf("  %-22s (missing)\n", $t);
        continue;
    }
    printf("  %-22s %8s rows\n", $t, number_format(DB::table($t)->count()));
}

if (Schema::hasTable('meetings')) {
    $cols = array_map(fn($c) => $c->Field, DB::select('SHOW COLUMNS FROM meetings'));
    echo "\n  meetings columns: " . implode(', ', $cols) . "\n";

    $withNote = in_array('note', $cols, true)
        ? DB::table('meetings')->whereNotNull('note')->where('note', '!=', '')->count()
        : null;

    printf("\n  total meetings            %s\n", number_format(DB::table('meetings')->count()));
    printf("  with a note filled in     %s\n", $withNote === null ? 'no note column' : number_format($withNote));

    if (in_array('date', $cols, true)) {
        printf("  in the last 90 days       %s\n",
            number_format(DB::table('meetings')->where('date', '>=', now()->subDays(90)->toDateString())->count()));
        printf("  dated in the future       %s\n",
            number_format(DB::table('meetings')->where('date', '>', now()->toDateString())->count()));
    }

    echo "\n  most recent:\n";
    foreach (DB::table('meetings')->orderByDesc('date')->limit(5)->get() as $m) {
        printf("    %-11s %-38s note: %s\n",
            (string) ($m->date ?? '—'),
            mb_strimwidth((string) ($m->title ?? ''), 0, 37, '…'),
            trim((string) ($m->note ?? '')) === '' ? 'EMPTY' : strlen((string) $m->note) . ' chars');
    }

    echo "\n  (if note is empty on nearly everything, minutes are not being kept —\n"
       . "   which is exactly what task 4 asks the system to enforce)\n";
}

head('4. WHO SUPERVISES WHOM — does any relationship exist at all?');

$found = [];

foreach (['users', 'employees', 'departments', 'designations'] as $t) {
    if (!Schema::hasTable($t)) {
        continue;
    }

    foreach (DB::select("SHOW COLUMNS FROM `{$t}`") as $c) {
        if (preg_match('/supervis|manager|reports_to|parent_id|head|lead/i', $c->Field)) {
            $found[] = "{$t}.{$c->Field}  ({$c->Type})";
        }
    }
}

if ($found) {
    echo "  candidate columns:\n";
    foreach ($found as $f) {
        echo "    {$f}\n";
    }
} else {
    echo "  NONE. No table models a supervisor, a manager, or a reporting line.\n";
    echo "  Task 2 needs that relationship to exist before a commitment can be owed\n";
    echo "  to anyone — it has to be added, and somebody has to fill it in.\n";
}

echo "\n  designations (job titles) in use:\n";

if (Schema::hasTable('designations')) {
    $rows = DB::table('designations as d')
        ->leftJoin('employees as e', 'e.designation_id', '=', 'd.id')
        ->select('d.id', 'd.name', DB::raw('COUNT(e.id) as n'))
        ->groupBy('d.id', 'd.name')
        ->having('n', '>', 0)
        ->orderByDesc('n')
        ->limit(20)
        ->get();

    foreach ($rows as $r) {
        printf("    %4d  %-38s %3s employees\n", $r->id, mb_strimwidth((string) $r->name, 0, 37, '…'), $r->n);
    }

    if ($rows->isEmpty()) {
        echo "    none assigned\n";
    }
}

head('5. REMINDER INFRASTRUCTURE — what can already notify people?');

foreach ([
    'app/Support/WhatsApp/TaskNotifier.php'      => 'WhatsApp task notifier',
    'app/Services/WhatsApp/MetaCloudClient.php'  => 'Meta Cloud client',
    'app/Console/Commands/WhatsAppTaskReminders.php' => 'WhatsApp reminder command',
    'app/Mail/InactivityReportMail.php'          => 'mail example to copy',
] as $rel => $label) {
    printf("  %-28s %s\n", $label, is_file(__DIR__ . '/../' . $rel) ? 'present' : 'MISSING');
}

if (Schema::hasTable('whatsapp_messages')) {
    printf("\n  whatsapp_messages rows      %s\n", number_format(DB::table('whatsapp_messages')->count()));
    $cols = array_map(fn($c) => $c->Field, DB::select('SHOW COLUMNS FROM whatsapp_messages'));

    if (in_array('status', $cols, true)) {
        foreach (DB::table('whatsapp_messages')->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->get() as $s) {
            printf("    %-14s %s\n", (string) $s->status, number_format($s->n));
        }
    }
}

$users = Schema::hasColumn('users', 'whatsapp_number')
    ? DB::table('users')->whereNotNull('whatsapp_number')->where('whatsapp_number', '!=', '')->count()
    : null;

printf("\n  users with a WhatsApp number  %s\n", $users === null ? 'column missing' : number_format($users));

head('6. SCHEDULED WORK — what already runs on a timer');

echo "  (task 3 is weekly by definition, so this is where it would hang)\n\n";

$console = __DIR__ . '/../routes/console.php';

if (is_file($console)) {
    foreach (file($console) as $i => $line) {
        if (stripos($line, 'Schedule::') !== false) {
            printf("    console.php:%-4d %s\n", $i + 1, trim($line));
        }
    }
} else {
    echo "    routes/console.php missing\n";
}

head('7. ROUTES ALREADY CLAIMED BY THESE WORDS');

$seen = [];

foreach (Route::getRoutes() as $r) {
    if ($r->getName() && preg_match('/commit|goal|meeting|minute|weekly/i', $r->getName())) {
        $seen[$r->getName()] = sprintf('  %-46s %s', $r->uri(), $r->getName());
    }
}

ksort($seen);
echo $seen ? implode("\n", $seen) . "\n" : "  none\n";
printf("\n  (%d route names matched)\n", count($seen));

echo "\n" . str_repeat('=', 74) . "\n  done — nothing was written\n" . str_repeat('=', 74) . "\n";

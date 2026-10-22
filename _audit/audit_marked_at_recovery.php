<?php

/**
 * audit_marked_at_recovery — can the lost completion dates be recovered?
 *
 * READ ONLY. Nothing is written.
 *
 * 1,629 of 2,037 completed tasks carry no marked_at. ProjectTaskController used to
 * wipe it whenever a card was dragged into Done (the bug its completed_stages comment
 * describes), so the dates were destroyed rather than never recorded.
 *
 * But the same action writes an activity_logs row: log_type 'Move Task', remark a JSON
 * blob holding old_stage and new_stage, and created_at stamped at the moment of the
 * move. Where a task's last such row names a completion stage, that row's created_at
 * IS the completion date.
 *
 * This measures how many of the 1,629 can be dated that way, and shows samples so the
 * result can be judged before anyone writes to production.
 *
 *   php _audit/audit_marked_at_recovery.php
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

head('1. IS THERE AN ACTIVITY LOG TO RECOVER FROM');

if (!Schema::hasTable('activity_logs')) {
    echo "  activity_logs table does not exist — nothing to recover from.\n";
    exit(0);
}

printf("  activity_logs rows              %s\n", number_format(DB::table('activity_logs')->count()));
printf("  'Move Task' rows                %s\n", number_format(DB::table('activity_logs')->where('log_type', 'Move Task')->count()));
printf("  distinct tasks with a move      %s\n", number_format(DB::table('activity_logs')->where('log_type', 'Move Task')->distinct()->count('task_id')));

$oldest = DB::table('activity_logs')->where('log_type', 'Move Task')->min('created_at');
$newest = DB::table('activity_logs')->where('log_type', 'Move Task')->max('created_at');
printf("  covering                        %s .. %s\n", $oldest ?: '—', $newest ?: '—');

head('2. WHICH STAGE NAMES COUNT AS COMPLETION');

// The activity log stores the stage NAME, not its id, so the match has to be by name.
// task_stages.complete is the authoritative flag — the same one TaskStage::completedIds()
// uses — and name matching is only the fallback it also falls back to. Stage names are
// per-creator here, so several rows can share a name and all of them count.
$flagged = Schema::hasColumn('task_stages', 'complete')
    ? DB::table('task_stages')->where('complete', 1)->get(['id', 'name'])
    : collect();

if ($flagged->isNotEmpty()) {
    echo "  from the task_stages.complete flag:\n";
    $completionStages = $flagged;
} else {
    echo "  no stage carries the complete flag — falling back to name matching:\n";
    $completionStages = DB::table('task_stages')
        ->where(function ($q) {
            foreach (['complete', 'completed', 'done', 'finish', 'finished', 'delivered'] as $w) {
                $q->orWhere('name', 'like', "%{$w}%");
            }
        })
        ->get(['id', 'name']);
}

if ($completionStages->isEmpty()) {
    echo "  nothing matched — listing every stage so the rule can be set by hand:\n";
    foreach (DB::table('task_stages')->orderBy('name')->get(['id', 'name']) as $s) {
        printf("    %4d  %s\n", $s->id, $s->name);
    }
} else {
    foreach ($completionStages as $s) {
        printf("    %4d  %s\n", $s->id, $s->name);
    }
}

$names = $completionStages->pluck('name')->map(fn($n) => trim($n))->unique()->values()->all();

printf("\n  matching move-to names: %s\n", $names ? implode(' | ', $names) : '(none)');

head('3. HOW MANY UNDATED COMPLETED TASKS HAVE A MOVE-TO-DONE ROW');

$undated = DB::table('project_tasks')
    ->where('is_complete', 1)
    ->whereNull('marked_at')
    ->pluck('id');

printf("  undated completed tasks         %s\n", number_format($undated->count()));

if ($undated->isEmpty() || !$names) {
    echo "  nothing to match.\n";
    exit(0);
}

$recoverable = 0;
$samples     = [];
$noMove      = 0;
$movedButNotDone = 0;

foreach ($undated->chunk(500) as $chunk) {
    $logs = DB::table('activity_logs')
        ->where('log_type', 'Move Task')
        ->whereIn('task_id', $chunk->all())
        ->orderBy('created_at')
        ->get(['task_id', 'remark', 'created_at']);

    $lastDoneMove = [];
    $anyMove      = [];

    foreach ($logs as $log) {
        $anyMove[$log->task_id] = true;

        $r = json_decode((string) $log->remark, true);

        if (!is_array($r) || !isset($r['new_stage'])) {
            continue;
        }

        foreach ($names as $n) {
            if (strcasecmp(trim($r['new_stage']), $n) === 0) {
                $lastDoneMove[$log->task_id] = $log->created_at;
                break;
            }
        }
    }

    foreach ($chunk as $tid) {
        if (isset($lastDoneMove[$tid])) {
            $recoverable++;

            if (count($samples) < 10) {
                $samples[$tid] = $lastDoneMove[$tid];
            }
        } elseif (isset($anyMove[$tid])) {
            $movedButNotDone++;
        } else {
            $noMove++;
        }
    }
}

printf("\n  RECOVERABLE (last move was into a completion stage)  %s  (%s%%)\n",
    number_format($recoverable),
    $undated->count() ? round($recoverable / $undated->count() * 100) : 0);
printf("  moved, but never into a completion stage             %s\n", number_format($movedButNotDone));
printf("  no 'Move Task' row at all                            %s\n", number_format($noMove));

head('4. SAMPLES — judge these before trusting the rest');

if (!$samples) {
    echo "  none\n";
} else {
    printf("  %-8s %-46s %-12s %s\n", 'task', 'name', 'recovered', 'created');
    foreach ($samples as $tid => $when) {
        $t = DB::table('project_tasks')->where('id', $tid)->first(['name', 'created_at', 'end_date']);
        printf("  #%-7s %-46s %-12s %s\n",
            $tid,
            mb_strimwidth((string) ($t->name ?? '?'), 0, 45, '…'),
            substr((string) $when, 0, 10),
            substr((string) ($t->created_at ?? '—'), 0, 10));
    }
    echo "\n  A recovered date BEFORE the task was created would mean the match is wrong.\n";
}

head('5. WOULD RECOVERY ACTUALLY GROW THE REVIEW QUEUE');

echo "  Recovered dates only matter if they land inside the review window.\n\n";

$inWindow = [];

foreach ([30, 90, 180, 365] as $days) {
    $inWindow[$days] = 0;
}

foreach ($undated->chunk(500) as $chunk) {
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
            if (strcasecmp(trim($r['new_stage']), $n) === 0) {
                $last[$log->task_id] = $log->created_at;
                break;
            }
        }
    }

    foreach ($last as $when) {
        foreach ([30, 90, 180, 365] as $days) {
            if ($when >= now()->subDays($days)->toDateTimeString()) {
                $inWindow[$days]++;
            }
        }
    }
}

foreach ($inWindow as $days => $n) {
    printf("  recovered dates within %3d days   %s\n", $days, number_format($n));
}

echo "\n" . str_repeat('=', 74) . "\n  done — nothing was written\n" . str_repeat('=', 74) . "\n";

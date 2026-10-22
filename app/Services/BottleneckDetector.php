<?php

namespace App\Services;

use App\Models\BottleneckAlert;
use App\Models\ProjectTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BottleneckDetector — runs every 5 minutes and inserts/resolves alerts.
 *
 * Three rules (Conservative thresholds, locked in by user):
 *   stage_stuck:   COUNT tasks per stage > 10
 *   task_delay:    elapsed > 1.5 * estimated_hrs (for tasks not yet complete)
 *   wip_overload:  COUNT in-progress tasks per user > 5
 *
 * Severity scaling (all rules):
 *   1.0× < ratio ≤ 1.5×  → low
 *   1.5× < ratio ≤ 2.5×  → medium
 *   ratio > 2.5×          → high
 */
class BottleneckDetector
{
    public const STAGE_STUCK_THRESHOLD   = 10;
    public const TASK_DELAY_RATIO        = 1.5;
    public const WIP_OVERLOAD_THRESHOLD  = 5;

    /**
     * Run all three detectors. Returns counts per type.
     */
    public function run(): array
    {
        return [
            'stage_stuck'   => $this->detectStageStuck(),
            'task_delay'    => $this->detectTaskDelay(),
            'wip_overload'  => $this->detectWipOverload(),
            'auto_resolved' => $this->autoResolveCleared(),
        ];
    }

    private function detectStageStuck(): int
    {
        $count = 0;
        $rows = DB::table('project_tasks')
            ->select('stage_id', DB::raw('COUNT(*) as task_count'))
            ->whereNotNull('stage_id')
            ->where(function ($q) {
                $q->where('is_complete', 0)->orWhereNull('is_complete');
            })
            ->groupBy('stage_id')
            ->having('task_count', '>', self::STAGE_STUCK_THRESHOLD)
            ->get();
        foreach ($rows as $row) {
            $ratio = $row->task_count / self::STAGE_STUCK_THRESHOLD;
            $stageName = DB::table('task_stages')->where('id', $row->stage_id)->value('name') ?: ('#' . $row->stage_id);
            $this->upsertAlert(
                'stage_stuck', 'stage', (int) $row->stage_id,
                $this->severityFromRatio($ratio),
                "{$row->task_count} tasks parked in stage \"{$stageName}\" (threshold: " . self::STAGE_STUCK_THRESHOLD . ")"
            );
            $count++;
        }
        return $count;
    }

    private function detectTaskDelay(): int
    {
        $count = 0;
        $now   = now();
        // Only check tasks that are NOT complete and have an estimate
        $tasks = DB::table('project_tasks')
            ->whereNotNull('estimated_hrs')
            ->where('estimated_hrs', '>', 0)
            ->whereNotNull('start_date')
            ->where(function ($q) {
                $q->where('is_complete', 0)->orWhereNull('is_complete');
            })
            ->select('id', 'name', 'start_date', 'end_date', 'estimated_hrs')
            ->get();

        foreach ($tasks as $t) {
            $elapsed = max(0, Carbon::parse($t->start_date)->diffInHours($now, false));
            if ($elapsed <= 0) continue;
            $ratio = $elapsed / max(1, (float) $t->estimated_hrs);
            if ($ratio < self::TASK_DELAY_RATIO) {
                // Auto-resolve if previously alerted
                $this->resolveAlert('task_delay', 'task', $t->id);
                continue;
            }
            $this->upsertAlert(
                'task_delay', 'task', (int) $t->id,
                $this->severityFromRatio($ratio),
                "Task \"{$t->name}\" elapsed " . round($elapsed, 1) . "h vs estimate {$t->estimated_hrs}h"
            );
            $count++;
        }
        return $count;
    }

    private function detectWipOverload(): int
    {
        $count = 0;
        $rows = DB::table('project_tasks')
            ->select('assign_to', DB::raw('COUNT(*) as wip'))
            ->whereNotNull('assign_to')
            ->where(function ($q) {
                $q->where('is_complete', 0)->orWhereNull('is_complete');
            })
            ->groupBy('assign_to')
            ->having('wip', '>', self::WIP_OVERLOAD_THRESHOLD)
            ->get();
        foreach ($rows as $row) {
            $ratio = $row->wip / self::WIP_OVERLOAD_THRESHOLD;
            $userName = DB::table('users')->where('id', $row->assign_to)->value('name') ?: ('#' . $row->assign_to);
            $this->upsertAlert(
                'wip_overload', 'user', (int) $row->assign_to,
                $this->severityFromRatio($ratio),
                "{$userName} has {$row->wip} active tasks (limit: " . self::WIP_OVERLOAD_THRESHOLD . ")"
            );
            $count++;
        }
        return $count;
    }

    /** Auto-resolve any alert whose underlying condition is no longer true. */
    private function autoResolveCleared(): int
    {
        $resolved = 0;
        $stillActive = BottleneckAlert::unresolved()->get();
        foreach ($stillActive as $alert) {
            $shouldClear = false;
            if ($alert->type === 'stage_stuck') {
                $now = DB::table('project_tasks')->where('stage_id', $alert->reference_id)
                    ->where(function ($q) { $q->where('is_complete', 0)->orWhereNull('is_complete'); })
                    ->count();
                $shouldClear = $now <= self::STAGE_STUCK_THRESHOLD;
            } elseif ($alert->type === 'wip_overload') {
                $now = DB::table('project_tasks')->where('assign_to', $alert->reference_id)
                    ->where(function ($q) { $q->where('is_complete', 0)->orWhereNull('is_complete'); })
                    ->count();
                $shouldClear = $now <= self::WIP_OVERLOAD_THRESHOLD;
            } elseif ($alert->type === 'task_delay') {
                $task = DB::table('project_tasks')->where('id', $alert->reference_id)->first();
                $shouldClear = !$task || $task->is_complete;
            }
            if ($shouldClear) {
                $alert->update(['resolved_at' => now()]);
                $resolved++;
            }
        }
        return $resolved;
    }

    private function severityFromRatio(float $ratio): string
    {
        if ($ratio > 2.5) return 'high';
        if ($ratio > 1.5) return 'medium';
        return 'low';
    }

    /**
     * Insert an alert if there isn't already an unresolved one for the same
     * (type, reference); if there IS, just refresh its severity + message
     * + updated_at so the dashboard shows the freshest state.
     */
    private function upsertAlert(string $type, string $refType, int $refId, string $severity, string $message): void
    {
        $existing = BottleneckAlert::where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->whereNull('resolved_at')
            ->first();
        if ($existing) {
            $existing->update(['severity' => $severity, 'message' => $message]);
            return;
        }
        BottleneckAlert::create([
            'type'           => $type,
            'reference_type' => $refType,
            'reference_id'   => $refId,
            'severity'       => $severity,
            'message'        => $message,
        ]);
    }

    private function resolveAlert(string $type, string $refType, int $refId): void
    {
        BottleneckAlert::where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }
}

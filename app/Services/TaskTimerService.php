<?php

namespace App\Services;

use App\Models\TaskEvent;
use App\Models\TaskLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TaskTimerService — start / stop / pause / resume / heartbeat for an
 * employee's task timer.
 *
 * KEY INVARIANT: each user can have at most ONE non-stopped TaskLog row
 * at any time. Enforced by checking + transactionally upserting.
 */
class TaskTimerService
{
    /** Minutes without heartbeat before auto-pause. */
    public const IDLE_THRESHOLD_MINUTES = 5;

    /** Heartbeat cadence the page MUST match (seconds). */
    public const HEARTBEAT_INTERVAL_SECONDS = 60;

    /**
     * Start a timer for $userId on $taskId. If they already have an open
     * timer, that one is auto-stopped first (single-active-timer rule).
     *
     * @return TaskLog the newly opened log row
     */
    public function start(int $userId, int $taskId): TaskLog
    {
        return DB::transaction(function () use ($userId, $taskId) {
            $existing = TaskLog::active()->where('user_id', $userId)->lockForUpdate()->first();
            if ($existing) {
                $this->stop($userId, ['auto_closed_for_new_task' => true]);
            }

            $log = TaskLog::create([
                'user_id'           => $userId,
                'task_id'           => $taskId,
                'start_time'        => now(),
                'status'            => 'active',
                'active_seconds'    => 0,
                'idle_seconds'      => 0,
                'last_heartbeat_at' => now(),
            ]);

            $this->logEvent($log, 'start');
            return $log;
        });
    }

    /**
     * Heartbeat from the browser. Increments active_seconds by the
     * elapsed time since the previous heartbeat (capped at HEARTBEAT_INTERVAL
     * to prevent gaming via slowed page).
     */
    public function heartbeat(int $userId): ?TaskLog
    {
        $log = TaskLog::where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
        if (!$log) return null;

        $now      = now();
        $previous = $log->last_heartbeat_at ?: $log->start_time;
        $delta    = max(0, $now->diffInSeconds($previous));
        $delta    = min($delta, self::HEARTBEAT_INTERVAL_SECONDS * 2); // anti-gaming cap

        $log->active_seconds += $delta;
        $log->last_heartbeat_at = $now;
        $log->save();

        $this->logEvent($log, 'heartbeat');
        return $log;
    }

    /**
     * Manual pause OR auto-pause from scheduler when last_heartbeat_at is
     * older than IDLE_THRESHOLD_MINUTES.
     */
    public function pause(int $userId, array $meta = []): ?TaskLog
    {
        return DB::transaction(function () use ($userId, $meta) {
            $log = TaskLog::active()->where('user_id', $userId)
                ->where('status', 'active')
                ->lockForUpdate()->first();
            if (!$log) return null;

            $log->status = 'paused';
            $log->save();
            $this->logEvent($log, 'pause', $meta);
            return $log;
        });
    }

    public function resume(int $userId): ?TaskLog
    {
        return DB::transaction(function () use ($userId) {
            $log = TaskLog::active()->where('user_id', $userId)
                ->where('status', 'paused')
                ->lockForUpdate()->first();
            if (!$log) return null;

            $log->status = 'active';
            $log->last_heartbeat_at = now();
            $log->save();
            $this->logEvent($log, 'resume');
            return $log;
        });
    }

    public function stop(int $userId, array $meta = []): ?TaskLog
    {
        return DB::transaction(function () use ($userId, $meta) {
            $log = TaskLog::active()->where('user_id', $userId)->lockForUpdate()->first();
            if (!$log) return null;

            // Final idle bucket = total wall time - active time
            $totalSec = max(0, now()->diffInSeconds($log->start_time));
            $idleSec  = max(0, $totalSec - $log->active_seconds);

            $log->idle_seconds = $idleSec;
            $log->end_time     = now();
            $log->status       = 'stopped';
            $log->save();
            $this->logEvent($log, 'stop', $meta);
            return $log;
        });
    }

    /**
     * Used by scheduler: find every active timer with a stale heartbeat
     * and pause it. Returns count paused.
     */
    public function autoPauseStale(): int
    {
        $cutoff = Carbon::now()->subMinutes(self::IDLE_THRESHOLD_MINUTES);
        $stale  = TaskLog::where('status', 'active')
            ->where('last_heartbeat_at', '<', $cutoff)
            ->get();
        foreach ($stale as $log) {
            $this->pause($log->user_id, ['reason' => 'auto_idle']);
        }
        return $stale->count();
    }

    public function currentForUser(int $userId): ?TaskLog
    {
        return TaskLog::with('task')
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'paused'])
            ->orderByDesc('id')
            ->first();
    }

    private function logEvent(TaskLog $log, string $eventType, array $meta = []): void
    {
        TaskEvent::create([
            'task_log_id' => $log->id,
            'task_id'     => $log->task_id,
            'user_id'     => $log->user_id,
            'event_type'  => $eventType,
            'occurred_at' => now(),
            'meta'        => $meta ?: null,
        ]);
    }
}

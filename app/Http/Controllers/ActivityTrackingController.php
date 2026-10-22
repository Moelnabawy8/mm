<?php

namespace App\Http\Controllers;

use App\Models\BottleneckAlert;
use App\Models\Employee;
use App\Models\EmployeePerformance;
use App\Models\ProjectTask;
use App\Models\TaskLog;
use App\Models\Utility;
use App\Services\BottleneckDetector;
use App\Services\PerformanceService;
use App\Services\TaskTimerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ActivityTrackingController — single entry point for the new module.
 * Splits naturally into two sub-modules:
 *   1. Employee timer endpoints  (start/stop/pause/resume/heartbeat/current)
 *   2. Manager dashboard endpoints (metrics/bottlenecks/performance lookup)
 *
 * Feature flag: when settings.activity_tracking_enabled = '0' (default),
 * the timer page renders an info card and the API endpoints return
 * { enabled: false } so the frontend can degrade gracefully.
 */
class ActivityTrackingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ─────────────────────────────────────────────────────────
    //  EMPLOYEE: my-timer page
    // ─────────────────────────────────────────────────────────
    public function myTimer()
    {
        if (!$this->isFeatureEnabled()) {
            return view('activity.disabled');
        }

        $userId = Auth::id();
        $service = new TaskTimerService();
        $current = $service->currentForUser($userId);

        // The user's open tasks — assigned to them, not yet complete
        $myTasks = ProjectTask::where('assign_to', $userId)
            ->where(function ($q) { $q->where('is_complete', 0)->orWhereNull('is_complete'); })
            ->orderBy('end_date')
            ->get(['id', 'name', 'project_id', 'priority', 'estimated_hrs', 'end_date']);

        return view('activity.my-timer', compact('current', 'myTasks'));
    }

    public function startTimer(Request $request)
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        $request->validate(['task_id' => 'required|integer|exists:project_tasks,id']);

        $log = (new TaskTimerService())->start(Auth::id(), (int) $request->task_id);
        return response()->json(['ok' => true, 'log_id' => $log->id, 'started_at' => $log->start_time]);
    }

    public function stopTimer(Request $request)
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        $log = (new TaskTimerService())->stop(Auth::id(), ['source' => 'manual']);
        return response()->json(['ok' => (bool) $log, 'log' => $log]);
    }

    public function pauseTimer(Request $request)
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        $log = (new TaskTimerService())->pause(Auth::id(), ['source' => 'manual']);
        return response()->json(['ok' => (bool) $log]);
    }

    public function resumeTimer(Request $request)
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        $log = (new TaskTimerService())->resume(Auth::id());
        return response()->json(['ok' => (bool) $log]);
    }

    public function heartbeat(Request $request)
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        $log = (new TaskTimerService())->heartbeat(Auth::id());
        return response()->json([
            'ok' => (bool) $log,
            'active_seconds' => $log ? $log->active_seconds : 0,
        ]);
    }

    public function currentTimer()
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        $log = (new TaskTimerService())->currentForUser(Auth::id());
        return response()->json([
            'enabled' => true,
            'active'  => (bool) $log,
            'log'     => $log,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  MANAGER: dashboard + metrics + bottlenecks
    // ─────────────────────────────────────────────────────────
    public function dashboard()
    {
        if (!$this->isFeatureEnabled()) {
            return view('activity.disabled');
        }
        if (!Auth::user()->can('manage activity tracking')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
        return view('activity.dashboard');
    }

    public function dashboardMetrics(): JsonResponse
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        if (!Auth::user()->can('manage activity tracking')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }

        $creatorId = Auth::user()->creatorId();

        // Restrict to employees of the same tenant
        $tenantUserIds = Employee::where('created_by', $creatorId)->pluck('user_id')->filter()->values();

        $thirtyDaysAgo = Carbon::now()->subDays(29)->toDateString();
        $today = Carbon::now()->toDateString();

        // Aggregate average score / day per user across last 30 days
        $rows = EmployeePerformance::whereIn('user_id', $tenantUserIds)
            ->whereBetween('date', [$thirtyDaysAgo, $today])
            ->select('user_id',
                DB::raw('AVG(score)        AS avg_score'),
                DB::raw('AVG(productivity) AS avg_productivity'),
                DB::raw('AVG(idle_ratio)   AS avg_idle_ratio'),
                DB::raw('SUM(active_seconds) AS active_seconds'),
                DB::raw('SUM(idle_seconds)   AS idle_seconds'),
                DB::raw('SUM(completed_tasks) AS completed_tasks'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Join names
        $userNames = DB::table('users')->whereIn('id', $tenantUserIds)->pluck('name', 'id');

        $top = $rows->sortByDesc('avg_score')->take(10)->map(function ($r) use ($userNames) {
            return [
                'user_id'      => $r->user_id,
                'name'         => $userNames[$r->user_id] ?? ('#' . $r->user_id),
                'score'        => round((float) $r->avg_score, 4),
                'stars'        => (int) round(((float) $r->avg_score) * 5),
                'productivity' => round((float) $r->avg_productivity, 4),
            ];
        })->values();

        $highIdle = $rows->sortByDesc('avg_idle_ratio')->take(10)->map(function ($r) use ($userNames) {
            return [
                'user_id'    => $r->user_id,
                'name'       => $userNames[$r->user_id] ?? ('#' . $r->user_id),
                'idle_ratio' => round((float) $r->avg_idle_ratio, 4),
                'idle_hours' => round(((int) $r->idle_seconds) / 3600, 2),
            ];
        })->values();

        // Average task completion time (in hours) over last 30 days
        $avgCompletionHours = DB::table('project_tasks')
            ->whereIn('assign_to', $tenantUserIds)
            ->where('is_complete', 1)
            ->whereDate('marked_at', '>=', $thirtyDaysAgo)
            ->whereNotNull('start_date')->whereNotNull('marked_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, start_date, marked_at)) AS hrs')
            ->value('hrs');

        $bottleneckCount = BottleneckAlert::unresolved()->count();
        $bottleneckBySeverity = BottleneckAlert::unresolved()
            ->select('severity', DB::raw('COUNT(*) as c'))
            ->groupBy('severity')->pluck('c', 'severity')->toArray();

        return response()->json([
            'enabled' => true,
            'top_productive'     => $top,
            'high_idle'          => $highIdle,
            'avg_task_hours'     => $avgCompletionHours ? round((float) $avgCompletionHours, 1) : null,
            'bottleneck_count'   => $bottleneckCount,
            'bottleneck_by_severity' => $bottleneckBySeverity,
            'window_days'        => 30,
        ]);
    }

    public function bottlenecksFeed(Request $request): JsonResponse
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        if (!Auth::user()->can('manage activity tracking')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        $alerts = BottleneckAlert::unresolved()
            ->orderByRaw("FIELD(severity, 'high', 'medium', 'low')")
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();
        return response()->json(['enabled' => true, 'alerts' => $alerts]);
    }

    /** Force a detection run on demand (manager click). */
    public function bottlenecksRun(): JsonResponse
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();
        if (!Auth::user()->can('manage activity tracking')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        $result = (new BottleneckDetector())->run();
        return response()->json(['enabled' => true, 'result' => $result]);
    }

    /** Public-ish performance lookup for a user (used by employee profile widget). */
    public function performanceFor(int $userId): JsonResponse
    {
        if (!$this->isFeatureEnabled()) return $this->disabledResponse();

        // Self OR has team-view permission
        $self = Auth::id() == $userId;
        if (!$self && !Auth::user()->can('view team performance')) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        $summary = (new PerformanceService())->summary($userId);
        return response()->json(['enabled' => true] + $summary);
    }

    // ─────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────
    private function isFeatureEnabled(): bool
    {
        try {
            $val = Utility::getValByName('activity_tracking_enabled');
            return (string) $val === '1';
        } catch (\Throwable $e) {
            return false; // safer default if settings helper missing
        }
    }

    private function disabledResponse(): JsonResponse
    {
        return response()->json(['enabled' => false, 'message' => 'Activity tracking is disabled.']);
    }
}

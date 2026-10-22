<?php

namespace App\Http\Controllers;

use App\Exports\TaskReportExport;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskStage;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\Utility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Task Report
 *
 * A single, filterable report over all project (department) tasks:
 * what is done, what is not, what is late - with PDF / Word / Excel output.
 */
class TaskReportController extends Controller
{
    /** Which date column the period filter is applied on. */
    public static $dateBasis = [
        'end_date'   => 'Due Date',
        'start_date' => 'Start Date',
        'created_at' => 'Created Date',
        'marked_at'  => 'Completed Date',
    ];

    /** Quick completion filter. */
    public static $statusFilters = [
        'all'       => 'All Tasks',
        'completed' => 'Completed',
        'pending'   => 'Not Completed',
        'overdue'   => 'Overdue',
    ];

    public function index(Request $request)
    {
        if (!\Auth::user()->can('manage task report')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        return view('task_report.index', $this->buildReport($request));
    }

    /**
     * Standalone printable page. Auto-downloads a PDF (html2pdf) and also
     * offers the browser print dialog as a fallback.
     */
    public function exportPdf(Request $request)
    {
        if (!\Auth::user()->can('manage task report')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        return view('task_report.print', $this->buildReport($request) + ['mode' => 'pdf']);
    }

    /**
     * The same printable page streamed as a Word document. Word opens HTML
     * natively, so this needs no extra package and keeps Arabic/RTL intact.
     */
    public function exportWord(Request $request)
    {
        if (!\Auth::user()->can('manage task report')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $html = view('task_report.print', $this->buildReport($request) + ['mode' => 'word'])->render();

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-word; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->fileName('doc') . '"',
        ]);
    }

    public function exportExcel(Request $request)
    {
        if (!\Auth::user()->can('manage task report')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $report = $this->buildReport($request);

        return Excel::download(new TaskReportExport($report['tasks']), $this->fileName('xlsx'));
    }

    protected function fileName($ext)
    {
        return 'task-report-' . date('Y-m-d-His') . '.' . $ext;
    }

    /* -----------------------------------------------------------------
     |  Report building
     | -----------------------------------------------------------------
     */

    protected function buildReport(Request $request)
    {
        $user      = \Auth::user();
        $creatorId = $user->creatorId();

        $filters = $this->resolveFilters($request);

        $completedStageIds = $this->completedStageIds($creatorId);

        $query = $this->baseQuery($user, $creatorId);
        $this->applyFilters($query, $filters);

        $tasks = $this->hydrate($query->get(), $completedStageIds);

        // Post-query filters that depend on derived values.
        if ($filters['status'] == 'completed') {
            $tasks = $tasks->where('is_done', true)->values();
        } elseif ($filters['status'] == 'pending') {
            $tasks = $tasks->where('is_done', false)->values();
        } elseif ($filters['status'] == 'overdue') {
            $tasks = $tasks->where('is_overdue', true)->values();
        }

        return [
            'tasks'      => $tasks,
            'undated'    => $this->countUndated($user, $creatorId, $filters),
            'stats'      => $this->stats($tasks),
            'byStage'    => $this->groupBy($tasks, 'stage_name'),
            'byPriority' => $this->withPriorityColors($this->groupBy($tasks, 'priority_label')),
            'byUser'     => $this->groupByUser($tasks),
            'byProject'  => $this->groupBy($tasks, 'project_name'),
            'filters'    => $filters,
            'meta'       => $this->meta($filters),
            // Filter option lists
            'projects'   => $this->projectOptions($user, $creatorId),
            'employees'  => $this->employeeOptions($creatorId),
            'stages'     => TaskStage::where('created_by', $creatorId)->orderBy('order')->get(),
            'priorities' => ProjectTask::$priority,
            'dateBasis'  => self::$dateBasis,
            'statusList' => self::$statusFilters,
        ];
    }

    protected function resolveFilters(Request $request)
    {
        $basis = $request->get('date_basis');
        if (!array_key_exists($basis, self::$dateBasis)) {
            $basis = 'end_date';
        }

        $status = $request->get('status');
        if (!array_key_exists($status, self::$statusFilters)) {
            $status = 'all';
        }

        // A task with no date belongs to no period, so a range would drop it.
        // Most tasks on this board have no due date, so they are kept by
        // default and the form carries a hidden 0 to allow unticking.
        $includeUndated = $request->has('include_undated')
            ? (bool) $request->get('include_undated')
            : true;

        return [
            'start_date'      => $request->filled('start_date') ? $request->get('start_date') : null,
            'end_date'        => $request->filled('end_date') ? $request->get('end_date') : null,
            'date_basis'      => $basis,
            'include_undated' => $includeUndated,
            'status'          => $status,
            'project_id' => array_filter((array) $request->get('project_id', [])),
            'assign_to'  => array_filter((array) $request->get('assign_to', [])),
            'stage_id'   => array_filter((array) $request->get('stage_id', [])),
            'priority'   => array_filter((array) $request->get('priority', [])),
            'search'     => $request->get('search'),
        ];
    }

    /**
     * Stages that mean "done". Falls back to name matching, then to the last
     * stage on the board, so the report still works if nobody ticked the
     * "complete" flag on a stage.
     */
    protected function completedStageIds($creatorId)
    {
        return TaskStage::completedIds($creatorId);
    }

    /**
     * How many tasks the selected period removed purely because they have no
     * date in the column it filters on.
     *
     * More than half the tasks on a busy board have no due date, so a period
     * filter can quietly halve the report. The number is surfaced in the view
     * instead of letting the totals look wrong.
     */
    protected function countUndated($user, $creatorId, $filters)
    {
        $noRange = empty($filters['start_date']) && empty($filters['end_date']);

        // Nothing is being hidden when there is no range, or when undated
        // tasks are being kept.
        if ($noRange || !empty($filters['include_undated'])) {
            return 0;
        }

        $query = $this->baseQuery($user, $creatorId);
        $this->applyNonDateFilters($query, $filters);

        // Grouped, so the OR cannot escape and cancel the filters above it.
        $query->where(function ($q) use ($filters) {
            $this->whereDateMissing($q, $filters['date_basis']);
        });

        return (int) $query->count();
    }

    /** A date column that was never filled: NULL or a zero placeholder. */
    protected function whereDateMissing($query, $basis)
    {
        return $query->whereNull($basis)
            ->orWhere($basis, '0000-00-00')
            ->orWhere($basis, '0000-00-00 00:00:00');
    }

    /** Row-level access scope, mirroring the task board. */
    protected function baseQuery($user, $creatorId)
    {
        $query = ProjectTask::query();

        if ($user->type == 'client') {
            $projectIds = Project::where('client_id', $user->id)->pluck('id')->toArray();
            $query->whereIn('project_id', $projectIds);
        } elseif ($user->hasFullProjectAccess()) {
            $query->where('created_by', $creatorId);
        } else {
            $projectIds = $user->projects()->pluck('project_id')->toArray();
            $query->where('created_by', $creatorId)
                ->where(function ($q) use ($user, $projectIds) {
                    $q->whereRaw('find_in_set(?, assign_to)', [$user->id]);
                    if (!empty($projectIds)) {
                        $q->orWhereIn('project_id', $projectIds);
                    }
                });
        }

        return $query;
    }

    protected function applyFilters($query, $filters)
    {
        $basis = $filters['date_basis'];

        if (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            $query->where(function ($q) use ($basis, $filters) {
                $q->where(function ($inRange) use ($basis, $filters) {
                    if (!empty($filters['start_date'])) {
                        $inRange->whereDate($basis, '>=', $filters['start_date']);
                    }
                    if (!empty($filters['end_date'])) {
                        $inRange->whereDate($basis, '<=', $filters['end_date']);
                    }
                });

                // Undated tasks ride along with any range rather than vanishing
                // from every one of them.
                if (!empty($filters['include_undated'])) {
                    $q->orWhere(function ($undated) use ($basis) {
                        $this->whereDateMissing($undated, $basis);
                    });
                }
            });
        }

        $this->applyNonDateFilters($query, $filters);

        $query->orderByRaw('COALESCE(end_date, start_date, created_at) ASC');

        return $query;
    }

    /**
     * Split out so the "how many tasks did the period silently drop" count can
     * reuse exactly the same filters minus the date range.
     */
    protected function applyNonDateFilters($query, $filters)
    {
        if (!empty($filters['project_id'])) {
            $query->whereIn('project_id', $filters['project_id']);
        }
        if (!empty($filters['stage_id'])) {
            $query->whereIn('stage_id', $filters['stage_id']);
        }
        if (!empty($filters['priority'])) {
            $query->whereIn('priority', $filters['priority']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }

        if (!empty($filters['assign_to'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['assign_to'] as $userId) {
                    $q->orWhereRaw('find_in_set(?, assign_to)', [$userId]);
                }
            });
        }

        return $query;
    }

    /** Flatten tasks into plain rows the views and exports can share. */
    protected function hydrate($tasks, $completedStageIds)
    {
        if ($tasks->isEmpty()) {
            return collect();
        }

        $taskIds = $tasks->pluck('id')->toArray();

        $projects = Project::whereIn('id', $tasks->pluck('project_id')->unique()->filter()->toArray())
            ->pluck('project_name', 'id');

        $stages = TaskStage::whereIn('id', $tasks->pluck('stage_id')->unique()->filter()->toArray())
            ->get()->keyBy('id');

        $userIds = [];
        foreach ($tasks as $task) {
            foreach (array_filter(explode(',', (string) $task->assign_to)) as $id) {
                $userIds[trim($id)] = trim($id);
            }
        }
        $users = User::whereIn('id', $userIds)->pluck('name', 'id');

        $logged      = $this->loggedHours($taskIds);
        $stageColors = $this->stageColorMap($stages);

        $today = Carbon::today();

        return $tasks->map(function ($task) use ($projects, $stages, $stageColors, $users, $logged, $completedStageIds, $today) {
            $stage  = $stages->get($task->stage_id);
            $isDone = in_array($task->stage_id, $completedStageIds);

            $assignees = [];
            foreach (array_filter(explode(',', (string) $task->assign_to)) as $id) {
                $assignees[] = $users->get(trim($id), '-');
            }

            $start  = $this->safeDate($task->start_date);
            $due    = $this->safeDate($task->end_date);
            $marked = $this->safeDate($task->marked_at);

            $daysLate = 0;
            if (!$isDone && $due && $due->lt($today)) {
                $daysLate = $due->diffInDays($today);
            }

            $completedIn = null;
            if ($isDone && $start && $marked && $marked->gte($start)) {
                $completedIn = $start->diffInDays($marked);
            }

            return (object) [
                'id'             => $task->id,
                'name'           => $task->name,
                'description'    => $task->description,
                'project_name'   => $projects->get($task->project_id, '-'),
                'assignees'      => $assignees,
                'assignee_text'  => empty($assignees) ? '-' : implode(', ', $assignees),
                'priority'       => $task->priority,
                'priority_label' => ProjectTask::$priority[$task->priority] ?? ucfirst((string) $task->priority),
                'priority_color' => ProjectTask::$priority_color[$task->priority] ?? 'secondary',
                'stage_name'     => $stage ? $stage->name : '-',
                'stage_color'    => $stageColors[$task->stage_id] ?? '#6c757d',
                // Normalised so placeholder dates render as "-" instead of 0000-00-00
                'start_date'     => $start ? $start->toDateString() : null,
                'end_date'       => $due ? $due->toDateString() : null,
                'marked_at'      => $marked ? $marked->toDateString() : null,
                'created_at'     => $task->created_at,
                'progress'       => (int) $task->progress,
                'is_done'        => $isDone,
                'is_overdue'     => $daysLate > 0,
                'days_late'      => $daysLate,
                'completed_in'   => $completedIn,
                'estimated_hrs'  => (float) $task->estimated_hrs,
                'actual_hrs'     => (float) ($logged[$task->id] ?? 0),
            ];
        })->values();
    }

    /** Sum of timesheet time per task, in decimal hours. */
    protected function loggedHours($taskIds)
    {
        $result = [];

        Timesheet::whereIn('task_id', $taskIds)
            ->select('task_id', 'time')
            ->get()
            ->each(function ($row) use (&$result) {
                $parts   = explode(':', (string) $row->time);
                $hours   = (int) ($parts[0] ?? 0);
                $minutes = (int) ($parts[1] ?? 0);

                $result[$row->task_id] = ($result[$row->task_id] ?? 0) + $hours + ($minutes / 60);
            });

        return array_map(function ($v) {
            return round($v, 2);
        }, $result);
    }

    protected function stats($tasks)
    {
        $total      = $tasks->count();
        $done       = $tasks->where('is_done', true)->count();
        $overdue    = $tasks->where('is_overdue', true)->count();
        $pending    = $total - $done;
        $inProgress = $tasks->filter(function ($t) {
            return !$t->is_done && $t->progress > 0;
        })->count();

        $completedDurations = $tasks->where('is_done', true)
            ->filter(function ($t) {
                return $t->completed_in !== null;
            })->pluck('completed_in');

        return [
            'total'         => $total,
            'completed'     => $done,
            'pending'       => $pending,
            'in_progress'   => $inProgress,
            'not_started'   => max($pending - $inProgress, 0),
            'overdue'       => $overdue,
            'rate'          => $total > 0 ? round(($done / $total) * 100, 1) : 0,
            'estimated_hrs' => round($tasks->sum('estimated_hrs'), 2),
            'actual_hrs'    => round($tasks->sum('actual_hrs'), 2),
            'avg_days'      => $completedDurations->count() > 0 ? round($completedDurations->avg(), 1) : null,
        ];
    }

    protected function groupBy($tasks, $key)
    {
        $overall = $tasks->count();

        return $tasks->groupBy($key)->map(function ($group, $label) use ($overall) {
            $total = $group->count();
            $done  = $group->where('is_done', true)->count();

            return [
                'label'     => $label !== '' ? $label : '-',
                'total'     => $total,
                'completed' => $done,
                'pending'   => $total - $done,
                'overdue'   => $group->where('is_overdue', true)->count(),
                'rate'      => $total > 0 ? round(($done / $total) * 100, 1) : 0,
                'percent'   => $overall > 0 ? round(($total / $overall) * 100, 1) : 0,
                'color'     => $group->first()->stage_color ?? '#6c757d',
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Priority carries meaning, so colour it semantically instead of inheriting
     * the stage colour of whichever task happened to land first in the group.
     */
    protected function withPriorityColors($groups)
    {
        $colors = [
            'Critical' => '#c62828',
            'High'     => '#e08600',
            'Medium'   => '#1b4f8a',
            'Low'      => '#16849b',
        ];

        return $groups->map(function ($row) use ($colors) {
            $row['color'] = $colors[$row['label']] ?? '#5b6b7c';

            return $row;
        });
    }

    /** Chart / badge palette used when a stage has no usable colour of its own. */
    protected static $palette = [
        '#1b4f8a', '#1e874b', '#e08600', '#16849b',
        '#7b4fa8', '#c62828', '#0f9d8f', '#5b6b7c',
    ];

    /**
     * One colour per stage. A stage's own colour is used when it is a real hex
     * that is actually visible on a white card - stages configured with white,
     * a near-white tint, or a Bootstrap class name instead of a hex would
     * otherwise draw an invisible slice. Everything else falls back to the
     * palette, and duplicates are pushed apart so two stages never share a hue.
     */
    protected function stageColorMap($stages)
    {
        $map  = [];
        $used = [];
        $next = 0;

        foreach ($stages as $id => $stage) {
            $color = $this->usableColor($stage->color ?? null);

            if ($color === null || in_array($color, $used, true)) {
                $color = self::$palette[$next % count(self::$palette)];

                // Skip palette entries a stage already claimed.
                $guard = 0;
                while (in_array($color, $used, true) && $guard < count(self::$palette)) {
                    $next++;
                    $guard++;
                    $color = self::$palette[$next % count(self::$palette)];
                }
                $next++;
            }

            $used[]    = $color;
            $map[$id]  = $color;
        }

        return $map;
    }

    /**
     * A colour we can safely paint on a white background, or null.
     * Accepts #rgb / #rrggbb only, and rejects anything too light to see.
     */
    protected function usableColor($color)
    {
        if (!is_string($color)) {
            return null;
        }

        $color = trim($color);

        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
            return null;
        }

        if (strlen($color) === 4) {
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }

        $r = hexdec(substr($color, 1, 2));
        $g = hexdec(substr($color, 3, 2));
        $b = hexdec(substr($color, 5, 2));

        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

        return $luminance > 0.88 ? null : strtolower($color);
    }

    /**
     * Parse a stored date, rejecting the placeholders this database is full of
     * ('0000-00-00', epoch dates) so one broken row cannot drag an average of
     * "days to complete" into the thousands.
     */
    protected function safeDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }

        return $date->year >= 2000 && $date->year <= 2100 ? $date : null;
    }

    /** Per-employee breakdown - a task with N assignees counts for each of them. */
    protected function groupByUser($tasks)
    {
        $rows = [];

        foreach ($tasks as $task) {
            $names = empty($task->assignees) ? [__('Unassigned')] : $task->assignees;

            foreach ($names as $name) {
                if (!isset($rows[$name])) {
                    $rows[$name] = [
                        'label'         => $name,
                        'total'         => 0,
                        'completed'     => 0,
                        'pending'       => 0,
                        'overdue'       => 0,
                        'estimated_hrs' => 0,
                        'actual_hrs'    => 0,
                        'days_sum'      => 0,
                        'days_count'    => 0,
                    ];
                }

                $rows[$name]['total']++;
                $rows[$name]['estimated_hrs'] += $task->estimated_hrs;
                $rows[$name]['actual_hrs'] += $task->actual_hrs;

                if ($task->is_done) {
                    $rows[$name]['completed']++;
                    if ($task->completed_in !== null) {
                        $rows[$name]['days_sum'] += $task->completed_in;
                        $rows[$name]['days_count']++;
                    }
                } else {
                    $rows[$name]['pending']++;
                }

                if ($task->is_overdue) {
                    $rows[$name]['overdue']++;
                }
            }
        }

        return collect($rows)->map(function ($row) {
            $row['rate']          = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100, 1) : 0;
            $row['avg_days']      = $row['days_count'] > 0 ? round($row['days_sum'] / $row['days_count'], 1) : null;
            $row['estimated_hrs'] = round($row['estimated_hrs'], 2);
            $row['actual_hrs']    = round($row['actual_hrs'], 2);

            return $row;
        })->sortByDesc('total')->values();
    }

    protected function meta($filters)
    {
        $settings = Utility::settings();

        $from = $filters['start_date'];
        $to   = $filters['end_date'];

        if ($from && $to) {
            $period = $from . ' - ' . $to;
        } elseif ($from) {
            $period = __('From') . ' ' . $from;
        } elseif ($to) {
            $period = __('Until') . ' ' . $to;
        } else {
            $period = __('All Time');
        }

        // An exported report has no filter form to look at, so the period has
        // to say why undated tasks are in a dated total.
        if (($from || $to) && !empty($filters['include_undated'])) {
            $period .= ' (' . __('including tasks with no date') . ')';
        }

        return [
            'company_name' => !empty($settings['company_name']) ? $settings['company_name'] : config('app.name'),
            'logo'         => Utility::get_file('uploads/logo/') . (Utility::getValByName('company_logo_dark') ?: 'logo-dark.png'),
            'period'       => $period,
            'basis_label'  => self::$dateBasis[$filters['date_basis']],
            'status_label' => self::$statusFilters[$filters['status']],
            'generated_at' => date('Y-m-d H:i'),
            'generated_by' => \Auth::user()->name,
        ];
    }

    protected function projectOptions($user, $creatorId)
    {
        if ($user->type == 'client') {
            return Project::where('client_id', $user->id)->orderBy('project_name')->get();
        }

        if (in_array($user->type, ['company', 'super admin'])) {
            return Project::where('created_by', $creatorId)->orderBy('project_name')->get();
        }

        return $user->projects()->orderBy('project_name')->get();
    }

    protected function employeeOptions($creatorId)
    {
        return User::where('created_by', $creatorId)
            ->whereNotIn('type', ['client', 'super admin'])
            ->orderBy('name')
            ->get();
    }
}

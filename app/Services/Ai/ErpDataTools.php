<?php

namespace App\Services\Ai;

use App\Models\Commitment;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The only things the assistant is allowed to look up.
 *
 * The model never writes SQL and never names a table. It picks one of the functions
 * declared in definitions() and supplies its arguments; run() dispatches to a PHP method
 * here. Three consequences, all deliberate:
 *
 *   - A hallucinated capability simply does not exist, so it fails as "I cannot answer
 *     that" rather than as a malformed query.
 *   - Every method scopes on the caller's creatorId(), so one company can never read
 *     another's figures through a cleverly worded question — the same scoping
 *     SetSalaryController was missing until 2026-09-12.
 *   - Nothing here writes. An assistant that can change data is a different feature with
 *     a different risk profile, and it is not this one.
 *
 * Results are returned small and already aggregated. Handing the model 3,000 task rows
 * would cost a fortune per question and answer worse than a count and five examples.
 */
class ErpDataTools
{
    /** Rows returned to the model per list-style answer. */
    private const SAMPLE = 8;

    /**
     * OpenAI tool definitions. Descriptions are written for the model, so they say when
     * to use each one, not merely what it returns.
     */
    public function definitions(): array
    {
        return [
            $this->tool('count_tasks',
                'Count project tasks, optionally filtered. Use for "how many tasks…", "how much work is late", "what is <department> working on".',
                [
                    'department' => ['string', 'Department name, e.g. "Developers". Omit for the whole company.'],
                    'status'     => ['string', 'One of: open, completed, overdue. Default open.'],
                    'days'       => ['integer', 'Only count tasks completed in the last N days. Only meaningful with status=completed.'],
                ]),

            $this->tool('list_overdue_commitments',
                'Commitments that are past their deadline and still open. Use for "who is late", "what is overdue", "what do I owe".',
                [
                    'scope'      => ['string', 'One of: mine (what the user owes), owed_to_me (what others owe the user), all. Default all.'],
                    'department' => ['string', 'Limit to one department by name.'],
                ]),

            $this->tool('commitment_summary',
                'Counts and kept-rate for commitments, this week or overall. Use for "how are we doing on commitments", "kept rate".',
                [
                    'week' => ['string', 'A date inside the week of interest, YYYY-MM-DD. Omit for the current week.'],
                ]),

            $this->tool('quality_summary',
                'Quality review scores and coverage per department. Use for "quality of <department>", "average score", "how much work was reviewed".',
                [
                    'department' => ['string', 'Department name. Omit for every department.'],
                ]),

            $this->tool('meetings_without_minutes',
                'Meetings that were held but have no minutes written. Use for "which meetings need writing up".',
                []),

            $this->tool('inactive_accounts',
                'Accounts that have not logged in for longer than the configured threshold. Use for "who has not logged in", "inactive users".',
                []),

            $this->tool('department_overview',
                'The list of departments with their headcount, projects and task volume. Use when the user asks what departments exist, or before filtering by one.',
                []),

            $this->tool('employee_workload',
                'Open and completed task counts for one employee by name. Use for "what is <name> working on", "how busy is <name>".',
                [
                    'name' => ['string', 'The employee name, or part of it.'],
                ]),
        ];
    }

    /** Dispatch a tool call. Unknown names are refused rather than guessed at. */
    public function run(string $name, array $args): array
    {
        $allowed = array_column(array_column($this->definitions(), 'function'), 'name');

        if (!in_array($name, $allowed, true)) {
            return ['error' => "Unknown tool: {$name}"];
        }

        $method = 'tool' . str_replace('_', '', ucwords($name, '_'));

        if (!method_exists($this, $method)) {
            return ['error' => "Tool {$name} is declared but not implemented"];
        }

        try {
            return $this->$method($args);
        } catch (\Throwable $e) {
            // The model must not see a stack trace or a table name.
            report($e);

            return ['error' => 'That lookup failed.'];
        }
    }

    /* ------------------------------------------------------------------- tools */

    private function toolCountTasks(array $a): array
    {
        $status = strtolower((string) ($a['status'] ?? 'open'));
        $deptId = $this->departmentId($a['department'] ?? null);

        if (($a['department'] ?? null) && !$deptId) {
            return ['error' => 'No department matches "' . $a['department'] . '".', 'departments' => $this->departmentNames()];
        }

        $q = DB::table('project_tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->where('p.created_by', $this->creator());

        if ($deptId) {
            $q->where('p.department_id', $deptId);
        }

        if ($status === 'completed') {
            $q->where('t.is_complete', 1);

            if (!empty($a['days'])) {
                // marked_at only — updated_at moves on any edit and would count
                // thousands of old tasks as recent work.
                $q->whereNotNull('t.marked_at')
                  ->where('t.marked_at', '>=', now()->subDays((int) $a['days'])->toDateString());
            }
        } elseif ($status === 'overdue') {
            $q->where('t.is_complete', 0)
              ->whereNotNull('t.end_date')
              ->where('t.end_date', '<', now()->toDateString());
        } else {
            $q->where('t.is_complete', 0);
        }

        $count = (clone $q)->count();

        $sample = (clone $q)
            ->select('t.name', 't.end_date', 'p.project_name')
            ->orderBy('t.end_date')
            ->limit(self::SAMPLE)
            ->get()
            ->map(fn($r) => ['task' => $r->name, 'due' => $r->end_date, 'project' => $r->project_name])
            ->all();

        return [
            'count'      => $count,
            'status'     => $status,
            'department' => $a['department'] ?? 'all departments',
            'examples'   => $sample,
        ];
    }

    private function toolListOverdueCommitments(array $a): array
    {
        if (!Schema::hasTable('commitments')) {
            return ['error' => 'Commitments are not set up on this system.'];
        }

        $scope = strtolower((string) ($a['scope'] ?? 'all'));

        $q = Commitment::with(['owner', 'department', 'owedTo'])
            ->where('created_by', $this->creator())
            ->where('status', Commitment::STATUS_OPEN);

        if ($scope === 'mine') {
            $q->where('user_id', Auth::id());
        } elseif ($scope === 'owed_to_me') {
            $q->where('owed_to_id', Auth::id());
        }

        if (!empty($a['department']) && ($deptId = $this->departmentId($a['department']))) {
            $q->where('department_id', $deptId);
        }

        $overdue = $q->get()->filter(fn($c) => $c->is_overdue)->values();

        return [
            'count' => $overdue->count(),
            'scope' => $scope,
            'items' => $overdue->take(self::SAMPLE)->map(fn($c) => [
                'what'      => $c->title,
                'owed_by'   => $c->owner_label,
                'owed_to'   => $c->owedTo->name ?? null,
                'deadline'  => $c->deadline ? $c->deadline->toDateString() : null,
                'days_late' => $c->days_left === null ? null : abs($c->days_left),
            ])->all(),
        ];
    }

    private function toolCommitmentSummary(array $a): array
    {
        if (!Schema::hasTable('commitments')) {
            return ['error' => 'Commitments are not set up on this system.'];
        }

        $week  = Commitment::weekStart($a['week'] ?? null);
        $board = app(\App\Services\Commitments\CommitmentBoard::class);

        $items = $board->week($week, $this->creator());

        return [
            'week'        => $week,
            'this_week'   => $board->tally($items),
            'by_department' => array_map(
                fn($d) => ['department' => $d['name'], 'open' => $d['open'], 'overdue' => $d['overdue'], 'kept_rate' => $d['kept_rate']],
                $board->weekByDepartment($week, $this->creator())
            ),
        ];
    }

    private function toolQualitySummary(array $a): array
    {
        if (!Schema::hasTable('task_quality_reviews')) {
            return ['error' => 'Quality reviews are not set up on this system.'];
        }

        $board = app(\App\Services\Quality\QualityBoard::class);
        $rows  = $board->departments($this->creator());

        if (!empty($a['department'])) {
            $needle = mb_strtolower(trim($a['department']));
            $rows   = array_values(array_filter($rows, fn($r) => str_contains(mb_strtolower($r['name']), $needle)));

            if (!$rows) {
                return ['error' => 'No department matches "' . $a['department'] . '".', 'departments' => $this->departmentNames()];
            }
        }

        return [
            'departments' => array_map(fn($r) => [
                'department'      => $r['name'],
                'completed_tasks' => $r['completed'],
                'awaiting_review' => $r['pending'],
                'reviewed'        => $r['reviews'],
                'average_score'   => $r['avg_score'],
            ], array_slice($rows, 0, 12)),
        ];
    }

    private function toolMeetingsWithoutMinutes(array $a): array
    {
        if (!Schema::hasColumn('meetings', 'minutes')) {
            return ['error' => 'Meeting minutes are not set up on this system.'];
        }

        $board = app(\App\Services\Commitments\CommitmentBoard::class);
        $list  = $board->meetingsAwaitingMinutes($this->creator());

        return [
            'count' => $list->count(),
            'items' => $list->take(self::SAMPLE)->map(fn($m) => [
                'title' => $m->title,
                'date'  => optional($m->dateOn())->toDateString(),
            ])->all(),
        ];
    }

    private function toolInactiveAccounts(array $a): array
    {
        if (!class_exists(\App\Services\InactivityAudit::class)) {
            return ['error' => 'The inactivity report is not available on this system.'];
        }

        if (!Auth::user()->can('manage inactivity report')) {
            return ['error' => 'You do not have permission to see the inactivity report.'];
        }

        $report = app(\App\Services\InactivityAudit::class)->scan();

        return [
            'candidates' => is_array($report['candidates'] ?? null) ? count($report['candidates']) : null,
            'warning'    => is_array($report['warning'] ?? null) ? count($report['warning']) : null,
            'exempt'     => is_array($report['exempt'] ?? null) ? count($report['exempt']) : null,
            'note'       => 'Full detail is on the Inactive Accounts Report page at /report/inactivity.',
        ];
    }

    private function toolDepartmentOverview(array $a): array
    {
        $rows = Department::orderBy('name')->get(['id', 'name']);

        $staff = Schema::hasColumn('employees', 'department_id')
            ? DB::table('employees')->select('department_id', DB::raw('COUNT(*) as n'))->groupBy('department_id')->pluck('n', 'department_id')
            : collect();

        $projects = Schema::hasColumn('projects', 'department_id')
            ? DB::table('projects')->where('created_by', $this->creator())
                ->select('department_id', DB::raw('COUNT(*) as n'))->groupBy('department_id')->pluck('n', 'department_id')
            : collect();

        return [
            'departments' => $rows->map(fn($d) => [
                'name'      => $d->name,
                'employees' => (int) ($staff[$d->id] ?? 0),
                'projects'  => (int) ($projects[$d->id] ?? 0),
            ])->all(),
            'note' => 'Some department names repeat as separate records; figures are per record.',
        ];
    }

    private function toolEmployeeWorkload(array $a): array
    {
        $name = trim((string) ($a['name'] ?? ''));

        if ($name === '') {
            return ['error' => 'Which employee?'];
        }

        $users = User::where('created_by', $this->creator())
            ->where('name', 'like', '%' . $name . '%')
            ->limit(5)
            ->get(['id', 'name']);

        if ($users->isEmpty()) {
            return ['error' => 'Nobody matches "' . $name . '".'];
        }

        if ($users->count() > 1) {
            return ['ambiguous' => $users->pluck('name')->all(), 'note' => 'Ask the user which one they mean.'];
        }

        $user = $users->first();

        // assign_to is a comma-separated list, so the match is on the list membership.
        $q = DB::table('project_tasks as t')
            ->join('projects as p', 'p.id', '=', 't.project_id')
            ->where('p.created_by', $this->creator())
            ->whereRaw('FIND_IN_SET(?, t.assign_to)', [$user->id]);

        $open      = (clone $q)->where('t.is_complete', 0)->count();
        $completed = (clone $q)->where('t.is_complete', 1)->count();

        $overdue = (clone $q)->where('t.is_complete', 0)
            ->whereNotNull('t.end_date')
            ->where('t.end_date', '<', now()->toDateString())
            ->count();

        return [
            'employee'        => $user->name,
            'open_tasks'      => $open,
            'overdue_tasks'   => $overdue,
            'completed_tasks' => $completed,
        ];
    }

    /* --------------------------------------------------------------- internals */

    private function creator(): int
    {
        return (int) Auth::user()->creatorId();
    }

    private function departmentNames(): array
    {
        return Department::orderBy('name')->pluck('name')->unique()->values()->all();
    }

    /** Resolve a department name the user typed to an id, tolerating partial matches. */
    private function departmentId(?string $name): ?int
    {
        if (!$name || trim($name) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($name));

        foreach (Department::orderBy('id')->get(['id', 'name']) as $d) {
            if (mb_strtolower((string) $d->name) === $needle) {
                return (int) $d->id;
            }
        }

        foreach (Department::orderBy('id')->get(['id', 'name']) as $d) {
            if (str_contains(mb_strtolower((string) $d->name), $needle)) {
                return (int) $d->id;
            }
        }

        return null;
    }

    private function tool(string $name, string $description, array $params): array
    {
        $properties = [];

        foreach ($params as $key => [$type, $desc]) {
            $properties[$key] = ['type' => $type, 'description' => $desc];
        }

        return [
            'type'     => 'function',
            'function' => [
                'name'        => $name,
                'description' => $description,
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => (object) $properties,
                    'required'   => [],
                ],
            ],
        ];
    }
}

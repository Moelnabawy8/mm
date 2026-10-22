<?php

namespace App\Services\Commitments;

use App\Models\Commitment;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every number the commitment and meeting-minutes screens show comes from here.
 *
 * "Overdue" and "missed" are definitions, not queries, and four screens plus a weekly
 * command need the same answer:
 *
 *   overdue — still open, deadline in the past. A live state, computed on read.
 *   missed  — the weekly command has since written that verdict down. A stored fact.
 *
 * Keeping both matters: a supervisor opening the page mid-week must see what is late
 * right now, while the weekly report needs a verdict that does not change after the
 * fact. Deriving "missed" on read instead would make last month's report shift every
 * time somebody re-ran it.
 */
class CommitmentBoard
{
    /** What one person owes. */
    public function owedBy(int $userId, ?string $status = null)
    {
        return $this->baseQuery()
            ->where('user_id', $userId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByRaw('FIELD(status, "open", "missed", "done", "cancelled")')
            ->orderByRaw('COALESCE(due_date, DATE_ADD(week_start, INTERVAL 6 DAY)) ASC')
            ->get();
    }

    /** What is owed to one person — the supervisor's view, and the whole of task 2. */
    public function owedTo(int $userId, ?string $status = null)
    {
        return $this->baseQuery()
            ->where('owed_to_id', $userId)
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByRaw('FIELD(status, "open", "missed", "done", "cancelled")')
            ->orderByRaw('COALESCE(due_date, DATE_ADD(week_start, INTERVAL 6 DAY)) ASC')
            ->get();
    }

    /** One week's commitments across the company — task 3's board. */
    public function week(string $weekStart, int $createdBy, ?int $departmentId = null)
    {
        return $this->baseQuery()
            ->where('created_by', $createdBy)
            ->where('week_start', $weekStart)
            ->when($departmentId, function ($q) use ($departmentId) {
                // A department's week means both what the department owes as a body and
                // what its people owe individually; showing only the former would hide
                // most of the work.
                $q->where(function ($inner) use ($departmentId) {
                    $inner->where('department_id', $departmentId)
                          ->orWhereIn('user_id', $this->userIdsInDepartment($departmentId));
                });
            })
            ->orderByRaw('FIELD(status, "open", "missed", "done", "cancelled")')
            ->orderBy('department_id')
            ->get();
    }

    /** Headline counters for a set of commitments already fetched. */
    public function tally($commitments): array
    {
        $open = $done = $missed = $overdue = $cancelled = 0;

        foreach ($commitments as $c) {
            switch ($c->status) {
                case Commitment::STATUS_DONE:      $done++; break;
                case Commitment::STATUS_MISSED:    $missed++; break;
                case Commitment::STATUS_CANCELLED: $cancelled++; break;
                default:
                    $open++;
                    if ($c->is_overdue) {
                        $overdue++;
                    }
            }
        }

        $judged = $done + $missed;

        return [
            'total'     => count($commitments),
            'open'      => $open,
            'overdue'   => $overdue,
            'done'      => $done,
            'missed'    => $missed,
            'cancelled' => $cancelled,
            // Kept rate counts only commitments that reached a verdict. Including the
            // still-open ones would make every fresh week look like a failure.
            'kept_rate' => $judged > 0 ? round($done / $judged * 100, 1) : null,
        ];
    }

    /** Per-department rollup for one week. */
    public function weekByDepartment(string $weekStart, int $createdBy): array
    {
        $rows = [];

        foreach ($this->week($weekStart, $createdBy) as $c) {
            $deptId = $c->department_id ?: $this->departmentOfUser($c->user_id);
            $key    = $deptId ?: 0;

            $rows[$key] ??= ['department_id' => $deptId, 'items' => []];
            $rows[$key]['items'][] = $c;
        }

        $names = Department::pluck('name', 'id');
        $out   = [];

        foreach ($rows as $key => $row) {
            $out[] = [
                'department_id' => $row['department_id'],
                'name'          => $row['department_id'] ? ($names[$row['department_id']] ?? __('Unknown')) : __('No department'),
            ] + $this->tally($row['items']);
        }

        usort($out, fn($a, $b) => [$b['overdue'], $b['total']] <=> [$a['overdue'], $a['total']]);

        return $out;
    }

    /** Supervisors who currently have anything owed to them. */
    public function supervisorsWithLoad(int $createdBy): array
    {
        $rows = DB::table('commitments')
            ->where('created_by', $createdBy)
            ->where('status', '!=', Commitment::STATUS_CANCELLED)
            ->select(
                'owed_to_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(status = "open") as open'),
                DB::raw('SUM(status = "done") as done'),
                DB::raw('SUM(status = "missed") as missed')
            )
            ->groupBy('owed_to_id')
            ->orderByDesc('open')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::whereIn('id', $rows->pluck('owed_to_id'))->pluck('name', 'id');

        return $rows->map(fn($r) => [
            'user_id' => $r->owed_to_id,
            'name'    => $names[$r->owed_to_id] ?? __('Unknown'),
            'total'   => (int) $r->total,
            'open'    => (int) $r->open,
            'done'    => (int) $r->done,
            'missed'  => (int) $r->missed,
        ])->all();
    }

    /* ------------------------------------------------------------- meetings */

    /**
     * Meetings whose date has passed with nothing written up.
     *
     * Task 4 is "ensure minutes are recorded", so this is the number the whole feature
     * exists to drive down, and it is what the weekly command chases.
     */
    public function meetingsAwaitingMinutes(int $createdBy, int $withinDays = 60)
    {
        return Meeting::where('created_by', $createdBy)
            ->where('status', '!=', Meeting::STATUS_CANCELLED)
            ->where('date', '<', now()->toDateString())
            ->where('date', '>=', now()->subDays($withinDays)->toDateString())
            ->where(function ($q) {
                $q->whereNull('minutes')->orWhere('minutes', '');
            })
            ->orderByDesc('date')
            ->get();
    }

    public function upcomingMeetings(int $createdBy, int $days = 14)
    {
        return Meeting::where('created_by', $createdBy)
            ->where('status', '!=', Meeting::STATUS_CANCELLED)
            ->whereBetween('date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->orderBy('date')
            ->orderBy('time')
            ->get();
    }

    public function meetingStats(int $createdBy, int $days = 90): array
    {
        $base = Meeting::where('created_by', $createdBy)
            ->where('status', '!=', Meeting::STATUS_CANCELLED)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->where('date', '<', now()->toDateString());

        $held = (clone $base)->count();

        $documented = (clone $base)
            ->whereNotNull('minutes')
            ->where('minutes', '!=', '')
            ->count();

        return [
            'held'        => $held,
            'documented'  => $documented,
            'missing'     => $held - $documented,
            'rate'        => $held > 0 ? round($documented / $held * 100, 1) : null,
            'upcoming'    => $this->upcomingMeetings($createdBy)->count(),
        ];
    }

    /* ------------------------------------------------------------ internals */

    private function baseQuery()
    {
        return Commitment::with(['owner', 'department', 'owedTo', 'meeting']);
    }

    private ?array $deptOfUser = null;

    /**
     * A user's department, via their employee record. Loaded once — resolving it per
     * row turned a 300-commitment board into 300 queries.
     */
    private function departmentOfUser(?int $userId): ?int
    {
        if (!$userId) {
            return null;
        }

        $this->deptOfUser ??= DB::table('employees')
            ->where('user_id', '>', 0)
            ->pluck('department_id', 'user_id')
            ->all();

        $d = $this->deptOfUser[$userId] ?? null;

        return $d ? (int) $d : null;
    }

    private function userIdsInDepartment(int $departmentId): array
    {
        return DB::table('employees')
            ->where('department_id', $departmentId)
            ->where('user_id', '>', 0)
            ->pluck('user_id')
            ->all();
    }
}

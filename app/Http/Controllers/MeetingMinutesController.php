<?php

namespace App\Http\Controllers;

use App\Models\Commitment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Commitments\CommitmentBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Meeting minutes — task 4, "ensure minutes are recorded".
 *
 * A separate controller from MeetingController on purpose. That one is a shared file
 * this ERP has carried since 2020, and the 2026-09-12 outage came from shipping a whole
 * copy of a shared file; adding several hundred lines to it would mean doing that
 * again. Minutes are also a different job from scheduling, usually done by a different
 * person under a different permission.
 *
 * The board is what makes the feature work rather than exist: the meetings module has
 * held zero rows since 2020, so a blank "write minutes" form nobody visits would change
 * nothing. The landing screen leads with meetings that have already happened and have
 * no write-up, because that is the number the task exists to drive down.
 */
class MeetingMinutesController extends Controller
{
    public function board(CommitmentBoard $board)
    {
        $user = Auth::user();

        if (!$this->canRecord() && !$user->can('manage meeting')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $creatorId = $user->creatorId();

        return view('meetings.minutes_board', [
            'awaiting'   => $board->meetingsAwaitingMinutes($creatorId),
            'upcoming'   => $board->upcomingMeetings($creatorId),
            'stats'      => $board->meetingStats($creatorId),
            'canRecord'  => $this->canRecord(),
        ]);
    }

    /** Write-up screen for one meeting: minutes, attendance, decisions. */
    public function edit($id)
    {
        $meeting = $this->find($id);

        if (!$meeting) {
            return redirect()->route('meetings.board')->with('error', __('Meeting not found.'));
        }

        $employeeIds = $meeting->employeeIds();

        // meeting_employees is the module's own attendance pivot, empty since 2020.
        // Attendance rows are created lazily on first save rather than backfilled, so a
        // meeting nobody has written up yet shows "not marked" instead of "absent".
        $attendance = DB::table('meeting_employees')
            ->where('meeting_id', $meeting->id)
            ->pluck('attended', 'employee_id');

        $employees = $employeeIds
            ? DB::table('employees')
                ->whereIn('id', $employeeIds)
                ->select('id', 'name', 'user_id', 'department_id')
                ->orderBy('name')
                ->get()
            : collect();

        return view('meetings.minutes_edit', [
            'meeting'     => $meeting,
            'employees'   => $employees,
            'attendance'  => $attendance,
            'commitments' => $meeting->commitments()->with(['owner', 'department', 'owedTo'])->get(),
            'people'      => $this->people(),
            'canRecord'   => $this->canRecord(),
        ]);
    }

    public function save(Request $request, $id)
    {
        if (!$this->canRecord()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $meeting = $this->find($id);

        if (!$meeting) {
            return redirect()->route('meetings.board')->with('error', __('Meeting not found.'));
        }

        $validator = \Validator::make($request->all(), [
            'minutes' => 'nullable|string|max:65535',
            'status'  => 'nullable|in:scheduled,held,cancelled',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput()->with('error', $validator->getMessageBag()->first());
        }

        DB::transaction(function () use ($request, $meeting) {
            $minutes = (string) $request->input('minutes');
            $hadText = trim(strip_tags($minutes)) !== '';

            $meeting->minutes = $minutes;

            // Stamp the author only when there is actually something written, so an
            // empty save does not record that somebody wrote up the meeting.
            if ($hadText) {
                $meeting->minutes_by = Auth::id();
                $meeting->minutes_at = now();

                if ($meeting->status === Meeting::STATUS_SCHEDULED) {
                    $meeting->status = Meeting::STATUS_HELD;
                }
            }

            if ($request->filled('status')) {
                $meeting->status = $request->input('status');
            }

            $meeting->save();

            // Attendance: one row per invited employee, present or not.
            $marks = (array) $request->input('attended', []);

            foreach ($meeting->employeeIds() as $employeeId) {
                $attended = array_key_exists($employeeId, $marks) ? 1 : 0;

                $exists = DB::table('meeting_employees')
                    ->where('meeting_id', $meeting->id)
                    ->where('employee_id', $employeeId)
                    ->exists();

                if ($exists) {
                    DB::table('meeting_employees')
                        ->where('meeting_id', $meeting->id)
                        ->where('employee_id', $employeeId)
                        ->update(['attended' => $attended, 'updated_at' => now()]);
                } else {
                    DB::table('meeting_employees')->insert([
                        'meeting_id'  => $meeting->id,
                        'employee_id' => $employeeId,
                        'attended'    => $attended,
                        'created_by'  => Auth::user()->creatorId(),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        });

        return redirect()->route('meetings.minutes', $meeting->id)->with('success', __('Minutes saved.'));
    }

    /**
     * Turn one decision from the minutes into a commitment.
     *
     * This is the join between task 4 and tasks 2 and 3: a decision with nobody's name
     * and no date is the thing minutes are usually criticised for, so the form makes
     * both mandatory and the result becomes a tracked commitment rather than a line of
     * prose.
     */
    public function addDecision(Request $request, $id)
    {
        if (!$this->canRecord()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $meeting = $this->find($id);

        if (!$meeting) {
            return redirect()->route('meetings.board')->with('error', __('Meeting not found.'));
        }

        $validator = \Validator::make($request->all(), [
            'title'      => 'required|string|max:191',
            'user_id'    => 'required|integer|exists:users,id',
            'owed_to_id' => 'required|integer|exists:users,id',
            'due_date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput()->with('error', $validator->getMessageBag()->first());
        }

        Commitment::create([
            'title'       => $request->title,
            'description' => $request->description,
            'user_id'     => (int) $request->user_id,
            'owed_to_id'  => (int) $request->owed_to_id,
            'cadence'     => Commitment::CADENCE_ONCE,
            'due_date'    => $request->due_date,
            'status'      => Commitment::STATUS_OPEN,
            'meeting_id'  => $meeting->id,
            'created_by'  => Auth::user()->creatorId(),
        ]);

        return redirect()->route('meetings.minutes', $meeting->id)
            ->with('success', __('Decision recorded as a commitment.'));
    }

    /* ------------------------------------------------------------ internals */

    private function canRecord(): bool
    {
        return Auth::user()->can('record meeting minutes') || Auth::user()->can('manage commitment');
    }

    private function find($id): ?Meeting
    {
        return Meeting::where('created_by', Auth::user()->creatorId())->find($id);
    }

    private function people()
    {
        $creatorId = Auth::user()->creatorId();

        return User::where(function ($q) use ($creatorId) {
                $q->where('created_by', $creatorId)->orWhere('id', $creatorId);
            })
            ->where('type', '!=', 'client')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}

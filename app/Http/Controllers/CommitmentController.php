<?php

namespace App\Http\Controllers;

use App\Models\Commitment;
use App\Models\CommitmentUpdate;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Commitments\CommitmentBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Commitments — task 2 (owed to supervisors) and task 3 (weekly, per department or
 * employee) are one feature here; see the migration for why.
 *
 * Access rule, stated once: the person who owes a commitment and the person it is owed
 * to can both see and update it, with no permission needed. Anyone holding
 * 'manage commitment' sees everything. That is deliberate — gating "my own
 * commitments" behind a permission would mean granting it to all 68 employees.
 *
 * Everything is scoped on creatorId() like the rest of this ERP.
 */
class CommitmentController extends Controller
{
    /** Overview: the week, the departments, who is carrying what. */
    public function index(Request $request, CommitmentBoard $board)
    {
        $user      = Auth::user();
        $creatorId = $user->creatorId();
        $week      = $this->validWeek($request->input('week'));

        if (!$user->can('manage commitment')) {
            return redirect()->route('commitments.mine');
        }

        $items = $board->week($week, $creatorId);

        return view('commitments.index', [
            'week'          => $week,
            'prevWeek'      => \Carbon\Carbon::parse($week)->subWeek()->toDateString(),
            'nextWeek'      => \Carbon\Carbon::parse($week)->addWeek()->toDateString(),
            'isCurrentWeek' => $week === Commitment::weekStart(),
            'items'         => $items,
            'tally'         => $board->tally($items),
            'byDepartment'  => $board->weekByDepartment($week, $creatorId),
            'supervisors'   => $board->supervisorsWithLoad($creatorId),
            'meetingStats'  => $board->meetingStats($creatorId),
        ]);
    }

    /** What I owe. Needs no permission — it is the signed-in user's own work. */
    public function mine(CommitmentBoard $board)
    {
        $items = $board->owedBy(Auth::id());

        return view('commitments.mine', [
            'items' => $items,
            'tally' => $board->tally($items),
        ]);
    }

    /** What is owed to me. The supervisor's view, and the whole of task 2. */
    public function owedToMe(CommitmentBoard $board)
    {
        $items = $board->owedTo(Auth::id());

        return view('commitments.owed', [
            'items' => $items,
            'tally' => $board->tally($items),
        ]);
    }

    public function create(Request $request)
    {
        if (!$this->canCreate()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        return view('commitments.create', [
            'people'      => $this->people(),
            'departments' => Department::orderBy('name')->get(),
            'week'        => Commitment::weekStart($request->input('week')),
            'meeting'     => $request->input('meeting_id')
                ? Meeting::find($request->input('meeting_id'))
                : null,
        ]);
    }

    public function store(Request $request)
    {
        if (!$this->canCreate()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'title'      => 'required|string|max:191',
            'owed_to_id' => 'required|integer|exists:users,id',
            'cadence'    => 'required|in:once,weekly',
            'owner_type' => 'required|in:user,department',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput()->with('error', $validator->getMessageBag()->first());
        }

        $data = $this->ownerFields($request);

        if ($data === null) {
            return redirect()->back()->withInput()
                ->with('error', __('Choose who owes this commitment.'));
        }

        $weekly = $request->cadence === Commitment::CADENCE_WEEKLY;

        if (!$weekly && !$request->due_date) {
            return redirect()->back()->withInput()
                ->with('error', __('A one-off commitment needs a due date.'));
        }

        Commitment::create($data + [
            'title'       => $request->title,
            'description' => $request->description,
            'owed_to_id'  => (int) $request->owed_to_id,
            'cadence'     => $request->cadence,
            'due_date'    => $weekly ? null : $request->due_date,
            'week_start'  => $weekly ? Commitment::weekStart($request->week_start) : null,
            'recurs'      => $weekly && $request->boolean('recurs'),
            'meeting_id'  => $request->meeting_id ?: null,
            'status'      => Commitment::STATUS_OPEN,
            'created_by'  => Auth::user()->creatorId(),
        ]);

        if ($request->meeting_id) {
            return redirect()->route('meetings.minutes', $request->meeting_id)
                ->with('success', __('Commitment added from this meeting.'));
        }

        return redirect()->route('commitments.index')->with('success', __('Commitment created.'));
    }

    public function show($id, CommitmentBoard $board)
    {
        $commitment = $this->findVisible($id);

        if (!$commitment) {
            return redirect()->route('commitments.mine')->with('error', __('Commitment not found.'));
        }

        return view('commitments.show', [
            'commitment' => $commitment,
            'canUpdate'  => $this->canTouch($commitment),
            'canManage'  => Auth::user()->can('manage commitment'),
        ]);
    }

    public function edit($id)
    {
        $commitment = $this->findVisible($id);

        if (!$commitment || !$this->canTouch($commitment)) {
            return redirect()->route('commitments.mine')->with('error', __('Permission Denied.'));
        }

        return view('commitments.edit', [
            'commitment'  => $commitment,
            'people'      => $this->people(),
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $commitment = $this->findVisible($id);

        if (!$commitment || !$this->canTouch($commitment)) {
            return redirect()->route('commitments.mine')->with('error', __('Permission Denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'title'      => 'required|string|max:191',
            'owed_to_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput()->with('error', $validator->getMessageBag()->first());
        }

        $commitment->fill([
            'title'       => $request->title,
            'description' => $request->description,
            'owed_to_id'  => (int) $request->owed_to_id,
            'due_date'    => $commitment->cadence === Commitment::CADENCE_WEEKLY ? null : $request->due_date,
            'recurs'      => $commitment->cadence === Commitment::CADENCE_WEEKLY && $request->boolean('recurs'),
        ])->save();

        return redirect()->route('commitments.show', $commitment->id)->with('success', __('Commitment updated.'));
    }

    /**
     * A check-in: a note, a progress figure, optionally a new status.
     *
     * Status and progress move together — marking something done while it still reads
     * 40% leaves two fields disagreeing on the same screen.
     */
    public function addUpdate(Request $request, $id)
    {
        $commitment = $this->findVisible($id);

        if (!$commitment || !$this->canTouch($commitment)) {
            return redirect()->route('commitments.mine')->with('error', __('Permission Denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'note'     => 'nullable|string|max:2000',
            'progress' => 'nullable|integer|min:0|max:100',
            'status'   => 'nullable|in:open,done,missed,cancelled',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withInput()->with('error', $validator->getMessageBag()->first());
        }

        if (!$request->filled('note') && !$request->filled('progress') && !$request->filled('status')) {
            return redirect()->back()->with('error', __('Write a note, set progress, or change the status.'));
        }

        DB::transaction(function () use ($request, $commitment) {
            $status = $request->input('status');

            if ($status) {
                $commitment->status = $status;

                if ($status === Commitment::STATUS_DONE) {
                    $commitment->progress     = 100;
                    $commitment->completed_at = now();
                } elseif ($status === Commitment::STATUS_OPEN) {
                    $commitment->completed_at = null;
                }
            }

            if ($request->filled('progress') && $status !== Commitment::STATUS_DONE) {
                $commitment->progress = (int) $request->progress;
            }

            $commitment->save();

            CommitmentUpdate::create([
                'commitment_id' => $commitment->id,
                'user_id'       => Auth::id(),
                'note'          => $request->note,
                'progress'      => $commitment->progress,
                'status_after'  => $status ?: null,
                'created_by'    => Auth::user()->creatorId(),
            ]);
        });

        return redirect()->route('commitments.show', $commitment->id)->with('success', __('Update recorded.'));
    }

    public function destroy($id)
    {
        if (!Auth::user()->can('manage commitment')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $commitment = Commitment::where('created_by', Auth::user()->creatorId())->find($id);

        if (!$commitment) {
            return redirect()->back()->with('error', __('Commitment not found.'));
        }

        $commitment->updates()->delete();
        $commitment->delete();

        return redirect()->route('commitments.index')->with('success', __('Commitment deleted.'));
    }

    /* ------------------------------------------------------------ internals */

    private function canCreate(): bool
    {
        return Auth::user()->can('create commitment') || Auth::user()->can('manage commitment');
    }

    /** Owner, supervisor, or administrator. */
    private function canTouch(Commitment $c): bool
    {
        $user = Auth::user();

        return $user->can('manage commitment')
            || (int) $c->user_id === (int) $user->id
            || (int) $c->owed_to_id === (int) $user->id;
    }

    private function findVisible($id): ?Commitment
    {
        $user = Auth::user();

        $commitment = Commitment::with(['owner', 'department', 'owedTo', 'meeting', 'updates.author'])
            ->where('created_by', $user->creatorId())
            ->find($id);

        if (!$commitment) {
            return null;
        }

        if ($user->can('manage commitment')
            || (int) $commitment->user_id === (int) $user->id
            || (int) $commitment->owed_to_id === (int) $user->id) {
            return $commitment;
        }

        // A department commitment is visible to that department's members.
        if ($commitment->department_id) {
            $mine = DB::table('employees')->where('user_id', $user->id)->value('department_id');

            if ($mine && (int) $mine === (int) $commitment->department_id) {
                return $commitment;
            }
        }

        return null;
    }

    /** user_id XOR department_id, or null when neither was chosen. */
    private function ownerFields(Request $request): ?array
    {
        if ($request->owner_type === 'department' && $request->department_id) {
            return ['department_id' => (int) $request->department_id, 'user_id' => null];
        }

        if ($request->owner_type === 'user' && $request->user_id) {
            return ['user_id' => (int) $request->user_id, 'department_id' => null];
        }

        return null;
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

    /** A week parameter is only ever a Monday; anything else is normalised to one. */
    private function validWeek($input): string
    {
        try {
            return Commitment::weekStart($input ?: now());
        } catch (\Throwable $e) {
            return Commitment::weekStart();
        }
    }
}

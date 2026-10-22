<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ProjectTask;
use App\Models\QualityCriterion;
use App\Models\QualityDepartmentReviewer;
use App\Models\TaskQualityReview;
use App\Models\User;
use App\Services\Quality\QualityBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The quality section that lives inside each department.
 *
 * Not to be confused with QualityEvaluationController, which scores sales *leads*
 * (49,069 rows keyed on lead_id) and has nothing to do with departments or tasks.
 * The names collide; the features do not.
 *
 * Everything is scoped on creatorId() like the rest of this ERP, so one company's
 * reviews never surface in another's — the same scoping SetSalaryController was
 * missing until 2026-09-12.
 */
class QualityReviewController extends Controller
{
    /** Overview: every department, what it completed, what was reviewed, how it scored. */
    public function index(QualityBoard $board)
    {
        $user = Auth::user();

        if (!$user->can('manage quality criteria')
            && !$user->can('review task quality')
            && !$user->can('view quality report')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $rows = $board->departments($user->creatorId());

        // A reviewer who is not an administrator sees only the departments they
        // review; an empty screen is clearer than someone else's scoreboard.
        if (!$user->can('manage quality criteria') && !$user->can('view quality report')) {
            $mine = $board->departmentsUserReviews($user->id);
            $rows = array_values(array_filter($rows, fn($r) => in_array($r['id'], $mine, true)));
        }

        return view('quality.index', [
            'rows'       => $rows,
            'canConfig'  => $user->can('manage quality criteria'),
            'totals'     => [
                'pending'  => array_sum(array_column($rows, 'pending')),
                'reviews'  => array_sum(array_column($rows, 'reviews')),
                'active'   => count(array_filter($rows, fn($r) => $r['is_active'])),
            ],
        ]);
    }

    /** One department's quality section: criteria, reviewers, queue, scores. */
    public function department($id, QualityBoard $board)
    {
        $user = Auth::user();
        $dept = Department::find($id);

        if (!$dept) {
            return redirect()->route('quality.index')->with('error', __('Department not found.'));
        }

        $canConfig = $user->can('manage quality criteria');
        $canReview = $board->canReview($user, $dept->id);

        if (!$canConfig && !$canReview && !$user->can('view quality report')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $creatorId = $user->creatorId();

        return view('quality.department', [
            'department' => $dept,
            'criteria'   => QualityCriterion::forDepartment($dept->id)->orderBy('sort_order')->orderBy('id')->get(),
            'reviewers'  => $board->reviewers($dept->id),
            'summary'    => $board->summary($dept->id, $creatorId),
            'pending'    => $board->pendingTasks($dept->id),
            'reviews'    => $board->reviews($dept->id, $creatorId, 50),
            'byEmployee' => $board->byEmployee($dept->id, $creatorId),
            'staff'      => $this->assignableUsers($creatorId),
            'canConfig'  => $canConfig,
            'canReview'  => $canReview,
            'defaults'   => QualityCriterion::defaults(),
        ]);
    }

    /* ------------------------------------------------------------------- criteria */

    public function storeCriterion(Request $request, $departmentId)
    {
        if (!Auth::user()->can('manage quality criteria')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name'      => 'required|string|max:191',
            'weight'    => 'required|integer|min:1|max:10',
            'max_score' => 'required|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        QualityCriterion::create([
            'department_id' => (int) $departmentId,
            'name'          => $request->name,
            'description'   => $request->description,
            'weight'        => (int) $request->weight,
            'max_score'     => (int) $request->max_score,
            'sort_order'    => (int) QualityCriterion::forDepartment($departmentId)->max('sort_order') + 1,
            'is_active'     => true,
            'created_by'    => Auth::user()->creatorId(),
        ]);

        return redirect()->back()->with('success', __('Criterion added.'));
    }

    /** Drop the standard set into a department that has none yet. */
    public function seedCriteria($departmentId)
    {
        if (!Auth::user()->can('manage quality criteria')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        if (QualityCriterion::forDepartment($departmentId)->exists()) {
            return redirect()->back()->with('error', __('This department already has criteria.'));
        }

        $order = 0;

        foreach (QualityCriterion::defaults() as $c) {
            QualityCriterion::create([
                'department_id' => (int) $departmentId,
                'name'          => $c['name'],
                'description'   => $c['description'],
                'weight'        => $c['weight'],
                'max_score'     => 5,
                'sort_order'    => $order++,
                'is_active'     => true,
                'created_by'    => Auth::user()->creatorId(),
            ]);
        }

        return redirect()->back()->with('success', __('Standard criteria added.'));
    }

    /**
     * Criteria are retired, never deleted, once they have been scored against —
     * an old review renders its own snapshot, but the list must still explain
     * what a live criterion is.
     */
    public function toggleCriterion($id)
    {
        if (!Auth::user()->can('manage quality criteria')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $criterion = QualityCriterion::find($id);

        if (!$criterion) {
            return redirect()->back()->with('error', __('Criterion not found.'));
        }

        $criterion->is_active = !$criterion->is_active;
        $criterion->save();

        return redirect()->back()->with(
            'success',
            $criterion->is_active ? __('Criterion re-activated.') : __('Criterion retired.')
        );
    }

    /* ------------------------------------------------------------------ reviewers */

    public function saveReviewers(Request $request, $departmentId)
    {
        if (!Auth::user()->can('manage quality criteria')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('reviewers', [])))));

        DB::transaction(function () use ($departmentId, $ids) {
            QualityDepartmentReviewer::where('department_id', $departmentId)->delete();

            foreach ($ids as $uid) {
                QualityDepartmentReviewer::create([
                    'department_id' => (int) $departmentId,
                    'user_id'       => $uid,
                    'created_by'    => Auth::user()->creatorId(),
                ]);
            }
        });

        return redirect()->back()->with('success', __('Reviewers saved.'));
    }

    /* -------------------------------------------------------------------- reviews */

    /** The review form for one completed task. */
    public function reviewForm($taskId, QualityBoard $board)
    {
        $task = ProjectTask::find($taskId);

        if (!$task) {
            return redirect()->route('quality.index')->with('error', __('Task not found.'));
        }

        $departmentId = $board->departmentOfTask($task);

        if (!$departmentId) {
            return redirect()->route('quality.index')
                ->with('error', __('This task\'s project is not filed under a department, so it cannot be reviewed.'));
        }

        if (!$board->canReview(Auth::user(), $departmentId)) {
            return redirect()->back()->with('error', __('You are not a reviewer for this department.'));
        }

        $criteria = QualityCriterion::forDepartment($departmentId)->active()->orderBy('sort_order')->orderBy('id')->get();

        if ($criteria->isEmpty()) {
            return redirect()->route('quality.department', $departmentId)
                ->with('error', __('Add at least one criterion before reviewing.'));
        }

        return view('quality.review', [
            'task'       => $task,
            'department' => Department::find($departmentId),
            'criteria'   => $criteria,
            'existing'   => TaskQualityReview::where('project_task_id', $task->id)->first(),
            'onTime'     => $board->wasOnTime($task),
            'assignees'  => $task->users(),
        ]);
    }

    public function storeReview(Request $request, $taskId, QualityBoard $board)
    {
        $task = ProjectTask::find($taskId);

        if (!$task) {
            return redirect()->route('quality.index')->with('error', __('Task not found.'));
        }

        $departmentId = $board->departmentOfTask($task);

        if (!$departmentId || !$board->canReview(Auth::user(), $departmentId)) {
            return redirect()->back()->with('error', __('You are not a reviewer for this department.'));
        }

        $criteria = QualityCriterion::forDepartment($departmentId)->active()->orderBy('sort_order')->get();

        if ($criteria->isEmpty()) {
            return redirect()->back()->with('error', __('Add at least one criterion before reviewing.'));
        }

        // Keep only scores for criteria that belong to this department, so a tampered
        // form cannot invent a criterion id and skew the weighted total.
        $posted = (array) $request->input('scores', []);
        $scores = [];

        foreach ($criteria as $c) {
            if (!array_key_exists($c->id, $posted) || $posted[$c->id] === '' || $posted[$c->id] === null) {
                continue;
            }

            $scores[$c->id] = max(0, min((int) $posted[$c->id], $c->max_score));
        }

        if (count($scores) !== $criteria->count()) {
            return redirect()->back()->withInput()->with('error', __('Score every criterion before saving.'));
        }

        $total = TaskQualityReview::computeTotal($scores, $criteria);

        TaskQualityReview::updateOrCreate(
            ['project_task_id' => $task->id],
            [
                'department_id'     => $departmentId,
                'project_id'        => (int) $task->project_id,
                'reviewer_id'       => Auth::id(),
                'assignee_ids'      => (string) $task->assign_to,
                'scores'            => $scores,
                'criteria_snapshot' => $criteria->map(fn($c) => [
                    'id'        => $c->id,
                    'name'      => $c->name,
                    'weight'    => $c->weight,
                    'max_score' => $c->max_score,
                ])->all(),
                'total_score'       => $total,
                'was_on_time'       => $board->wasOnTime($task),
                'notes'             => $request->input('notes'),
                'reviewed_at'       => now(),
                'created_by'        => Auth::user()->creatorId(),
            ]
        );

        return redirect()->route('quality.department', $departmentId)
            ->with('success', __('Review saved.') . ' ' . $total . '%');
    }

    public function destroyReview($id, QualityBoard $board)
    {
        $review = TaskQualityReview::where('created_by', Auth::user()->creatorId())->find($id);

        if (!$review) {
            return redirect()->back()->with('error', __('Review not found.'));
        }

        if (!$board->canReview(Auth::user(), $review->department_id)) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $departmentId = $review->department_id;
        $review->delete();

        return redirect()->route('quality.department', $departmentId)
            ->with('success', __('Review removed. The task is back in the queue.'));
    }

    /* --------------------------------------------------------------------- report */

    public function report(Request $request, QualityBoard $board)
    {
        if (!Auth::user()->can('view quality report') && !Auth::user()->can('manage quality criteria')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $creatorId = Auth::user()->creatorId();
        $from      = $request->input('from', now()->subDays(90)->toDateString());
        $to        = $request->input('to', now()->toDateString());

        $rows = DB::table('task_quality_reviews as r')
            ->join('departments as d', 'd.id', '=', 'r.department_id')
            ->where('r.created_by', $creatorId)
            ->whereBetween('r.reviewed_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select(
                'd.id',
                'd.name',
                DB::raw('COUNT(*) as reviews'),
                DB::raw('ROUND(AVG(r.total_score), 1) as avg_score'),
                DB::raw('SUM(r.was_on_time = 1) as on_time'),
                DB::raw('MIN(r.total_score) as worst'),
                DB::raw('MAX(r.total_score) as best')
            )
            ->groupBy('d.id', 'd.name')
            ->orderByDesc('avg_score')
            ->get();

        return view('quality.report', [
            'rows'   => $rows,
            'from'   => $from,
            'to'     => $to,
            'board'  => $board->departments($creatorId),
        ]);
    }

    /* ------------------------------------------------------------------ internals */

    /** Users this company can name as reviewers. */
    private function assignableUsers(int $creatorId)
    {
        return User::where(function ($q) use ($creatorId) {
                $q->where('created_by', $creatorId)->orWhere('id', $creatorId);
            })
            ->where('type', '!=', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}

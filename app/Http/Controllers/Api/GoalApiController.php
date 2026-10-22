<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GoalTracking;
use App\Models\Goal;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GoalApiController extends Controller
{
    use ApiResponser;

    public function getGoals()
    {
        $goals = Goal::where('created_by', Auth::user()->creatorId())
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['goals' => $goals], 'Goals retrieved.');
    }

    public function createGoal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'amount' => 'required|numeric|min:0',
            'is_display' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $goal = new Goal();
        $goal->name = $request->name;
        $goal->type = $request->type;
        $goal->from = $request->from;
        $goal->to = $request->to;
        $goal->amount = $request->amount;
        $goal->is_display = $request->has('is_display') ? 1 : 0;
        $goal->created_by = Auth::user()->creatorId();
        $goal->save();

        return $this->success(['goal' => $goal], 'Goal created successfully.');
    }

    public function getGoal($id)
    {
        $goal = Goal::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$goal) {
            return $this->error('Goal not found.', 404);
        }

        return $this->success(['goal' => $goal], 'Goal detail.');
    }

    public function updateGoal(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'amount' => 'required|numeric|min:0',
            'is_display' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $goal = Goal::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$goal) {
            return $this->error('Goal not found.', 404);
        }

        $goal->name = $request->name;
        $goal->type = $request->type;
        $goal->from = $request->from;
        $goal->to = $request->to;
        $goal->amount = $request->amount;
        $goal->is_display = $request->has('is_display') ? 1 : 0;
        $goal->save();

        return $this->success(['goal' => $goal], 'Goal updated successfully.');
    }

    public function deleteGoal($id)
    {
        $goal = Goal::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$goal) {
            return $this->error('Goal not found.', 404);
        }

        $goal->delete();

        return $this->success([], 'Goal deleted successfully.');
    }

    public function getGoalTypes()
    {
        $types = Goal::$goalType;
        return $this->success(['types' => $types], 'Goal types retrieved.');
    }

    public function getGoalStats()
    {
        $goals = Goal::where('created_by', Auth::user()->creatorId())->get();

        $totalGoals = $goals->count();
        $activeGoals = $goals->where('to', '>=', date('Y-m-d'))->count();
        $completedGoals = $goals->where('to', '<', date('Y-m-d'))->count();
        $displayGoals = $goals->where('is_display', 1)->count();

        // Group by type
        $goalsByType = $goals->groupBy('type')->map(function ($group) {
            return $group->count();
        });

        return $this->success([
            'total_goals' => $totalGoals,
            'active_goals' => $activeGoals,
            'completed_goals' => $completedGoals,
            'display_goals' => $displayGoals,
            'goals_by_type' => $goalsByType
        ], 'Goal statistics retrieved.');
    }

    public function getActiveGoals()
    {
        $today = date('Y-m-d');
        $goals = Goal::where('created_by', Auth::user()->creatorId())
            ->where('to', '>=', $today)
            ->orderBy('to', 'asc')
            ->get();

        return $this->success(['goals' => $goals], 'Active goals retrieved.');
    }

    public function getCompletedGoals()
    {
        $today = date('Y-m-d');
        $goals = Goal::where('created_by', Auth::user()->creatorId())
            ->where('to', '<', $today)
            ->orderBy('to', 'desc')
            ->get();

        return $this->success(['goals' => $goals], 'Completed goals retrieved.');
    }

    public function getDisplayGoals()
    {
        $goals = Goal::where('created_by', Auth::user()->creatorId())
            ->where('is_display', 1)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['goals' => $goals], 'Display goals retrieved.');
    }
}

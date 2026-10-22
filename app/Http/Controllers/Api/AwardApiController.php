<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Award;
use App\Models\AwardType;
use App\Models\Employee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AwardApiController extends Controller
{
    use ApiResponser;

    public function getAwards()
    {
        $user = Auth::user();

        if ($user->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $awards = Award::where('employee_id', '=', $emp->id)
                ->with(['employee', 'awardType'])
                ->orderBy('date', 'desc')
                ->get();
        } else {
            $awards = Award::where('created_by', '=', Auth::user()->creatorId())
                ->with(['employee', 'awardType'])
                ->orderBy('date', 'desc')
                ->get();
        }

        return $this->success(['awards' => $awards], 'Awards retrieved.');
    }

    public function createAward(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'award_type' => 'required|exists:award_types,id',
            'date' => 'required|date',
            'gift' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if employee belongs to user's company
        $employee = Employee::where('id', $request->employee_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        // Check if award type belongs to user's company
        $awardType = AwardType::where('id', $request->award_type)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$awardType) {
            return $this->error('Award type not found or access denied.', 404);
        }

        $award = new Award();
        $award->employee_id = $request->employee_id;
        $award->award_type = $request->award_type;
        $award->date = $request->date;
        $award->gift = $request->gift;
        $award->description = $request->description;
        $award->created_by = Auth::user()->creatorId();
        $award->save();

        // Send notifications
        $setting = Utility::settings(Auth::user()->creatorId());
        $emp = Employee::find($request->employee_id);
        $awardType = AwardType::find($request->award_type);

        $awardNotificationArr = [
            'award_name' => $awardType->name,
            'employee_name' => $emp->name,
            'award_date' => $request->date,
        ];

        // Slack Notification
        if (isset($setting['award_notification']) && $setting['award_notification'] == 1) {
            Utility::send_slack_msg('new_award', $awardNotificationArr);
        }

        // Telegram Notification
        if (isset($setting['telegram_award_notification']) && $setting['telegram_award_notification'] == 1) {
            Utility::send_telegram_msg('new_award', $awardNotificationArr);
        }

        // Send Email
        $settings = Utility::settings();
        if ($settings['new_award'] == 1) {
            $awardArr = [
                'award_name' => $awardType->name,
                'award_email' => $emp->email,
                'employee_name' => $emp->name,
            ];

            $resp = Utility::sendEmailTemplate('new_award', [$emp->id => $emp->email], $awardArr);
        }

        // Webhook
        $module = 'New Award';
        $webhook = Utility::webhookSetting($module);
        if ($webhook) {
            $parameter = json_encode($award);
            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
        }

        $message = 'Award created successfully.';
        if (isset($resp) && $resp['is_success'] == false && !empty($resp['error'])) {
            $message .= ' Email notification failed: ' . $resp['error'];
        }

        return $this->success(['award' => $award], $message);
    }

    public function getAward($id)
    {
        $award = Award::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['employee', 'awardType'])
            ->first();

        if (!$award) {
            return $this->error('Award not found.', 404);
        }

        // Check if employee has access to this award
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($award->employee_id != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        return $this->success(['award' => $award], 'Award detail.');
    }

    public function updateAward(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'award_type' => 'required|exists:award_types,id',
            'date' => 'required|date',
            'gift' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $award = Award::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$award) {
            return $this->error('Award not found.', 404);
        }

        // Check if employee belongs to user's company
        $employee = Employee::where('id', $request->employee_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        // Check if award type belongs to user's company
        $awardType = AwardType::where('id', $request->award_type)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$awardType) {
            return $this->error('Award type not found or access denied.', 404);
        }

        $award->employee_id = $request->employee_id;
        $award->award_type = $request->award_type;
        $award->date = $request->date;
        $award->gift = $request->gift;
        $award->description = $request->description;
        $award->save();

        return $this->success(['award' => $award], 'Award updated successfully.');
    }

    public function deleteAward($id)
    {
        $award = Award::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$award) {
            return $this->error('Award not found.', 404);
        }

        // Check if employee has access to delete this award
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($award->employee_id != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        $award->delete();

        return $this->success([], 'Award deleted successfully.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getAwardTypes()
    {
        $awardTypes = AwardType::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name']);

        return $this->success(['award_types' => $awardTypes], 'Award types retrieved.');
    }

    public function getMyAwards()
    {
        if (Auth::user()->type != 'Employee') {
            return $this->error('This endpoint is for employees only.', 403);
        }

        $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
        $awards = Award::where('employee_id', '=', $emp->id)
            ->with(['awardType'])
            ->orderBy('date', 'desc')
            ->get();

        return $this->success(['awards' => $awards], 'My awards retrieved.');
    }

    public function getAwardsByEmployee($employeeId)
    {
        // Check if employee belongs to user's company
        $employee = Employee::where('id', $employeeId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        $awards = Award::where('employee_id', '=', $employeeId)
            ->with(['awardType'])
            ->orderBy('date', 'desc')
            ->get();

        return $this->success(['awards' => $awards], 'Awards by employee retrieved.');
    }

    public function getAwardsByType($awardTypeId)
    {
        // Check if award type belongs to user's company
        $awardType = AwardType::where('id', $awardTypeId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$awardType) {
            return $this->error('Award type not found or access denied.', 404);
        }

        $awards = Award::where('award_type', '=', $awardTypeId)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['employee'])
            ->orderBy('date', 'desc')
            ->get();

        return $this->success(['awards' => $awards], 'Awards by type retrieved.');
    }

    public function getAwardsByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = Award::where('created_by', Auth::user()->creatorId())
            ->whereBetween('date', [$request->start_date, $request->end_date]);

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee_id', '=', $emp->id);
        }

        $awards = $query->with(['employee', 'awardType'])
            ->orderBy('date', 'desc')
            ->get();

        return $this->success(['awards' => $awards], 'Awards by date range retrieved.');
    }

    public function getAwardStats()
    {
        $query = Award::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee_id', '=', $emp->id);
        }

        $totalAwards = $query->count();
        $currentYearAwards = $query->whereYear('date', date('Y'))->count();
        $currentMonthAwards = $query->whereYear('date', date('Y'))->whereMonth('date', date('m'))->count();

        // Group by award type
        $awardsByType = $query->with('awardType')
            ->get()
            ->groupBy('awardType.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by employee
        $awardsByEmployee = $query->with('employee')
            ->get()
            ->groupBy('employee.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by month
        $awardsByMonth = $query->get()
            ->groupBy(function ($award) {
                return date('Y-m', strtotime($award->date));
            })
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_awards' => $totalAwards,
            'current_year_awards' => $currentYearAwards,
            'current_month_awards' => $currentMonthAwards,
            'awards_by_type' => $awardsByType,
            'awards_by_employee' => $awardsByEmployee,
            'awards_by_month' => $awardsByMonth
        ], 'Award statistics retrieved.');
    }

    public function getRecentAwards($limit = 10)
    {
        $query = Award::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('employee_id', '=', $emp->id);
        }

        $awards = $query->with(['employee', 'awardType'])
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();

        return $this->success(['awards' => $awards], 'Recent awards retrieved.');
    }
}

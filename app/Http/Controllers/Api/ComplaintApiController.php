<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ComplaintApiController extends Controller
{
    use ApiResponser;

    public function getComplaints()
    {
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $complaints = Complaint::where('complaint_from', '=', $emp->id)
                ->with(['complaintFrom', 'complaintAgainst'])
                ->get();
        } else {
            $complaints = Complaint::where('created_by', '=', Auth::user()->creatorId())
                ->with(['complaintFrom', 'complaintAgainst'])
                ->get();
        }

        return $this->success(['complaints' => $complaints], 'Complaints retrieved.');
    }

    public function createComplaint(Request $request)
    {
        if (Auth::user()->type != 'Employee') {
            $validator = Validator::make($request->all(), [
                'complaint_from' => 'required|exists:employees,id',
                'complaint_against' => 'required|exists:employees,id',
                'title' => 'required|string|max:255',
                'complaint_date' => 'required|date',
                'description' => 'nullable|string',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'complaint_against' => 'required|exists:employees,id',
                'title' => 'required|string|max:255',
                'complaint_date' => 'required|date',
                'description' => 'nullable|string',
            ]);
        }

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if employees belong to user's company
        if (Auth::user()->type != 'Employee') {
            $fromEmployee = Employee::where('id', $request->complaint_from)
                ->where('created_by', Auth::user()->creatorId())
                ->first();
            if (!$fromEmployee) {
                return $this->error('Complaint from employee not found or access denied.', 404);
            }
        }

        $againstEmployee = Employee::where('id', $request->complaint_against)
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        if (!$againstEmployee) {
            return $this->error('Complaint against employee not found or access denied.', 404);
        }

        // Prevent same employee selection
        if (Auth::user()->type != 'Employee') {
            if ($request->complaint_from == $request->complaint_against) {
                return $this->error('Complaint from and against cannot be the same employee.', 400);
            }
        } else {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($emp->id == $request->complaint_against) {
                return $this->error('You cannot file a complaint against yourself.', 400);
            }
        }

        $complaint = new Complaint();
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $complaint->complaint_from = $emp->id;
        } else {
            $complaint->complaint_from = $request->complaint_from;
        }
        $complaint->complaint_against = $request->complaint_against;
        $complaint->title = $request->title;
        $complaint->complaint_date = $request->complaint_date;
        $complaint->description = $request->description;
        $complaint->created_by = Auth::user()->creatorId();
        $complaint->save();

        // Send Email
        $settings = Utility::settings();
        if (isset($settings['complaint_resent']) && $settings['complaint_resent'] == 1) {
            $employee = Employee::find($complaint->complaint_against);
            $complaintArr = [
                'complaint_name' => $employee->name,
                'complaint_title' => $complaint->title,
                'complaint_against' => $complaint->complaint_against,
                'complaint_date' => $complaint->complaint_date,
                'complaint_description' => $complaint->description,
            ];

            $resp = Utility::sendEmailTemplate('complaint_resent', [$employee->id => $employee->email], $complaintArr);

            $message = 'Complaint successfully created.';
            if ($resp['is_success'] == false && !empty($resp['error'])) {
                $message .= ' Email notification failed: ' . $resp['error'];
            }

            return $this->success(['complaint' => $complaint], $message);
        }

        return $this->success(['complaint' => $complaint], 'Complaint created successfully.');
    }

    public function getComplaint($id)
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['complaintFrom', 'complaintAgainst'])
            ->first();

        if (!$complaint) {
            return $this->error('Complaint not found.', 404);
        }

        // Check if employee has access to this complaint
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($complaint->complaint_from != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        return $this->success(['complaint' => $complaint], 'Complaint detail.');
    }

    public function updateComplaint(Request $request, $id)
    {
        if (Auth::user()->type != 'Employee') {
            $validator = Validator::make($request->all(), [
                'complaint_from' => 'required|exists:employees,id',
                'complaint_against' => 'required|exists:employees,id',
                'title' => 'required|string|max:255',
                'complaint_date' => 'required|date',
                'description' => 'nullable|string',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'complaint_against' => 'required|exists:employees,id',
                'title' => 'required|string|max:255',
                'complaint_date' => 'required|date',
                'description' => 'nullable|string',
            ]);
        }

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $complaint = Complaint::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$complaint) {
            return $this->error('Complaint not found.', 404);
        }

        // Check if employees belong to user's company
        if (Auth::user()->type != 'Employee') {
            $fromEmployee = Employee::where('id', $request->complaint_from)
                ->where('created_by', Auth::user()->creatorId())
                ->first();
            if (!$fromEmployee) {
                return $this->error('Complaint from employee not found or access denied.', 404);
            }
        }

        $againstEmployee = Employee::where('id', $request->complaint_against)
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        if (!$againstEmployee) {
            return $this->error('Complaint against employee not found or access denied.', 404);
        }

        // Prevent same employee selection
        if (Auth::user()->type != 'Employee') {
            if ($request->complaint_from == $request->complaint_against) {
                return $this->error('Complaint from and against cannot be the same employee.', 400);
            }
        } else {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($emp->id == $request->complaint_against) {
                return $this->error('You cannot file a complaint against yourself.', 400);
            }
        }

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $complaint->complaint_from = $emp->id;
        } else {
            $complaint->complaint_from = $request->complaint_from;
        }
        $complaint->complaint_against = $request->complaint_against;
        $complaint->title = $request->title;
        $complaint->complaint_date = $request->complaint_date;
        $complaint->description = $request->description;
        $complaint->save();

        return $this->success(['complaint' => $complaint], 'Complaint updated successfully.');
    }

    public function deleteComplaint($id)
    {
        $complaint = Complaint::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$complaint) {
            return $this->error('Complaint not found.', 404);
        }

        // Check if employee has access to delete this complaint
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            if ($complaint->complaint_from != $emp->id) {
                return $this->error('Permission denied.', 403);
            }
        }

        $complaint->delete();

        return $this->success([], 'Complaint deleted successfully.');
    }

    public function getEmployees()
    {
        if (Auth::user()->type == 'Employee') {
            $user = Auth::user();
            $currentEmployee = Employee::where('user_id', $user->id)->get()->pluck('name', 'id');
            $employees = Employee::where('created_by', Auth::user()->creatorId())
                ->where('user_id', '!=', $user->id)
                ->get()
                ->pluck('name', 'id');
        } else {
            $user = Auth::user();
            $currentEmployee = Employee::where('user_id', $user->id)->get()->pluck('name', 'id');
            $employees = Employee::where('created_by', Auth::user()->creatorId())
                ->get()
                ->pluck('name', 'id');
        }

        return $this->success([
            'employees' => $employees,
            'current_employee' => $currentEmployee
        ], 'Employees retrieved.');
    }

    public function getComplaintStats()
    {
        $query = Complaint::where('created_by', Auth::user()->creatorId());

        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $query->where('complaint_from', '=', $emp->id);
        }

        $totalComplaints = $query->count();
        $recentComplaints = $query->where('complaint_date', '>=', date('Y-m-d', strtotime('-30 days')))->count();

        // Group by status if status field exists
        $complaintsByStatus = $query->get()->groupBy('status')->map(function ($group) {
            return $group->count();
        });

        return $this->success([
            'total_complaints' => $totalComplaints,
            'recent_complaints' => $recentComplaints,
            'complaints_by_status' => $complaintsByStatus
        ], 'Complaint statistics retrieved.');
    }

    public function getMyComplaints()
    {
        if (Auth::user()->type != 'Employee') {
            return $this->error('This endpoint is for employees only.', 403);
        }

        $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
        $complaints = Complaint::where('complaint_from', '=', $emp->id)
            ->with(['complaintAgainst'])
            ->orderBy('complaint_date', 'desc')
            ->get();

        return $this->success(['complaints' => $complaints], 'My complaints retrieved.');
    }

    public function getComplaintsAgainstMe()
    {
        $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
        $complaints = Complaint::where('complaint_against', '=', $emp->id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['complaintFrom'])
            ->orderBy('complaint_date', 'desc')
            ->get();

        return $this->success(['complaints' => $complaints], 'Complaints against me retrieved.');
    }
}

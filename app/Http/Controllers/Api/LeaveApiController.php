<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Employee;
use App\Models\LeaveAttachment;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LeaveApiController extends Controller
{
    use ApiResponser;

    private function isAdmin($user): bool
    {
        return in_array($user->type, ['company', 'HR', 'super admin']);
    }

    public function getLeaves()
    {
        $user = Auth::user();
        $query = Leave::with(['leaveType', 'employee', 'attachments']);

        if ($this->isAdmin($user)) {
            $query->where('created_by', $user->creatorId());
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                return $this->success(['leaves' => []], 'No employee profile found.');
            }
        }

        $leaves = $query->get();
        return $this->success(['leaves' => $leaves], 'Leaves retrieved.');
    }

    public function createLeave(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'leave_reason' => 'required',
                'remark' => 'nullable',
                'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $user = Auth::user();
        $employee_id = null;

        if ($this->isAdmin($user)) {
            if (!$request->user_id) {
                return $this->error('user_id is required for Company/HR/Admin.', 422);
            }
            $employee = Employee::where('user_id', $request->user_id)->first();
            if (!$employee) {
                return $this->error('No employee profile found for the given user_id.', 404);
            }
            $employee_id = $employee->id;
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee) {
                return $this->error('Employee record not found. Please contact administrator to create your employee profile.', 404);
            }
            $employee_id = $employee->id;
        }

        $leave_type = LeaveType::find($request->leave_type_id);
        $startDate = new \DateTime($request->start_date);
        $endDate = new \DateTime($request->end_date);
        $endDate->add(new \DateInterval('P1D'));
        $total_leave_days = !empty($startDate->diff($endDate)) ? $startDate->diff($endDate)->days : 0;

        if ($leave_type->days >= $total_leave_days) {
            $leave = new Leave();
            $leave->employee_id = $employee_id;
            $leave->leave_type_id = $request->leave_type_id;
            $leave->applied_on = date('Y-m-d');
            $leave->start_date = $request->start_date;
            $leave->end_date = $request->end_date;
            $leave->total_leave_days = $total_leave_days;
            $leave->leave_reason = $request->leave_reason;
            $leave->remark = $request->remark;
            $leave->status = 'Pending';
            $leave->created_by = $user->creatorId();
            $leave->save();

            // Handle attachment upload
            if ($request->hasFile('attachment')) {
                $file          = $request->file('attachment');
                $uploadDir     = base_path('uploads/leave_attachments/' . $leave->id);
                $fileName      = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $filePath      = 'uploads/leave_attachments/' . $leave->id . '/' . $fileName;
                $fileSize      = $file->getSize();
                $originalName  = $file->getClientOriginalName();
                $mimeType      = $file->getClientMimeType();

                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $file->move($uploadDir, $fileName);

                $attachment = new LeaveAttachment();
                $attachment->leave_id      = $leave->id;
                $attachment->file_name     = $fileName;
                $attachment->original_name = $originalName;
                $attachment->file_type     = $mimeType;
                $attachment->file_size     = $fileSize;
                $attachment->file_path     = $filePath;
                $attachment->uploaded_by   = $user->id;
                $attachment->save();
            }

            $leave->load(['leaveType', 'employee', 'attachments']);
            return $this->success(['leave' => $leave], 'Leave created successfully.');
        } else {
            return $this->error('Leave type days exceeded.', 400);
        }
    }

    public function getLeave($id)
    {
        $leave = Leave::with(['leaveType', 'employee', 'attachments'])->find($id);
        if (!$leave) return $this->error('Leave not found', 404);

        // Check access
        $user = Auth::user();
        if (!$this->isAdmin($user)) {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee || $leave->employee_id != $employee->id) {
                return $this->error('Permission denied.', 403);
            }
        } else {
            if ($leave->created_by != $user->creatorId()) {
                return $this->error('Permission denied.', 403);
            }
        }

        return $this->success(['leave' => $leave], 'Leave detail.');
    }

    public function updateLeave(Request $request, $id)
    {
        $leave = Leave::find($id);
        if (!$leave) return $this->error('Leave not found', 404);

        $user = Auth::user();

        if ($this->isAdmin($user)) {
            if ($leave->created_by != $user->creatorId()) {
                return $this->error('Permission denied.', 403);
            }
        } else {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee || $leave->employee_id != $employee->id) {
                return $this->error('Permission denied.', 403);
            }
            if ($leave->status != 'Pending') {
                return $this->error('Cannot edit non-pending leave.', 400);
            }
        }

        $validator = Validator::make(
            $request->all(),
            [
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date'    => 'required|date',
                'end_date'      => 'required|date|after_or_equal:start_date',
                'leave_reason'  => 'required',
                'remark'        => 'nullable',
                'attachment'    => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $leave_type = LeaveType::find($request->leave_type_id);
        $startDate  = new \DateTime($request->start_date);
        $endDate    = new \DateTime($request->end_date);
        $endDate->add(new \DateInterval('P1D'));
        $total_leave_days = $startDate->diff($endDate)->days;

        if ($leave_type->days < $total_leave_days) {
            return $this->error('Leave type days exceeded.', 400);
        }

        $leave->leave_type_id    = $request->leave_type_id;
        $leave->start_date       = $request->start_date;
        $leave->end_date         = $request->end_date;
        $leave->total_leave_days = $total_leave_days;
        $leave->leave_reason     = $request->leave_reason;
        $leave->remark           = $request->remark;
        $leave->save();

        if ($request->hasFile('attachment')) {
            $file         = $request->file('attachment');
            $uploadDir    = base_path('uploads/leave_attachments/' . $leave->id);
            $fileName     = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $filePath     = 'uploads/leave_attachments/' . $leave->id . '/' . $fileName;
            $fileSize     = $file->getSize();
            $originalName = $file->getClientOriginalName();
            $mimeType     = $file->getClientMimeType();

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $file->move($uploadDir, $fileName);

            $attachment = new LeaveAttachment();
            $attachment->leave_id      = $leave->id;
            $attachment->file_name     = $fileName;
            $attachment->original_name = $originalName;
            $attachment->file_type     = $mimeType;
            $attachment->file_size     = $fileSize;
            $attachment->file_path     = $filePath;
            $attachment->uploaded_by   = $user->id;
            $attachment->save();
        }

        $leave->load(['leaveType', 'employee', 'attachments']);
        return $this->success(['leave' => $leave], 'Leave updated successfully.');
    }

    public function deleteLeave($id)
    {
        $leave = Leave::find($id);
        if (!$leave) return $this->error('Leave not found', 404);

        // Similar access check
        $user = Auth::user();
        if (!$this->isAdmin($user)) {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee || $leave->employee_id != $employee->id) {
                return $this->error('Permission denied.', 403);
            }
            if ($leave->status != 'Pending') {
                return $this->error('Cannot delete non-pending leave.', 400);
            }
        } else {
            if ($leave->created_by != $user->creatorId()) {
                return $this->error('Permission denied.', 403);
            }
        }

        $leave->delete();
        return $this->success([], 'Leave deleted successfully.');
    }

    public function approveLeave($id)
    {
        $leave = Leave::find($id);
        if (!$leave) return $this->error('Leave not found', 404);

        if (!$this->isAdmin(Auth::user())) {
            return $this->error('Permission denied.', 403);
        }

        $leave->status = 'Approved';
        $leave->save();
        $leave->load(['leaveType', 'employee', 'attachments']);

        return $this->success(['leave' => $leave], 'Leave approved successfully.');
    }

    public function rejectLeave($id)
    {
        $leave = Leave::find($id);
        if (!$leave) return $this->error('Leave not found', 404);

        if (!$this->isAdmin(Auth::user())) {
            return $this->error('Permission denied.', 403);
        }

        $leave->status = 'Reject';
        $leave->save();
        $leave->load(['leaveType', 'employee', 'attachments']);

        return $this->success(['leave' => $leave], 'Leave rejected successfully.');
    }

    public function getLeaveTypes()
    {
        $types = LeaveType::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['leave_types' => $types], 'Leave types retrieved.');
    }

    public function createLeaveType(Request $request)
    {
        if (!$this->isAdmin(Auth::user())) {
            return $this->error('Permission denied.', 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'title' => 'required',
                'days' => 'required|numeric',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $leaveType = new LeaveType();
        $leaveType->title = $request->title;
        $leaveType->days = $request->days;
        $leaveType->created_by = Auth::user()->creatorId();
        $leaveType->save();

        return $this->success(['leave_type' => $leaveType], 'Leave type created successfully.');
    }
}

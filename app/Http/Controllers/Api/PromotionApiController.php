<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Promotion;
use App\Models\Employee;
use App\Models\Designation;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PromotionApiController extends Controller
{
    use ApiResponser;

    public function getPromotions()
    {
        if (Auth::user()->type == 'Employee') {
            $emp = Employee::where('user_id', '=', Auth::user()->id)->first();
            $promotions = Promotion::where('created_by', '=', Auth::user()->creatorId())
                ->where('employee_id', '=', $emp->id)
                ->with(['designation', 'employee'])
                ->get();
        } else {
            $promotions = Promotion::where('created_by', '=', Auth::user()->creatorId())
                ->with(['designation', 'employee'])
                ->get();
        }

        return $this->success(['promotions' => $promotions], 'Promotions retrieved.');
    }

    public function createPromotion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'designation_id' => 'required|exists:designations,id',
            'promotion_title' => 'required|string|max:255',
            'promotion_date' => 'required|date',
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

        // Check if designation belongs to user's company
        $designation = Designation::where('id', $request->designation_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$designation) {
            return $this->error('Designation not found or access denied.', 404);
        }

        $promotion = new Promotion();
        $promotion->employee_id = $request->employee_id;
        $promotion->designation_id = $request->designation_id;
        $promotion->promotion_title = $request->promotion_title;
        $promotion->promotion_date = $request->promotion_date;
        $promotion->description = $request->description;
        $promotion->created_by = Auth::user()->creatorId();
        $promotion->save();

        // Send Email
        $settings = Utility::settings();
        if ($settings['promotion_sent'] == 1) {
            $promotionArr = [
                'employee_name' => $employee->name,
                'promotion_designation' => $designation->name,
                'promotion_title' => $promotion->promotion_title,
                'promotion_date' => $promotion->promotion_date,
            ];

            $resp = Utility::sendEmailTemplate('promotion_sent', [$employee->email], $promotionArr);
        }

        return $this->success(['promotion' => $promotion], 'Promotion created successfully.');
    }

    public function getPromotion($id)
    {
        $promotion = Promotion::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['designation', 'employee'])
            ->first();

        if (!$promotion) {
            return $this->error('Promotion not found.', 404);
        }

        return $this->success(['promotion' => $promotion], 'Promotion detail.');
    }

    public function updatePromotion(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'designation_id' => 'required|exists:designations,id',
            'promotion_title' => 'required|string|max:255',
            'promotion_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $promotion = Promotion::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$promotion) {
            return $this->error('Promotion not found.', 404);
        }

        // Check if employee belongs to user's company
        $employee = Employee::where('id', $request->employee_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found or access denied.', 404);
        }

        // Check if designation belongs to user's company
        $designation = Designation::where('id', $request->designation_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$designation) {
            return $this->error('Designation not found or access denied.', 404);
        }

        $promotion->employee_id = $request->employee_id;
        $promotion->designation_id = $request->designation_id;
        $promotion->promotion_title = $request->promotion_title;
        $promotion->promotion_date = $request->promotion_date;
        $promotion->description = $request->description;
        $promotion->save();

        return $this->success(['promotion' => $promotion], 'Promotion updated successfully.');
    }

    public function deletePromotion($id)
    {
        $promotion = Promotion::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$promotion) {
            return $this->error('Promotion not found.', 404);
        }

        $promotion->delete();

        return $this->success([], 'Promotion deleted successfully.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getDesignations()
    {
        $designations = Designation::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['designations' => $designations], 'Designations retrieved.');
    }
}

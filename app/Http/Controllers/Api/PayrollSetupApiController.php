<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AllowanceOption;
use App\Models\DeductionOption;
use App\Models\LoanOption;
use App\Models\PayslipType;
use App\Models\SalaryStructure;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PayrollSetupApiController extends Controller
{
    use ApiResponser;

    // ─── Allowance Options ────────────────────────────────────────────────────

    public function getAllowanceOptions()
    {
        $options = AllowanceOption::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['allowance_options' => $options], 'Allowance options retrieved.');
    }

    public function createAllowanceOption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $option = AllowanceOption::create([
            'name'       => $request->name,
            'created_by' => Auth::user()->creatorId(),
        ]);
        return $this->success(['allowance_option' => $option], 'Allowance option created successfully.');
    }

    public function getAllowanceOption($id)
    {
        $option = AllowanceOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Allowance option not found.', 404);
        return $this->success(['allowance_option' => $option], 'Allowance option retrieved.');
    }

    public function updateAllowanceOption(Request $request, $id)
    {
        $option = AllowanceOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Allowance option not found.', 404);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $option->update(['name' => $request->name]);
        return $this->success(['allowance_option' => $option], 'Allowance option updated successfully.');
    }

    public function deleteAllowanceOption($id)
    {
        $option = AllowanceOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Allowance option not found.', 404);
        $option->delete();
        return $this->success([], 'Allowance option deleted successfully.');
    }

    // ─── Deduction Options ────────────────────────────────────────────────────

    public function getDeductionOptions()
    {
        $options = DeductionOption::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['deduction_options' => $options], 'Deduction options retrieved.');
    }

    public function createDeductionOption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $option = DeductionOption::create([
            'name'       => $request->name,
            'created_by' => Auth::user()->creatorId(),
        ]);
        return $this->success(['deduction_option' => $option], 'Deduction option created successfully.');
    }

    public function getDeductionOption($id)
    {
        $option = DeductionOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Deduction option not found.', 404);
        return $this->success(['deduction_option' => $option], 'Deduction option retrieved.');
    }

    public function updateDeductionOption(Request $request, $id)
    {
        $option = DeductionOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Deduction option not found.', 404);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $option->update(['name' => $request->name]);
        return $this->success(['deduction_option' => $option], 'Deduction option updated successfully.');
    }

    public function deleteDeductionOption($id)
    {
        $option = DeductionOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Deduction option not found.', 404);
        $option->delete();
        return $this->success([], 'Deduction option deleted successfully.');
    }

    // ─── Loan Options ─────────────────────────────────────────────────────────

    public function getLoanOptions()
    {
        $options = LoanOption::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['loan_options' => $options], 'Loan options retrieved.');
    }

    public function createLoanOption(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $option = LoanOption::create([
            'name'       => $request->name,
            'created_by' => Auth::user()->creatorId(),
        ]);
        return $this->success(['loan_option' => $option], 'Loan option created successfully.');
    }

    public function getLoanOption($id)
    {
        $option = LoanOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Loan option not found.', 404);
        return $this->success(['loan_option' => $option], 'Loan option retrieved.');
    }

    public function updateLoanOption(Request $request, $id)
    {
        $option = LoanOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Loan option not found.', 404);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $option->update(['name' => $request->name]);
        return $this->success(['loan_option' => $option], 'Loan option updated successfully.');
    }

    public function deleteLoanOption($id)
    {
        $option = LoanOption::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$option) return $this->error('Loan option not found.', 404);
        $option->delete();
        return $this->success([], 'Loan option deleted successfully.');
    }

    // ─── Payslip Types ────────────────────────────────────────────────────────

    public function getPayslipTypes()
    {
        $types = PayslipType::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['payslip_types' => $types], 'Payslip types retrieved.');
    }

    public function createPayslipType(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $type = PayslipType::create([
            'name'       => $request->name,
            'created_by' => Auth::user()->creatorId(),
        ]);
        return $this->success(['payslip_type' => $type], 'Payslip type created successfully.');
    }

    public function getPayslipType($id)
    {
        $type = PayslipType::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$type) return $this->error('Payslip type not found.', 404);
        return $this->success(['payslip_type' => $type], 'Payslip type retrieved.');
    }

    public function updatePayslipType(Request $request, $id)
    {
        $type = PayslipType::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$type) return $this->error('Payslip type not found.', 404);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $type->update(['name' => $request->name]);
        return $this->success(['payslip_type' => $type], 'Payslip type updated successfully.');
    }

    public function deletePayslipType($id)
    {
        $type = PayslipType::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$type) return $this->error('Payslip type not found.', 404);
        $type->delete();
        return $this->success([], 'Payslip type deleted successfully.');
    }

    // ─── Salary Structures ────────────────────────────────────────────────────

    public function getSalaryStructures()
    {
        $structures = SalaryStructure::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['salary_structures' => $structures], 'Salary structures retrieved.');
    }

    public function createSalaryStructure(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'basic_salary' => 'required|numeric|min:0',
            'allowances'   => 'nullable|array',
            'deductions'   => 'nullable|array',
            'description'  => 'nullable|string',
            'status'       => 'nullable|in:active,inactive',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $structure = SalaryStructure::create([
            'name'         => $request->name,
            'description'  => $request->description,
            'basic_salary' => $request->basic_salary,
            'allowances'   => $request->allowances ?? [],
            'deductions'   => $request->deductions ?? [],
            'status'       => $request->status ?? 'active',
            'created_by'   => Auth::user()->creatorId(),
        ]);
        return $this->success(['salary_structure' => $structure], 'Salary structure created successfully.');
    }

    public function getSalaryStructure($id)
    {
        $structure = SalaryStructure::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$structure) return $this->error('Salary structure not found.', 404);
        return $this->success(['salary_structure' => $structure], 'Salary structure retrieved.');
    }

    public function updateSalaryStructure(Request $request, $id)
    {
        $structure = SalaryStructure::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$structure) return $this->error('Salary structure not found.', 404);

        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:255',
            'basic_salary' => 'required|numeric|min:0',
            'allowances'   => 'nullable|array',
            'deductions'   => 'nullable|array',
            'description'  => 'nullable|string',
            'status'       => 'nullable|in:active,inactive',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $structure->update([
            'name'         => $request->name,
            'description'  => $request->description,
            'basic_salary' => $request->basic_salary,
            'allowances'   => $request->allowances ?? $structure->allowances,
            'deductions'   => $request->deductions ?? $structure->deductions,
            'status'       => $request->status ?? $structure->status,
        ]);
        return $this->success(['salary_structure' => $structure], 'Salary structure updated successfully.');
    }

    public function deleteSalaryStructure($id)
    {
        $structure = SalaryStructure::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$structure) return $this->error('Salary structure not found.', 404);
        $structure->delete();
        return $this->success([], 'Salary structure deleted successfully.');
    }
}

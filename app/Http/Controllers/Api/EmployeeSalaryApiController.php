<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allowance;
use App\Models\Commission;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\OtherPayment;
use App\Models\Overtime;
use App\Models\PayslipType;
use App\Models\SaturationDeduction;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmployeeSalaryApiController extends Controller
{
    use ApiResponser;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function findEmployee($id)
    {
        return Employee::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();
    }

    // ─── Employee Salary List & Detail ────────────────────────────────────────

    public function index()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())
            ->with('salaryType')
            ->get()
            ->map(fn($e) => [
                'id'           => $e->id,
                'employee_id'  => $e->employee_id,
                'name'         => $e->name,
                'salary'       => $e->salary,
                'salary_type'  => $e->salaryType ? $e->salaryType->name : null,
                'net_salary'   => $e->get_net_salary(),
            ]);

        return $this->success(['employees' => $employees], 'Employee salary list retrieved.');
    }

    public function show($id)
    {
        $employee = $this->findEmployee($id);
        if (!$employee) return $this->error('Employee not found.', 404);

        $employee->load('salaryType');

        return $this->success([
            'employee' => [
                'id'           => $employee->id,
                'employee_id'  => $employee->employee_id,
                'name'         => $employee->name,
                'salary'       => $employee->salary,
                'salary_type'  => $employee->salaryType ? ['id' => $employee->salaryType->id, 'name' => $employee->salaryType->name] : null,
                'account'      => $employee->account,
                'net_salary'   => $employee->get_net_salary(),
            ],
            'allowances'   => $employee->allowances,
            'commissions'  => $employee->commissions,
            'loans'        => $employee->loans,
            'deductions'   => $employee->saturationDeductions,
            'other_payments' => $employee->otherPayments,
            'overtimes'    => $employee->overtimes,
        ], 'Employee salary detail retrieved.');
    }

    public function updateBasicSalary(Request $request, $id)
    {
        $employee = $this->findEmployee($id);
        if (!$employee) return $this->error('Employee not found.', 404);

        $validator = Validator::make($request->all(), [
            'salary'      => 'required|numeric|min:0',
            'salary_type' => 'required|exists:payslip_types,id',
            'account'     => 'nullable|exists:bank_accounts,id',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $employee->salary      = $request->salary;
        $employee->salary_type = $request->salary_type;
        if ($request->has('account')) {
            $employee->account = $request->account;
        }
        $employee->save();

        return $this->success(['employee' => $employee], 'Basic salary updated successfully.');
    }

    // ─── Allowances ───────────────────────────────────────────────────────────

    public function getAllowances($id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $allowances = Allowance::where('employee_id', $id)->get();
        return $this->success(['allowances' => $allowances], 'Allowances retrieved.');
    }

    public function createAllowance(Request $request, $id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:255',
            'allowance_option' => 'required|exists:allowance_options,id',
            'type'             => 'required|in:fixed,percentage',
            'amount'           => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $allowance = Allowance::create([
            'employee_id'      => $id,
            'allowance_option' => $request->allowance_option,
            'title'            => $request->title,
            'type'             => $request->type,
            'amount'           => $request->amount,
            'created_by'       => Auth::user()->creatorId(),
        ]);
        return $this->success(['allowance' => $allowance], 'Allowance created successfully.');
    }

    public function updateAllowance(Request $request, $id, $allowanceId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $allowance = Allowance::where('id', $allowanceId)->where('employee_id', $id)->first();
        if (!$allowance) return $this->error('Allowance not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'  => 'required|string|max:255',
            'type'   => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $allowance->update($request->only('title', 'type', 'amount', 'allowance_option'));
        return $this->success(['allowance' => $allowance], 'Allowance updated successfully.');
    }

    public function deleteAllowance($id, $allowanceId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $allowance = Allowance::where('id', $allowanceId)->where('employee_id', $id)->first();
        if (!$allowance) return $this->error('Allowance not found.', 404);
        $allowance->delete();
        return $this->success([], 'Allowance deleted successfully.');
    }

    // ─── Commissions ──────────────────────────────────────────────────────────

    public function getCommissions($id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $commissions = Commission::where('employee_id', $id)->get();
        return $this->success(['commissions' => $commissions], 'Commissions retrieved.');
    }

    public function createCommission(Request $request, $id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'  => 'required|string|max:255',
            'type'   => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $commission = Commission::create([
            'employee_id' => $id,
            'title'       => $request->title,
            'type'        => $request->type,
            'amount'      => $request->amount,
            'created_by'  => Auth::user()->creatorId(),
        ]);
        return $this->success(['commission' => $commission], 'Commission created successfully.');
    }

    public function updateCommission(Request $request, $id, $commissionId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $commission = Commission::where('id', $commissionId)->where('employee_id', $id)->first();
        if (!$commission) return $this->error('Commission not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'  => 'required|string|max:255',
            'type'   => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $commission->update($request->only('title', 'type', 'amount'));
        return $this->success(['commission' => $commission], 'Commission updated successfully.');
    }

    public function deleteCommission($id, $commissionId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $commission = Commission::where('id', $commissionId)->where('employee_id', $id)->first();
        if (!$commission) return $this->error('Commission not found.', 404);
        $commission->delete();
        return $this->success([], 'Commission deleted successfully.');
    }

    // ─── Loans ────────────────────────────────────────────────────────────────

    public function getLoans($id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $loans = Loan::where('employee_id', $id)->get();
        return $this->success(['loans' => $loans], 'Loans retrieved.');
    }

    public function createLoan(Request $request, $id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'loan_option' => 'required|exists:loan_options,id',
            'type'        => 'required|in:fixed,percentage',
            'amount'      => 'required|numeric|min:0',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'reason'      => 'nullable|string',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $loan = Loan::create([
            'employee_id' => $id,
            'loan_option' => $request->loan_option,
            'title'       => $request->title,
            'type'        => $request->type,
            'amount'      => $request->amount,
            'start_date'  => $request->start_date,
            'end_date'    => $request->end_date,
            'reason'      => $request->reason,
            'created_by'  => Auth::user()->creatorId(),
        ]);
        return $this->success(['loan' => $loan], 'Loan created successfully.');
    }

    public function updateLoan(Request $request, $id, $loanId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $loan = Loan::where('id', $loanId)->where('employee_id', $id)->first();
        if (!$loan) return $this->error('Loan not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|max:255',
            'type'       => 'required|in:fixed,percentage',
            'amount'     => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $loan->update($request->only('title', 'type', 'amount', 'loan_option', 'start_date', 'end_date', 'reason'));
        return $this->success(['loan' => $loan], 'Loan updated successfully.');
    }

    public function deleteLoan($id, $loanId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $loan = Loan::where('id', $loanId)->where('employee_id', $id)->first();
        if (!$loan) return $this->error('Loan not found.', 404);
        $loan->delete();
        return $this->success([], 'Loan deleted successfully.');
    }

    // ─── Deductions ───────────────────────────────────────────────────────────

    public function getDeductions($id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $deductions = SaturationDeduction::where('employee_id', $id)->get();
        return $this->success(['deductions' => $deductions], 'Deductions retrieved.');
    }

    public function createDeduction(Request $request, $id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:255',
            'deduction_option' => 'required|exists:deduction_options,id',
            'type'             => 'required|in:fixed,percentage',
            'amount'           => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $deduction = SaturationDeduction::create([
            'employee_id'      => $id,
            'deduction_option' => $request->deduction_option,
            'title'            => $request->title,
            'type'             => $request->type,
            'amount'           => $request->amount,
            'created_by'       => Auth::user()->creatorId(),
        ]);
        return $this->success(['deduction' => $deduction], 'Deduction created successfully.');
    }

    public function updateDeduction(Request $request, $id, $deductionId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $deduction = SaturationDeduction::where('id', $deductionId)->where('employee_id', $id)->first();
        if (!$deduction) return $this->error('Deduction not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'  => 'required|string|max:255',
            'type'   => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $deduction->update($request->only('title', 'type', 'amount', 'deduction_option'));
        return $this->success(['deduction' => $deduction], 'Deduction updated successfully.');
    }

    public function deleteDeduction($id, $deductionId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $deduction = SaturationDeduction::where('id', $deductionId)->where('employee_id', $id)->first();
        if (!$deduction) return $this->error('Deduction not found.', 404);
        $deduction->delete();
        return $this->success([], 'Deduction deleted successfully.');
    }

    // ─── Other Payments ───────────────────────────────────────────────────────

    public function getOtherPayments($id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $payments = OtherPayment::where('employee_id', $id)->get();
        return $this->success(['other_payments' => $payments], 'Other payments retrieved.');
    }

    public function createOtherPayment(Request $request, $id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'  => 'required|string|max:255',
            'type'   => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $payment = OtherPayment::create([
            'employee_id' => $id,
            'title'       => $request->title,
            'type'        => $request->type,
            'amount'      => $request->amount,
            'created_by'  => Auth::user()->creatorId(),
        ]);
        return $this->success(['other_payment' => $payment], 'Other payment created successfully.');
    }

    public function updateOtherPayment(Request $request, $id, $paymentId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $payment = OtherPayment::where('id', $paymentId)->where('employee_id', $id)->first();
        if (!$payment) return $this->error('Other payment not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'  => 'required|string|max:255',
            'type'   => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $payment->update($request->only('title', 'type', 'amount'));
        return $this->success(['other_payment' => $payment], 'Other payment updated successfully.');
    }

    public function deleteOtherPayment($id, $paymentId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $payment = OtherPayment::where('id', $paymentId)->where('employee_id', $id)->first();
        if (!$payment) return $this->error('Other payment not found.', 404);
        $payment->delete();
        return $this->success([], 'Other payment deleted successfully.');
    }

    // ─── Overtime ─────────────────────────────────────────────────────────────

    public function getOvertimes($id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $overtimes = Overtime::where('employee_id', $id)->get();
        return $this->success(['overtimes' => $overtimes], 'Overtime records retrieved.');
    }

    public function createOvertime(Request $request, $id)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'          => 'required|string|max:255',
            'number_of_days' => 'required|integer|min:1',
            'hours'          => 'required|numeric|min:0',
            'rate'           => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $overtime = Overtime::create([
            'employee_id'    => $id,
            'title'          => $request->title,
            'number_of_days' => $request->number_of_days,
            'hours'          => $request->hours,
            'rate'           => $request->rate,
            'created_by'     => Auth::user()->creatorId(),
        ]);
        return $this->success(['overtime' => $overtime], 'Overtime created successfully.');
    }

    public function updateOvertime(Request $request, $id, $overtimeId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $overtime = Overtime::where('id', $overtimeId)->where('employee_id', $id)->first();
        if (!$overtime) return $this->error('Overtime not found.', 404);

        $validator = Validator::make($request->all(), [
            'title'          => 'required|string|max:255',
            'number_of_days' => 'required|integer|min:1',
            'hours'          => 'required|numeric|min:0',
            'rate'           => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $overtime->update($request->only('title', 'number_of_days', 'hours', 'rate'));
        return $this->success(['overtime' => $overtime], 'Overtime updated successfully.');
    }

    public function deleteOvertime($id, $overtimeId)
    {
        if (!$this->findEmployee($id)) return $this->error('Employee not found.', 404);
        $overtime = Overtime::where('id', $overtimeId)->where('employee_id', $id)->first();
        if (!$overtime) return $this->error('Overtime not found.', 404);
        $overtime->delete();
        return $this->success([], 'Overtime deleted successfully.');
    }
}

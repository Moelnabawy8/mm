<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaySlip;
use App\Models\Employee;
use App\Models\Allowance;
use App\Models\Commission;
use App\Models\Loan;
use App\Models\SaturationDeduction;
use App\Models\OtherPayment;
use App\Models\Overtime;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class PayrollApiController extends Controller
{
    use ApiResponser;

    public function getPayslips(Request $request)
    {
        $query = PaySlip::where('created_by', Auth::user()->creatorId())
            ->with(['employees:id,employee_id,name,salary_type']);

        if ($request->month && $request->year) {
            $query->where('salary_month', $request->year . '-' . $request->month);
        } elseif ($request->salary_month) {
            $query->where('salary_month', $request->salary_month);
        }
        if ($request->status !== null && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $payslips = $query->get();
        return $this->success(['payslips' => $payslips], 'Payslips retrieved.');
    }

    public function paySalary($id)
    {
        $payslip = PaySlip::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$payslip) return $this->error('Payslip not found.', 404);
        if ($payslip->status == 1) return $this->error('Payslip already paid.', 400);

        $payslip->status = 1;
        $payslip->save();

        return $this->success(['payslip' => $payslip], 'Payslip marked as paid successfully.');
    }

    public function bulkPay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'salary_month' => 'required|string',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $count = PaySlip::where('created_by', Auth::user()->creatorId())
            ->where('salary_month', $request->salary_month)
            ->where('status', 0)
            ->update(['status' => 1]);

        return $this->success(['paid_count' => $count], "Bulk payment done. {$count} payslips marked as paid.");
    }

    public function createPayslip(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'month' => 'required',
                'year' => 'required',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $month = $request->month;
        $year  = $request->year;
        $formate_month_year = $year . '-' . $month;

        $validatePaysilp    = PaySlip::where('salary_month', '=', $formate_month_year)->where('created_by', Auth::user()->creatorId())->pluck('employee_id');
        $payslip_employee   = Employee::where('created_by', Auth::user()->creatorId())->where('company_doj', '<=', date($year . '-' . $month . '-t'))->count();

        if ($payslip_employee > count($validatePaysilp)) {
            $employees = Employee::where('created_by', Auth::user()->creatorId())->where('company_doj', '<=', date($year . '-' . $month . '-t'))->whereNotIn('employee_id', $validatePaysilp)->get();

            $created_count = 0;
            foreach ($employees as $employee) {
                $chek = PaySlip::where(['employee_id' => $employee->id, 'salary_month' => $formate_month_year])->first();

                if (!$chek) {
                    $payslipEmployee                       = new PaySlip();
                    $payslipEmployee->employee_id          = $employee->id;
                    $payslipEmployee->net_payble           = $employee->get_net_salary();
                    $payslipEmployee->salary_month         = $formate_month_year;
                    $payslipEmployee->status               = 0;
                    $payslipEmployee->basic_salary         = !empty($employee->salary) ? $employee->salary : 0;
                    $payslipEmployee->allowance            = Employee::allowance($employee->id);
                    $payslipEmployee->commission           = Employee::commission($employee->id);
                    $payslipEmployee->loan                 = Employee::loan($employee->id);
                    $payslipEmployee->saturation_deduction = Employee::saturation_deduction($employee->id);
                    $payslipEmployee->other_payment        = Employee::other_payment($employee->id);
                    $payslipEmployee->overtime             = Employee::overtime($employee->id);
                    $payslipEmployee->created_by           = Auth::user()->creatorId();
                    $payslipEmployee->save();
                    $created_count++;
                }
            }
            return $this->success(['count' => $created_count], 'Payslips created successfully.');
        } else {
            return $this->error('Payslips already created for this month.', 400);
        }
    }

    public function getPayslip($id)
    {
        $payslip = PaySlip::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$payslip) {
            return $this->error('Payslip not found.', 404);
        }
        $payslip->employee = Employee::find($payslip->employee_id);
        return $this->success(['payslip' => $payslip], 'Payslip details.');
    }

    public function updatePayslip(Request $request, $id)
    {
        $payslip = PaySlip::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$payslip) {
            return $this->error('Payslip not found.', 404);
        }

        if (isset($request->allowance) && !empty($request->allowance)) {
            $allowances   = $request->allowance;
            $allowanceIds = $request->allowance_id;
            foreach ($allowances as $k => $allownace) {
                $allowanceData         = Allowance::find($allowanceIds[$k]);
                if ($allowanceData) {
                    $allowanceData->amount = $allownace;
                    $allowanceData->save();
                }
            }
        }

        if (isset($request->commission) && !empty($request->commission)) {
            $commissions   = $request->commission;
            $commissionIds = $request->commission_id;
            foreach ($commissions as $k => $commission) {
                $commissionData         = Commission::find($commissionIds[$k]);
                if ($commissionData) {
                    $commissionData->amount = $commission;
                    $commissionData->save();
                }
            }
        }

        if (isset($request->loan) && !empty($request->loan)) {
            $loans   = $request->loan;
            $loanIds = $request->loan_id;
            foreach ($loans as $k => $loan) {
                $loanData         = Loan::find($loanIds[$k]);
                if ($loanData) {
                    $loanData->amount = $loan;
                    $loanData->save();
                }
            }
        }

        if (isset($request->saturation_deductions) && !empty($request->saturation_deductions)) {
            $saturation_deductionss   = $request->saturation_deductions;
            $saturation_deductionsIds = $request->saturation_deductions_id;
            foreach ($saturation_deductionss as $k => $saturation_deductions) {
                $saturation_deductionsData         = SaturationDeduction::find($saturation_deductionsIds[$k]);
                if ($saturation_deductionsData) {
                    $saturation_deductionsData->amount = $saturation_deductions;
                    $saturation_deductionsData->save();
                }
            }
        }

        if (isset($request->other_payment) && !empty($request->other_payment)) {
            $other_payments   = $request->other_payment;
            $other_paymentIds = $request->other_payment_id;
            foreach ($other_payments as $k => $other_payment) {
                $other_paymentData         = OtherPayment::find($other_paymentIds[$k]);
                if ($other_paymentData) {
                    $other_paymentData->amount = $other_payment;
                    $other_paymentData->save();
                }
            }
        }

        if (isset($request->rate) && !empty($request->rate)) {
            $rates   = $request->rate;
            $rateIds = $request->rate_id;
            $hourses = $request->hours;

            foreach ($rates as $k => $rate) {
                $overtime        = Overtime::find($rateIds[$k]);
                if ($overtime) {
                    $overtime->rate  = $rate;
                    $overtime->hours = $hourses[$k];
                    $overtime->save();
                }
            }
        }

        $payslip->allowance            = Employee::allowance($payslip->employee_id);
        $payslip->commission           = Employee::commission($payslip->employee_id);
        $payslip->loan                 = Employee::loan($payslip->employee_id);
        $payslip->saturation_deduction = Employee::saturation_deduction($payslip->employee_id);
        $payslip->other_payment        = Employee::other_payment($payslip->employee_id);
        $payslip->overtime             = Employee::overtime($payslip->employee_id);
        $payslip->net_payble           = Employee::find($payslip->employee_id)->get_net_salary();
        $payslip->save();

        return $this->success(['payslip' => $payslip], 'Payslip updated successfully.');
    }

    public function deletePayslip($id)
    {
        $payslip = PaySlip::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$payslip) {
            return $this->error('Payslip not found.', 404);
        }
        $payslip->delete();
        return $this->success([], 'Payslip deleted successfully.');
    }

    public function downloadPayslipPdf($id)
    {
        $payslip = PaySlip::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$payslip) {
            return $this->error('Payslip not found.', 404);
        }

        $payslipId = Crypt::encrypt($payslip->id);
        $month = $payslip->salary_month;
        $url = route('payslip.payslipPdf', [$payslipId, $month]);

        return $this->success(['url' => $url], 'Payslip PDF URL retrieved.');
    }

    public function sendPayslip($id)
    {
        $payslip = PaySlip::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$payslip) {
            return $this->error('Payslip not found.', 404);
        }

        $employee = Employee::find($payslip->employee_id);
        $payslip->name  = $employee->name;
        $payslip->email = $employee->email;
        $month = $payslip->salary_month;
        $payslipId    = Crypt::encrypt($payslip->id);
        $payslip->url = route('payslip.payslipPdf', [$payslipId, $month]);

        $payslipArr = [
            'employee_name' => $employee->name,
            'employee_email' => $employee->email,
            'payslip_name' =>   $payslip->name,
            'payslip_salary_month' => $payslip->salary_month,
            'payslip_url' => $payslip->url,
        ];

        try {
            Utility::sendEmailTemplate('payslip_sent', [$employee->id => $employee->email], $payslipArr);
            return $this->success([], 'Payslip sent successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to send payslip email.', 500);
        }
    }

    public function getAllowances()
    {
        $allowances = Allowance::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['allowances' => $allowances], 'Allowances retrieved.');
    }

    public function getDeductions()
    {
        $deductions = SaturationDeduction::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['deductions' => $deductions], 'Deductions retrieved.');
    }

    public function getCommissions()
    {
        $commissions = Commission::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['commissions' => $commissions], 'Commissions retrieved.');
    }

    public function getLoans()
    {
        $loans = Loan::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['loans' => $loans], 'Loans retrieved.');
    }

    public function getOvertime()
    {
        $overtime = Overtime::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['overtime' => $overtime], 'Overtime retrieved.');
    }
}

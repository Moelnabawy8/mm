<?php

namespace App\Http\Controllers;

use App\Models\AllowanceOption;
use App\Models\DeductionOption;
use App\Models\SalaryStructure;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function index()
    {
        $structures = SalaryStructure::where('created_by', \Auth::user()->creatorId())->get();
        return view('salarystructure.index', compact('structures'));
    }

    public function create()
    {
        $allowanceOptions = AllowanceOption::where('created_by', \Auth::user()->creatorId())->get();
        $deductionOptions = DeductionOption::where('created_by', \Auth::user()->creatorId())->get();
        return view('salarystructure.create', compact('allowanceOptions', 'deductionOptions'));
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name'         => 'required|max:100',
            'basic_salary' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $allowances = $this->buildComponents(
            $request->allowance_option ?? [],
            $request->allowance_amount ?? [],
            $request->allowance_is_percentage ?? []
        );

        $deductions = $this->buildComponents(
            $request->deduction_option ?? [],
            $request->deduction_amount ?? [],
            $request->deduction_is_percentage ?? []
        );

        SalaryStructure::create([
            'name'         => $request->name,
            'description'  => $request->description,
            'basic_salary' => $request->basic_salary,
            'allowances'   => $allowances ?: null,
            'deductions'   => $deductions ?: null,
            'status'       => $request->status ?? 'active',
            'created_by'   => \Auth::user()->creatorId(),
        ]);

        return redirect()->route('salary-structure.index')->with('success', __('Salary Structure created successfully.'));
    }

    public function edit(SalaryStructure $salaryStructure)
    {
        if ($salaryStructure->created_by != \Auth::user()->creatorId()) {
            return response()->json(['error' => __('Permission denied.')], 401);
        }

        $allowanceOptions = AllowanceOption::where('created_by', \Auth::user()->creatorId())->get();
        $deductionOptions = DeductionOption::where('created_by', \Auth::user()->creatorId())->get();

        return view('salarystructure.edit', compact('salaryStructure', 'allowanceOptions', 'deductionOptions'));
    }

    public function update(Request $request, SalaryStructure $salaryStructure)
    {
        if ($salaryStructure->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name'         => 'required|max:100',
            'basic_salary' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $allowances = $this->buildComponents(
            $request->allowance_option ?? [],
            $request->allowance_amount ?? [],
            $request->allowance_is_percentage ?? []
        );

        $deductions = $this->buildComponents(
            $request->deduction_option ?? [],
            $request->deduction_amount ?? [],
            $request->deduction_is_percentage ?? []
        );

        $salaryStructure->update([
            'name'         => $request->name,
            'description'  => $request->description,
            'basic_salary' => $request->basic_salary,
            'allowances'   => $allowances ?: null,
            'deductions'   => $deductions ?: null,
            'status'       => $request->status ?? 'active',
        ]);

        return redirect()->route('salary-structure.index')->with('success', __('Salary Structure updated successfully.'));
    }

    public function destroy(SalaryStructure $salaryStructure)
    {
        if ($salaryStructure->created_by != \Auth::user()->creatorId()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $salaryStructure->delete();
        return redirect()->route('salary-structure.index')->with('success', __('Salary Structure deleted successfully.'));
    }

    private function buildComponents(array $options, array $amounts, array $percentageFlags): array
    {
        $result = [];
        foreach ($options as $i => $optionId) {
            if (empty($optionId)) continue;
            $result[] = [
                'option_id'    => (int) $optionId,
                'amount'       => (float) ($amounts[$i] ?? 0),
                'is_percentage'=> isset($percentageFlags[$i]) ? 1 : 0,
            ];
        }
        return $result;
    }
}

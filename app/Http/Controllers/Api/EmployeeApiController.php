<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Branch;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\Utility;
use App\Models\EmployeeDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class EmployeeApiController extends Controller
{
    use ApiResponser;

    /**
     * Save document directly to document root (no symlink needed)
     */
    private function saveDocument($file, $filename)
    {
        // Save to document root - handles both regular and nested public structures
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? public_path();
        $dir = $docRoot . '/documents/';

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $filename);

        return $filename;
    }

    public function index()
    {
        if (Auth::user()->type == 'Employee') {
            $employees = Employee::where('user_id', '=', Auth::user()->id)->with(['designation', 'branch', 'department'])->get();
        } else {
            $employees = Employee::where('created_by', Auth::user()->creatorId())->with(['designation', 'branch', 'department'])->get();
        }

        return $this->success([
            'employees' => $employees->map(function($employee) {
                return [
                    'id' => $employee->user_id,
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->employee_id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'address' => $employee->address,
                    'dob' => $employee->dob,
                    'gender' => $employee->gender,
                    'company_doj' => $employee->company_doj,
                    'salary' => $employee->salary,
                    'department' => $employee->department ? ['name' => $employee->department->name] : null,
                    'designation' => $employee->designation ? ['name' => $employee->designation->name] : null,
                    'branch' => $employee->branch ? ['name' => $employee->branch->name] : null,
                ];
            })
        ], 'Employees retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Personal details
            'name'                 => 'required|string|max:255',
            'email'                => 'required|email|unique:users',
            'password'             => 'required|min:8',
            'phone'                => 'required|string|max:20',
            'dob'                  => 'required|date',
            'gender'               => 'nullable|in:Male,Female',
            'address'              => 'required|string',
            // Company details
            'company_doj'          => 'required|date',
            'branch_id'            => 'required|integer|exists:branches,id',
            'department_id'        => 'required|integer|exists:departments,id',
            'designation_id'       => 'required|integer|exists:designations,id',
            // Bank account details (optional)
            'account_holder_name'  => 'nullable|string|max:255',
            'account_number'       => 'nullable|string|max:50',
            'bank_name'            => 'nullable|string|max:255',
            'bank_identifier_code' => 'nullable|string|max:50',
            'branch_location'      => 'nullable|string|max:255',
            'tax_payer_id'         => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $creatorId = Auth::user()->creatorId();
        $objUser = User::find($creatorId);

        // Simplified creation without plan checks for API v1
        $user = User::create([
            'name' => $request['name'],
            'email' => $request['email'],
            'password' => Hash::make($request['password']),
            'type' => 'employee',
            'lang' => 'en',
            'created_by' => $creatorId,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);
        $user->assignRole('Employee');

        // Handle document uploads (document[{document_id}] files)
        $document_implode = null;
        if (!empty($request->document) && !is_null($request->document)) {
            $document_implode = implode(',', array_keys($request->document));
        }

        $employee = Employee::create([
            'user_id'              => $user->id,
            'name'                 => $request['name'],
            'dob'                  => $request['dob'],
            'gender'               => $request['gender'] ?? null,
            'phone'                => $request['phone'],
            'address'              => $request['address'],
            'email'                => $request['email'],
            'password'             => Hash::make($request['password']),
            'employee_id'          => Utility::employeeNumber($creatorId),
            'branch_id'            => $request['branch_id'],
            'department_id'        => $request['department_id'],
            'designation_id'       => $request['designation_id'],
            'company_doj'          => $request['company_doj'],
            'documents'            => $document_implode,
            // Bank account details
            'account_holder_name'  => $request['account_holder_name'] ?? null,
            'account_number'       => $request['account_number'] ?? null,
            'bank_name'            => $request['bank_name'] ?? null,
            'bank_identifier_code' => $request['bank_identifier_code'] ?? null,
            'branch_location'      => $request['branch_location'] ?? null,
            'tax_payer_id'         => $request['tax_payer_id'] ?? null,
            'created_by'           => $creatorId,
        ]);

        // Save each document file
        if ($request->hasFile('document')) {
            foreach ($request->file('document') as $docId => $file) {
                $filenameWithExt = $file->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $file->getClientOriginalExtension();
                // Use microtime for guaranteed uniqueness even when uploading multiple files per second
                $fileNameToStore = $filename . '_' . microtime(true) . '.' . $extension;

                // Save to both paths (storage + public)
                $this->saveDocument($file, $fileNameToStore);

                EmployeeDocument::create([
                    'employee_id'    => $employee->id,
                    'document_id'    => $docId,
                    'document_value' => $fileNameToStore,
                    'created_by'     => $creatorId,
                ]);
            }
        }

        return $this->success(['employee' => $employee], 'Employee created successfully.');
    }

    public function show($id)
    {
        $user = Auth::user();

        $employee = Employee::with(['designation', 'branch', 'department', 'salaryType', 'user'])
            ->where('user_id', $id)
            ->where('created_by', $user->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found.', 404);
        }

        // Format employee data
        $employeeData = [
            'id' => $employee->user_id,
            'employee_id' => $employee->employee_id,
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'dob' => $employee->dob,
            'gender' => $employee->gender,
            'company_doj' => $employee->company_doj,
            'salary' => $employee->salary,
            'salary_type' => $employee->salaryType ? $employee->salaryType->name : null,
            'department' => $employee->department ? [
                'name' => $employee->department->name,
            ] : null,
            'designation' => $employee->designation ? [
                'name' => $employee->designation->name,
            ] : null,
            'branch' => $employee->branch ? [
                'name' => $employee->branch->name,
            ] : null,
            'avatar' => $employee->user && $employee->user->avatar ? asset('storage/uploads/avatar/' . $employee->user->avatar) : null,
        ];

        return $this->success(['employee' => $employeeData], 'Employee retrieved successfully.');
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found.', 404);
        }

        $updateValidator = Validator::make($request->all(), [
            'name'                 => 'nullable|string|max:255',
            'phone'                => 'nullable|string|max:20',
            'dob'                  => 'nullable|date',
            'gender'               => 'nullable|in:Male,Female',
            'address'              => 'nullable|string',
            'company_doj'          => 'nullable|date',
            'branch_id'            => 'nullable|integer|exists:branches,id',
            'department_id'        => 'nullable|integer|exists:departments,id',
            'designation_id'       => 'nullable|integer|exists:designations,id',
            'account_holder_name'  => 'nullable|string|max:255',
            'account_number'       => 'nullable|string|max:50',
            'bank_name'            => 'nullable|string|max:255',
            'bank_identifier_code' => 'nullable|string|max:50',
            'branch_location'      => 'nullable|string|max:255',
            'tax_payer_id'         => 'nullable|string|max:50',
        ]);

        if ($updateValidator->fails()) {
            return $this->error($updateValidator->errors()->first(), 422);
        }

        $input = $request->only([
            'name', 'phone', 'dob', 'gender', 'address', 'company_doj',
            'branch_id', 'department_id', 'designation_id',
            'account_holder_name', 'account_number', 'bank_name',
            'bank_identifier_code', 'branch_location', 'tax_payer_id',
        ]);
        $employee->fill(array_filter($input, fn($v) => !is_null($v)))->save();

        if ($employee->user_id) {
            $user = User::find($employee->user_id);
            if ($user) {
                $user->name = $employee->name;
                if ($request->email) $user->email = $employee->email;
                $user->save();
            }
        }

        return $this->success(['employee' => $employee], 'Employee updated successfully.');
    }

    public function destroy($id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found.', 404);
        }

        if ($employee->user_id) {
            $user = User::find($employee->user_id);
            if ($user) $user->delete();
        }

        $employee->delete();

        return $this->success([], 'Employee deleted successfully.');
    }


    public function getEmployeeDocuments($id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found.', 404);
        }

        // Search by employee internal ID
        $documents = \App\Models\EmployeeDocument::where('employee_id', $employee->id)->get();

        $documents = $documents->map(function ($doc) use ($id) {
            return [
                'id' => $doc->id,
                'employee_id' => (int)$id,
                'document_id' => $doc->document_id,
                'document_value' => $doc->document_value,
                'document_url' => !empty($doc->document_value) ? url('documents/' . $doc->document_value) : null,
                'created_by' => $doc->created_by,
                'created_at' => $doc->created_at,
                'updated_at' => $doc->updated_at,
            ];
        });

        return $this->success(['documents' => $documents], 'Documents retrieved successfully.');
    }

    public function uploadEmployeeDocument(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'document_id' => 'required',
            'document_file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found.', 404);
        }

        $document = new \App\Models\EmployeeDocument();
        $document->employee_id = $employee->id;
        $document->document_id = $request->document_id;

        if ($request->hasFile('document_file')) {
            $filenameWithExt = $request->file('document_file')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('document_file')->getClientOriginalExtension();
            // Use microtime for guaranteed uniqueness even when uploading multiple files per second
            $fileNameToStore = $filename . '_' . microtime(true) . '.' . $extension;

            // Save to both paths (storage + public)
            $this->saveDocument($request->file('document_file'), $fileNameToStore);

            $document->document_value = $fileNameToStore;
        }

        $document->created_by = Auth::user()->creatorId();
        $document->save();

        return $this->success([
            'document' => [
                'id' => $document->id,
                'employee_id' => (int)$id,
                'document_id' => $document->document_id,
                'document_value' => $document->document_value,
                'document_url' => !empty($document->document_value) ? url('documents/' . $document->document_value) : null,
                'created_by' => $document->created_by,
            ]
        ], 'Document uploaded successfully.');
    }

    public function getEmployeeSalary($id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        if (!$employee) return $this->error('Employee not found.', 404);

        $salaryData = [
            'salary' => $employee->salary,
            'salary_type' => $employee->salary_type, // This uses the relation accessor, might need raw id
            'salary_type_id' => $employee->attributes['salary_type'] ?? null, // Access raw attribute if needed
            'net_salary' => $employee->get_net_salary(),
            'allowances' => $employee->allowances,
            'commissions' => $employee->commissions,
            'loans' => $employee->loans,
            'saturation_deductions' => $employee->saturationDeductions,
            'other_payments' => $employee->otherPayments,
            'overtimes' => $employee->overtimes,
        ];

        return $this->success(['salary_data' => $salaryData], 'Employee salary data retrieved.');
    }

    public function setEmployeeSalary(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'salary_type' => 'required',
            'salary' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 401);
        }

        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        if (!$employee) return $this->error('Employee not found.', 404);

        $employee->salary_type = $request->salary_type;
        $employee->salary = $request->salary;
        $employee->save();

        return $this->success(['employee' => $employee], 'Employee salary updated successfully.');
    }

    public function getEmployeeLeaves($id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        if (!$employee) return $this->error('Employee not found.', 404);

        $leaves = \App\Models\Leave::where('employee_id', $employee->id)->get();
        
        $leaves = $leaves->map(function ($leave) use ($id) {
            return [
                'id' => $leave->id,
                'employee_id' => (int)$id,
                'leave_type_id' => $leave->leave_type_id,
                'applied_on' => $leave->applied_on,
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date,
                'total_leave_days' => $leave->total_leave_days,
                'leave_reason' => $leave->leave_reason,
                'remark' => $leave->remark,
                'status' => $leave->status,
                'created_by' => $leave->created_by,
            ];
        });

        return $this->success(['leaves' => $leaves], 'Employee leaves retrieved.');
    }

    public function getEmployeePayslips($id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        if (!$employee) return $this->error('Employee not found.', 404);

        $payslips = \App\Models\PaySlip::where('employee_id', $employee->id)->get();

        $payslips = $payslips->map(function ($payslip) use ($id) {
            return [
                'id' => $payslip->id,
                'employee_id' => (int)$id,
                'net_payble' => $payslip->net_payble,
                'salary_month' => $payslip->salary_month,
                'status' => $payslip->status,
                'basic_salary' => $payslip->basic_salary,
                'created_by' => $payslip->created_by,
            ];
        });

        return $this->success(['payslips' => $payslips], 'Employee payslips retrieved.');
    }

    public function importEmployees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt',
            'column_mapping' => 'required|array',
            'column_mapping.*' => 'required|string|in:name,dob,gender,phone,address,email,password,company_doj,account_holder_name,account_number,bank_name,bank_identifier_code,branch_location,tax_payer_id,branch_id,department_id,designation_id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if (!$request->hasFile('file')) {
            return $this->error('No file provided.', 400);
        }

        $file = $request->file('file');
        $filePath = $file->getRealPath();
        $file_extension = $file->getClientOriginalExtension();

        if ($file_extension !== 'csv') {
            return $this->error('Only CSV files are supported.', 400);
        }

        try {
            $file_data = [];
            $header = null;

            if (($handle = fopen($filePath, 'r')) !== FALSE) {
                while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    if (!$header) {
                        $header = $row;
                    } else {
                        $file_data[] = $row;
                    }
                }
                fclose($handle);
            }

            $columnMapping = $request->column_mapping;
            $results = [];
            $errors = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($file_data as $key => $row) {
                try {
                    // Check if employee with this email already exists
                    $emailColumn = array_search('email', $columnMapping);
                    if ($emailColumn !== false && !empty($row[$emailColumn])) {
                        $existingEmployee = Employee::where('created_by', Auth::user()->creatorId())
                            ->where('email', 'like', $row[$emailColumn])
                            ->first();

                        if ($existingEmployee) {
                            $errors[] = "Row " . ($key + 2) . ": Employee with email '{$row[$emailColumn]}' already exists.";
                            $errorCount++;
                            continue;
                        }
                    }

                    // Validate required fields
                    $nameColumn = array_search('name', $columnMapping);
                    $emailColumn = array_search('email', $columnMapping);
                    $passwordColumn = array_search('password', $columnMapping);

                    if ($nameColumn === false || empty($row[$nameColumn])) {
                        $errors[] = "Row " . ($key + 2) . ": Name is required.";
                        $errorCount++;
                        continue;
                    }

                    if ($emailColumn === false || empty($row[$emailColumn])) {
                        $errors[] = "Row " . ($key + 2) . ": Email is required.";
                        $errorCount++;
                        continue;
                    }

                    if ($passwordColumn === false || empty($row[$passwordColumn])) {
                        $errors[] = "Row " . ($key + 2) . ": Password is required.";
                        $errorCount++;
                        continue;
                    }

                    // Create user account
                    $user = \App\Models\User::create([
                        'name' => $row[$nameColumn],
                        'email' => $row[$emailColumn],
                        'password' => \Hash::make($row[$passwordColumn]),
                        'email_verified_at' => date('Y-m-d H:i:s'),
                        'type' => 'Employee',
                        'lang' => 'en',
                        'created_by' => Auth::user()->creatorId(),
                    ]);

                    $user->assignRole('Employee');

                    // Prepare employee data
                    $employeeData = [
                        'name' => $row[$nameColumn],
                        'user_id' => $user->id,
                        'email' => $row[$emailColumn],
                        'password' => \Hash::make($row[$passwordColumn]),
                        'employee_id' => Utility::employeeNumber(Auth::user()->creatorId()),
                        'created_by' => Auth::user()->creatorId(),
                    ];

                    // Add optional fields
                    $optionalFields = [
                        'dob' => 'dob',
                        'gender' => 'gender',
                        'phone' => 'phone',
                        'address' => 'address',
                        'company_doj' => 'company_doj',
                        'account_holder_name' => 'account_holder_name',
                        'account_number' => 'account_number',
                        'bank_name' => 'bank_name',
                        'bank_identifier_code' => 'bank_identifier_code',
                        'branch_location' => 'branch_location',
                        'tax_payer_id' => 'tax_payer_id',
                    ];

                    foreach ($optionalFields as $field => $column) {
                        $columnIndex = array_search($column, $columnMapping);
                        if ($columnIndex !== false && !empty($row[$columnIndex])) {
                            $employeeData[$field] = $row[$columnIndex];
                        }
                    }

                    // Handle relationships
                    $branchColumn = array_search('branch_id', $columnMapping);
                    if ($branchColumn !== false && !empty($row[$branchColumn])) {
                        $branch = \App\Models\Branch::find($row[$branchColumn]);
                        if ($branch && $branch->created_by == Auth::user()->creatorId()) {
                            $employeeData['branch_id'] = $branch->id;
                        }
                    }

                    $departmentColumn = array_search('department_id', $columnMapping);
                    if ($departmentColumn !== false && !empty($row[$departmentColumn])) {
                        $department = \App\Models\Department::find($row[$departmentColumn]);
                        if ($department && $department->created_by == Auth::user()->creatorId()) {
                            $employeeData['department_id'] = $department->id;
                        }
                    }

                    $designationColumn = array_search('designation_id', $columnMapping);
                    if ($designationColumn !== false && !empty($row[$designationColumn])) {
                        $designation = \App\Models\Designation::find($row[$designationColumn]);
                        if ($designation && $designation->created_by == Auth::user()->creatorId()) {
                            $employeeData['designation_id'] = $designation->id;
                        }
                    }

                    // Create employee
                    $employee = Employee::create($employeeData);
                    $results[] = [
                        'row' => $key + 2,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'status' => 'success'
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($key + 2) . ": " . $e->getMessage();
                    $errorCount++;
                }
            }

            return $this->success([
                'results' => $results,
                'errors' => $errors,
                'summary' => [
                    'total_rows' => count($file_data),
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ]
            ], 'Employee import completed.');
        } catch (\Exception $e) {
            return $this->error('Error processing file: ' . $e->getMessage(), 500);
        }
    }

    public function exportEmployees()
    {
        try {
            $employees = Employee::where('created_by', Auth::user()->creatorId())
                ->with(['branch', 'department', 'designation'])
                ->get();

            $csvData = [];
            $csvData[] = [
                'Name',
                'Email',
                'Phone',
                'Address',
                'DOB',
                'Gender',
                'Employee ID',
                'Company DOJ',
                'Account Holder Name',
                'Account Number',
                'Bank Name',
                'Bank Identifier Code',
                'Branch Location',
                'Tax Payer ID',
                'Branch',
                'Department',
                'Designation'
            ];

            foreach ($employees as $employee) {
                $csvData[] = [
                    $employee->name,
                    $employee->email,
                    $employee->phone,
                    $employee->address,
                    $employee->dob,
                    $employee->gender,
                    $employee->employee_id,
                    $employee->company_doj,
                    $employee->account_holder_name,
                    $employee->account_number,
                    $employee->bank_name,
                    $employee->bank_identifier_code,
                    $employee->branch_location,
                    $employee->tax_payer_id,
                    $employee->branch ? $employee->branch->name : '',
                    $employee->department ? $employee->department->name : '',
                    $employee->designation ? $employee->designation->name : '',
                ];
            }

            $filename = 'employees_' . date('Y-m-d_H-i-s') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($csvData) {
                $file = fopen('php://output', 'w');
                foreach ($csvData as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return $this->error('Error exporting employees: ' . $e->getMessage(), 500);
        }
    }


    public function getEmployeePermissions($id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found.', 404);
        }

        $user = User::find($employee->user_id);
        if (!$user) {
            return $this->error('User account not found for this employee.', 404);
        }

        $roles = $user->getRoleNames();
        $permissions = $user->getAllPermissions()->pluck('name');

        return $this->success([
            'id' => (int)$id,
            'employee_id' => $employee->employee_id,
            'employee_name' => $employee->name,
            'role' => $roles->first(),
            'roles' => $roles,
            'permissions' => $permissions,
        ], 'Employee permissions retrieved successfully.');
    }

    public function getEmployeeStatistics($id)
    {
        $employee = Employee::where('user_id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$employee) {
            return $this->error('Employee not found.', 404);
        }

        $user = \App\Models\User::find($employee->user_id);
        if (!$user) {
            return $this->error('User account not found for this employee.', 404);
        }

        // Get user projects using the same method as dashboard
        $user_projects = $user->projects()->pluck('project_id')->toArray();

        // Get all tasks in user's projects
        $project_tasks = \App\Models\ProjectTask::whereIn('project_id', $user_projects)->get();

        // Get all expenses in user's projects
        $project_expense = \App\Models\Expense::whereIn('project_id', $user_projects)->get();

        // Total Projects (same as dashboard)
        $complete_project = $user->projects()->where('status', 'LIKE', 'complete')->count();
        $totalProjects = count($user_projects);
        $completedProjectsPercentage = $totalProjects > 0
            ? round(($complete_project / $totalProjects) * 100, 2)
            : 0;

        // Calculate projects status breakdown with percentages
        $projectsStatusBreakdown = [
            'Complete' => '0%',
            'In Progress' => '0%',
            'On Hold' => '0%',
            'Not Started' => '0%',
        ];

        // Get status counts from user projects
        $projects = $user->projects;
        $statusCounts = $projects->groupBy('status')->map->count();

        foreach ($statusCounts as $status => $count) {
            $percentage = $totalProjects > 0
                ? round(($count / $totalProjects) * 100, 2)
                : 0;

            // Map database status to display format
            $displayStatus = ucwords(str_replace('_', ' ', $status));
            if (isset($projectsStatusBreakdown[$displayStatus])) {
                $projectsStatusBreakdown[$displayStatus] = $percentage . '%';
            }
        }

        // Total Tasks (same as dashboard logic)
        if (Auth::user()->type == 'company') {
            $complete_task = \App\Models\ProjectTask::where('is_complete', '=', 1)
                ->whereIn('project_id', $user_projects)
                ->count();
        } else {
            $complete_task = \App\Models\ProjectTask::where('is_complete', '=', 1)
                ->whereRaw("find_in_set('" . $user->id . "',assign_to)")
                ->whereIn('project_id', $user_projects)
                ->count();
        }

        $totalTasks = $project_tasks->count();
        $completedTasksPercentage = $totalTasks > 0
            ? round(($complete_task / $totalTasks) * 100, 2)
            : 0;

        // Total Expense (same as dashboard - count of expenses, not sum)
        $totalExpenses = $project_expense->count();

        return $this->success([
            'id' => (int)$id,
            'total_projects' => $totalProjects,
            'completed_projects_percentage' => $completedProjectsPercentage,
            'projects_status_breakdown' => $projectsStatusBreakdown,
            'total_tasks' => $totalTasks,
            'completed_tasks_percentage' => $completedTasksPercentage,
            'total_expenses' => $totalExpenses,
        ], 'Employee statistics retrieved successfully.');
    }
}

<?php

/**
 * Script to create Employee records for existing Users
 * Run this script to fix "Employee record not found" error
 * 
 * Usage: php create_employee_records.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Employee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;

echo "=================================================\n";
echo "   Create Employee Records Script\n";
echo "=================================================\n\n";

// Get all users without employee records
$usersWithoutEmployees = User::leftJoin('employees', 'users.id', '=', 'employees.user_id')
    ->whereNull('employees.id')
    ->where('users.type', 'employee')
    ->select('users.*')
    ->get();

if ($usersWithoutEmployees->isEmpty()) {
    echo "✅ All employee users already have employee records!\n";
    exit(0);
}

echo "Found " . $usersWithoutEmployees->count() . " users without employee records:\n\n";

// Check if required data exists
$branches = Branch::all();
$departments = Department::all();
$designations = Designation::all();

if ($branches->isEmpty()) {
    echo "⚠️  WARNING: No branches found. Creating default branch...\n";
    $branch = Branch::create([
        'name' => 'Main Branch',
        'created_by' => 1,
    ]);
    echo "✅ Created default branch: {$branch->name} (ID: {$branch->id})\n\n";
} else {
    $branch = $branches->first();
}

if ($departments->isEmpty()) {
    echo "⚠️  WARNING: No departments found. Creating default department...\n";
    $department = Department::create([
        'name' => 'General',
        'branch_id' => $branch->id,
        'created_by' => 1,
    ]);
    echo "✅ Created default department: {$department->name} (ID: {$department->id})\n\n";
} else {
    $department = $departments->first();
}

if ($designations->isEmpty()) {
    echo "⚠️  WARNING: No designations found. Creating default designation...\n";
    $designation = Designation::create([
        'name' => 'Employee',
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'created_by' => 1,
    ]);
    echo "✅ Created default designation: {$designation->name} (ID: {$designation->id})\n\n";
} else {
    $designation = $designations->first();
}

echo "Using:\n";
echo "  - Branch: {$branch->name} (ID: {$branch->id})\n";
echo "  - Department: {$department->name} (ID: {$department->id})\n";
echo "  - Designation: {$designation->name} (ID: {$designation->id})\n\n";

echo "=================================================\n";
echo "Creating employee records...\n";
echo "=================================================\n\n";

$created = 0;
$errors = 0;

// Get the highest employee_id
$maxEmployeeId = Employee::max('employee_id');
$nextEmployeeId = $maxEmployeeId ? (is_numeric($maxEmployeeId) ? $maxEmployeeId + 1 : 1) : 1;

foreach ($usersWithoutEmployees as $user) {
    try {
        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'address' => $user->address ?? '',
            'employee_id' => $nextEmployeeId,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'company_doj' => now()->format('Y-m-d'),
            'created_by' => $user->created_by ?? 1,
        ]);

        echo "✅ Created employee record for: {$user->name} ({$user->email})\n";
        echo "   Employee ID: {$employee->employee_id}\n\n";
        
        $created++;
        $nextEmployeeId++;
    } catch (\Exception $e) {
        echo "❌ Error creating employee for {$user->name}: {$e->getMessage()}\n\n";
        $errors++;
    }
}

echo "=================================================\n";
echo "Summary:\n";
echo "=================================================\n";
echo "✅ Successfully created: {$created} employee records\n";
if ($errors > 0) {
    echo "❌ Errors: {$errors}\n";
}
echo "\n";

// Verify the results
$remainingUsers = User::leftJoin('employees', 'users.id', '=', 'employees.user_id')
    ->whereNull('employees.id')
    ->where('users.type', 'employee')
    ->count();

if ($remainingUsers == 0) {
    echo "🎉 SUCCESS! All employee users now have employee records!\n";
} else {
    echo "⚠️  WARNING: {$remainingUsers} users still without employee records.\n";
}

echo "\n=================================================\n";
echo "Done!\n";
echo "=================================================\n";

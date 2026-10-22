<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Utility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDuplicateEmployeeIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:fix-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identifies and resolves duplicate employee IDs per company';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting to fix duplicate employee IDs...');

        // Group employees by creator and employee_id to find duplicates
        $duplicates = DB::table('employees')
            ->select('created_by', 'employee_id', DB::raw('count(*) as count'))
            ->groupBy('created_by', 'employee_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate employee IDs found.');
            return 0;
        }

        foreach ($duplicates as $duplicate) {
            $this->warn("Found {$duplicate->count} employees with ID '{$duplicate->employee_id}' for Creator ID: {$duplicate->created_by}");

            // Get all employees with this duplicate ID for this creator, except the first one (we keep the oldest)
            $employees = Employee::where('created_by', $duplicate->created_by)
                ->where('employee_id', $duplicate->employee_id)
                ->orderBy('created_at', 'asc')
                ->get();

            // Skip the first one, fix the rest
            for ($i = 1; $i < $employees->count(); $i++) {
                $employee = $employees[$i];
                $oldId = $employee->employee_id;
                
                // Use our new robust Utility function to get the next available ID
                $newId = Utility::employeeNumber($employee->created_by);
                
                $employee->employee_id = $newId;
                $employee->save();
                
                $this->line(" - Updated Employee ID from '{$oldId}' to '{$newId}' for Employee: {$employee->name} (DB ID: {$employee->id})");
            }
        }

        $this->info('Finished fixing duplicates.');
        return 0;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AttendanceEmployee;
use App\Models\Utility;
use Carbon\Carbon;

class AutoCheckOutCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:auto-checkout';

    /**
     * The console command description.
     */
    protected $description = 'Automatically check out employees who forgot to check out on previous days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::now('Africa/Cairo')->format('Y-m-d');

        // Find all unclosed attendance records from days before today
        $unclosedRecords = AttendanceEmployee::where('clock_out', '00:00:00')
            ->where('date', '<', $today)
            ->get();

        if ($unclosedRecords->isEmpty()) {
            $this->info('No unclosed attendance records found.');
            return 0;
        }

        // Get default company end time
        $defaultEndTime = Utility::getValByName('company_end_time') ?? '18:00:00';

        $count = 0;

        foreach ($unclosedRecords as $record) {
            $date = $record->date instanceof Carbon ? $record->date->format('Y-m-d') : $record->date;
            $clockOut = $defaultEndTime;

            // Calculate early leaving (should be 0 since we're setting to end time)
            $earlyLeaving = '00:00:00';
            $overtime = '00:00:00';

            $record->clock_out = $clockOut;
            $record->early_leaving = $earlyLeaving;
            $record->overtime = $overtime;
            $record->save();

            $count++;
        }

        $this->info("Auto check-out completed. {$count} record(s) updated with check-out time of {$defaultEndTime}.");

        return 0;
    }
}

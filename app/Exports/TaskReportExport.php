<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TaskReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $tasks;

    public function __construct($tasks)
    {
        $this->tasks = $tasks;
    }

    public function collection()
    {
        return $this->tasks->map(function ($task) {
            return [
                $task->name,
                $task->project_name,
                $task->assignee_text,
                $task->priority_label,
                $task->stage_name,
                $task->is_done ? __('Completed') : __('Not Completed'),
                $task->start_date,
                $task->end_date,
                $task->marked_at,
                $task->days_late > 0 ? $task->days_late : 0,
                $task->progress . '%',
                $task->estimated_hrs,
                $task->actual_hrs,
            ];
        });
    }

    public function headings(): array
    {
        return [
            __('Task'),
            __('Department'),
            __('Assigned To'),
            __('Priority'),
            __('Stage'),
            __('Status'),
            __('Start Date'),
            __('Due Date'),
            __('Completed On'),
            __('Days Late'),
            __('Progress'),
            __('Estimated Hours'),
            __('Actual Hours'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * One CRM report section as a sheet: a title, the filter summary, then the table.
 * The rows are built by CrmReportController from the same service calls the page
 * uses, so the file always matches the screen.
 */
class CrmReportExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
        private bool $rtl = false
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        // Excel sheet names: max 31 chars, no []:*?/\
        return mb_substr(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $this->title), 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft($this->rtl);
                $sheet->freezePane('A2');
                $sheet->getStyle('1:1')->getFont()->setBold(true);
            },
        ];
    }
}

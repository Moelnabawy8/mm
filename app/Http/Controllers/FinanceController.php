<?php

namespace App\Http\Controllers;


class FinanceController extends Controller
{
    /**
     * Redirect map: finance/{section}/{subsection} → existing named route
     */
    private array $redirectMap = [
        'sales/refunds'             => 'custom-credit.note',
        'sales/recurring'           => 'invoice.index',
        'purchases/refunds'         => 'custom-debit.note',
        'payroll/salary-components' => 'allowanceoption.index',
        'attendance/log-sessions'   => 'attendanceemployee.index',
    ];

    /**
     * Icons for "coming soon" sections
     */
    private array $sectionIcons = [
        'accounting'              => 'ti-calculator',
        'organizational-structure'=> 'ti-sitemap',
        'attendance'              => 'ti-calendar-time',
        'payroll'                 => 'ti-cash',
        'reports'                 => 'ti-chart-bar',
        'cost-centers'            => 'ti-building-bank',
        'chart'                   => 'ti-layout-dashboard',
        'shifts'                  => 'ti-clock',
        'allocated-shifts'        => 'ti-clipboard-list',
        'ai'                      => 'ti-robot',
    ];

    public function index($section = 'dashboard', $subsection = null)
    {
        $key = $section . ($subsection ? '/' . $subsection : '');

        if (isset($this->redirectMap[$key])) {
            return redirect()->route($this->redirectMap[$key]);
        }

        $sectionName    = ucwords(str_replace('-', ' ', $section));
        $subSectionName = $subsection ? ucwords(str_replace('-', ' ', $subsection)) : '';
        $pageTitle      = $subSectionName ? "$sectionName - $subSectionName" : $sectionName;
        $icon           = $this->sectionIcons[$subsection ?? $section] ?? 'ti-tool';

        return view('finance.index', compact(
            'section', 'subsection', 'sectionName', 'subSectionName', 'pageTitle', 'icon'
        ));
    }
}

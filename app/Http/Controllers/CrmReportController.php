<?php

namespace App\Http\Controllers;

use App\Exports\CrmReportExport;
use App\Services\CrmReports\EmployeeReport;
use App\Services\CrmReports\ReportContext;
use App\Services\LeadScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * CRM Reports: one page per category (Employee / Data / Financial).
 *
 * The Employee page renders the filter bar and the KPI strip; each report tab is
 * fetched on demand from section(), so opening the page runs two aggregate queries
 * rather than all eight reports.
 */
class CrmReportController extends Controller
{
    /** Tab key => title. The order is the order of the tabs. */
    const EMPLOYEE_SECTIONS = [
        'leads'          => 'Leads per employee',
        'actions'        => 'Actions & conversion',
        'stages'         => 'Lead stages',
        'neglected'      => 'Neglected leads',
        'communications' => 'Activity log',
        'meetings'       => 'Meetings',
        'comparison'     => 'Quick comparison',
        'detailed'       => 'Detailed comparison',
    ];

    const STALE_OPTIONS = [3, 7, 14, 30];

    private function denied()
    {
        return redirect()->back()->with('error', __('Permission Denied.'));
    }

    private function context(Request $request): ReportContext
    {
        return ReportContext::fromRequest($request, \Auth::user());
    }

    public function employee(Request $request)
    {
        if (!\Auth::user()->can('show crm reports')) {
            return $this->denied();
        }

        $ctx = $this->context($request);
        $report = new EmployeeReport($ctx);

        $section = $request->input('tab');
        if (!array_key_exists($section, self::EMPLOYEE_SECTIONS)) {
            $section = 'leads';
        }

        return view('crm_reports.employee', [
            'ctx'      => $ctx,
            'kpis'     => $report->kpis(),
            'sections' => self::EMPLOYEE_SECTIONS,
            'active'   => $section,
            'options'  => $this->filterOptions($ctx),
        ]);
    }

    /** One report tab as HTML, for the page's tab loader. */
    public function section(Request $request, string $section)
    {
        if (!\Auth::user()->can('show crm reports')) {
            return response()->json(['error' => __('Permission Denied.')], 403);
        }
        if (!array_key_exists($section, self::EMPLOYEE_SECTIONS)) {
            abort(404);
        }

        $ctx = $this->context($request);
        $report = new EmployeeReport($ctx);
        $page = max(1, (int) $request->input('page', 1));
        $data = ['ctx' => $ctx, 'section' => $section, 'query' => $ctx->query()];

        switch ($section) {
            case 'leads':
                $data += $report->leadsPerEmployee();
                break;
            case 'actions':
                $data += $report->actionAndConversion();
                break;
            case 'stages':
                $data += $report->stages();
                break;
            case 'neglected':
                $data += $report->neglected($this->staleDays($request), $page);
                $data['staleOptions'] = self::STALE_OPTIONS;
                break;
            case 'communications':
                $type = in_array($request->input('kind'), EmployeeReport::LOG_KINDS, true) ? $request->input('kind') : null;
                $data += $report->communicationLog($type, $page);
                $data['heatmap'] = $report->activityHeatmap();
                break;
            case 'meetings':
                $f = $this->meetingFilters($request);
                $data += $report->meetings($f);
                $data['meetingFilters'] = $f;
                $data['meetingTypes'] = DB::table('sales_meeting_types')->where('created_by', $ctx->companyId)->orderBy('name')->pluck('name', 'id');
                break;
            case 'comparison':
                $data += $report->comparison();
                break;
            case 'detailed':
                $data += $report->detailedComparison();
                break;
        }

        return response()->json(['html' => view('crm_reports.employee.' . $section, $data)->render()]);
    }

    public function export(Request $request, string $section)
    {
        if (!\Auth::user()->can('show crm reports')) {
            return $this->denied();
        }
        if (!array_key_exists($section, self::EMPLOYEE_SECTIONS)) {
            abort(404);
        }

        $ctx = $this->context($request);
        $report = new EmployeeReport($ctx);
        [$headings, $rows] = $this->exportRows($report, $section, $request);

        $title = __(self::EMPLOYEE_SECTIONS[$section]);
        $file = 'crm-' . $section . '-' . $ctx->from->toDateString() . '_' . $ctx->to->toDateString() . '.xlsx';
        $rtl = (\Auth::user()->lang ?? app()->getLocale()) === 'ar';

        return Excel::download(new CrmReportExport($title, $headings, $rows, $rtl), $file);
    }

    public function data()
    {
        if (!\Auth::user()->can('show crm reports')) {
            return $this->denied();
        }

        return view('crm_reports.coming_soon', ['category' => 'data']);
    }

    public function financial()
    {
        if (!\Auth::user()->can('show crm reports')) {
            return $this->denied();
        }

        return view('crm_reports.coming_soon', ['category' => 'financial']);
    }

    // ---------------------------------------------------------------- helpers

    private function staleDays(Request $request): int
    {
        $d = (int) $request->input('stale', 7);

        return in_array($d, self::STALE_OPTIONS, true) ? $d : 7;
    }

    private function meetingFilters(Request $request): array
    {
        $status = $request->input('meeting_status');
        $allowed = array_merge(EmployeeReport::MEETING_STATUSES, ['past', 'upcoming']);

        return [
            'meeting_type'    => $request->filled('meeting_type') ? (int) $request->input('meeting_type') : null,
            'meeting_status'  => in_array($status, $allowed, true) ? $status : null,
            'meeting_country' => $request->filled('meeting_country') ? (string) $request->input('meeting_country') : null,
        ];
    }

    /**
     * Dropdown contents for the filter bar, all bounded. The lists read off the
     * whole leads table (~200k rows in production, 2-3s) are cached per company for
     * 10 minutes; a new campaign shows up in the filter within that time.
     */
    private function filterOptions(ReportContext $ctx): array
    {
        $usr = \Auth::user();
        $leads = fn () => DB::table('leads')->where('created_by', $ctx->companyId);
        $cached = \Cache::remember('crm-reports.filters.' . $ctx->companyId, 600, fn () => [
            'owners'    => DB::table('user_leads as ul')->join('leads as l', 'l.id', '=', 'ul.lead_id')
                ->where('l.created_by', $ctx->companyId)
                ->distinct()->limit(500)->pluck('ul.user_id')->all(),
            'campaigns' => $leads()->whereNotNull('campaign_name')->where('campaign_name', '!=', '')
                ->select('campaign_name', DB::raw('COUNT(*) as n'))->groupBy('campaign_name')
                ->orderByDesc('n')->limit(200)->pluck('campaign_name')->sort()->values()->all(),
            'countries' => $leads()->whereIn('country', LeadScope::COUNTRIES)
                ->select('country', DB::raw('COUNT(*) as n'))->groupBy('country')
                ->orderByDesc('n')->pluck('country')->sort()->values()->all(),
        ]);

        if ($ctx->seesEveryone()) {
            // Sales people, plus anyone else who has leads assigned to them.
            $ids = LeadScope::salesUsersQuery($usr)->pluck('users.id')->all();
            $owners = $cached['owners'];
            $employees = DB::table('users')->whereIn('id', array_unique(array_merge($ids, $owners)))
                ->orderBy('name')->pluck('name', 'id');
        } else {
            $employees = DB::table('users')->whereIn('id', $ctx->visibleIds)->orderBy('name')->pluck('name', 'id');
        }

        return [
            'employees' => $employees,
            'pipelines' => DB::table('pipelines')->where('created_by', $ctx->companyId)->orderBy('name')->pluck('name', 'id'),
            'sources'   => DB::table('sources')->where('created_by', $ctx->companyId)->orderBy('name')->pluck('name', 'id'),
            'campaigns' => collect($cached['campaigns']),
            'countries' => collect($cached['countries']),
            'periods'   => [
                'today' => __('Today'), '7d' => __('Last 7 days'), '30d' => __('Last 30 days'), '90d' => __('Last 90 days'),
                'mtd' => __('This month'), 'last_month' => __('Last month'), 'qtd' => __('This quarter'),
                'ytd' => __('This year'), 'all' => __('All time'), 'custom' => __('Custom range'),
            ],
        ];
    }

    /** [headings, rows] for one section, from the same service calls as the page. */
    private function exportRows(EmployeeReport $report, string $section, Request $request): array
    {
        $limit = 20000;

        switch ($section) {
            case 'leads':
                $d = $report->leadsPerEmployee();

                return [
                    [__('Employee'), __('Leads'), __('Previous period')],
                    $d['rows']->map(fn ($r) => [$r->name, $r->leads, $r->prev])->all(),
                ];

            case 'actions':
                $d = $report->actionAndConversion();

                return [
                    [__('Employee'), __('Leads'), __('Action taken'), __('Action rate %'), __('Converted to deal'), __('Conversion rate %')],
                    $d['rows']->map(fn ($r) => [$r->name, $r->leads, $r->actioned, $r->action_rate, $r->dealt, $r->conversion])->all(),
                ];

            case 'stages':
                $d = $report->stages();
                $head = array_merge([__('Employee')], array_map(fn ($s) => $s !== '' ? $s : __('No status'), $d['statuses']),
                    [__('No deal'), __('Leads')]);
                $rows = [];
                foreach ($d['matrix'] as $m) {
                    $row = [$m['name']];
                    foreach ($d['statuses'] as $s) {
                        $row[] = $m['statuses'][$s] ?? 0;
                    }
                    $row[] = $m['none'];
                    $row[] = $m['total'];
                    $rows[] = $row;
                }

                return [$head, $rows];

            case 'neglected':
                $rows = $report->neglectedList($this->staleDays($request), 0, $limit);

                return [
                    [__('Lead #'), __('Name'), __('Phone'), __('Assigned to'), __('Deal status'), __('Campaign'), __('Country'),
                        __('Created'), __('Last action'), __('Idle days')],
                    $rows->map(fn ($r) => [$r->id, $r->name, $r->phone, $r->owner, $r->deal_status ?: __('No deal'), $r->campaign_name, $r->country,
                        $r->created_at, $r->last_at ?: __('Never'), (int) $r->idle_days])->all(),
                ];

            case 'communications':
                $type = in_array($request->input('kind'), EmployeeReport::LOG_KINDS, true) ? $request->input('kind') : null;
                $rows = $report->communicationLogQuery($type)->limit($limit)->get();
                $report->prepareLogRows($rows);
                $what = fn ($r) => $r->kind === 'meeting'
                    ? EmployeeReport::meetingTypeLabel($r->outcome)
                    : __('Evaluation');
                $result = fn ($r) => $r->kind === 'meeting'
                    ? EmployeeReport::meetingStatusLabel($r->detail)
                    : EmployeeReport::evaluationLabel($r->outcome, $r->detail);

                return [
                    [__('Date'), __('Employee'), __('Type'), __('Lead #'), __('Customer'), __('Phone'), __('Status / result'),
                        __('Title'), __('Details')],
                    $rows->map(fn ($r) => [$r->created_at, $r->actor_name, $what($r), $r->lead_id, $r->lead_name,
                        $r->lead_phone, $result($r), (string) $r->title, mb_substr($r->body, 0, 1000)])->all(),
                ];

            case 'meetings':
                $d = $report->meetings($this->meetingFilters($request));
                $types = $d['types'];
                $rows = $d['perEmployee']->map(function ($r) use ($types, $d) {
                    $row = [$r->name, $r->n, $r->leads, $r->done, $r->no_show, $r->cancelled, $r->to_deal, $r->deal_rate];
                    foreach ($types as $t) {
                        $row[] = $d['typeMatrix'][(int) $r->uid][$t] ?? 0;
                    }

                    return $row;
                })->all();

                return [
                    array_merge([__('Employee'), __('Meetings'), __('Leads met'), __('Done'), __('No show'), __('Cancelled'),
                        __('Led to deal'), __('Meeting to deal %')], array_map(fn ($t) => $t !== '' ? $t : __('No type'), $types)),
                    $rows,
                ];

            case 'comparison':
            case 'detailed':
                $d = $report->detailedComparison();

                return [
                    [__('Employee'), __('Leads'), __('Action taken'), __('Leads with meeting'), __('Meetings'),
                        __('Leads with a call meeting'), __('Call meeting rate %'), __('Leads with a presentation'), __('Presentation rate %'),
                        __('Converted to deal'), __('Conversion rate %'), __('Purchased'),
                        __('Cash customers'), __('Installment customers'), __('Revenue'), __('Qualified (quality)'),
                        __('Leads previous period'), __('Deals previous period')],
                    $d['rows']->map(fn ($r) => [$r->name, $r->leads, $r->actioned, $r->met, $r->meetings,
                        $r->met_call, $r->call_rate, $r->met_pres, $r->presentation_rate, $r->dealt, $r->conversion, $r->bought, $r->cash, $r->installment, $r->revenue,
                        $r->qualified, $r->prev_leads, $r->prev_dealt])->all(),
                ];
        }

        return [[], []];
    }
}

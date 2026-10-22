<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreSodRequest;
use App\Http\Requests\StoreEodRequest;
use App\Services\DailyReportService;
use App\Services\ReportService;
use App\Http\Resources\DailyReportResource;

class DailyReportController extends Controller
{
    protected $dailyReportService;
    protected $reportService;

    public function __construct(DailyReportService $dailyReportService, ReportService $reportService)
    {
        $this->dailyReportService = $dailyReportService;
        $this->reportService = $reportService;
    }

    /**
     * Submit Start-of-Day (SOD) Report.
     *
     * @param StoreSodRequest $request
     * @return JsonResponse
     */
    public function startOfDay(StoreSodRequest $request): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1; // Fallback to 1 for testing if not auth
            $report = $this->dailyReportService->submitSod(
                $userId,
                $request->validated()['report_date'],
                $request->validated()
            );

            // Fetch cumulative achievements for context
            $cumulative = $this->dailyReportService->calculateCumulativeAchievements($userId, $report->report_date->toDateString());
            $report->setAttribute('cumulative_achievements', $cumulative);

            return response()->json([
                'success' => true,
                'message' => 'Start-of-Day report submitted successfully.',
                'data' => new DailyReportResource($report)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Submit End-of-Day (EOD) Report.
     *
     * @param StoreEodRequest $request
     * @return JsonResponse
     */
    public function endOfDay(StoreEodRequest $request): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1; // Fallback to 1 for testing if not auth
            $report = $this->dailyReportService->submitEod(
                $userId,
                $request->validated()['report_date'],
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'End-of-Day report submitted successfully.',
                'data' => new DailyReportResource($report)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get Report Summary Analytics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'user_id', 'department_id']);
        $summary = $this->reportService->getSummary($filters);

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Get Detailed Reports.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function detailed(Request $request): JsonResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'user_id']);
        $detailed = $this->reportService->getDetailed($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => DailyReportResource::collection($detailed['reports']),
                'charts' => $detailed['charts']
            ]
        ]);
    }

    /**
     * Get Management Dashboard Snapshot.
     *
     * @return JsonResponse
     */
    public function snapshot(): JsonResponse
    {
        $snapshot = $this->reportService->getSnapshot();

        return response()->json([
            'success' => true,
            'data' => $snapshot
        ]);
    }

    public function export(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'user_id', 'department_id']);
        
        $query = \App\Models\DailyReport::with('user')->orderBy('report_date', 'desc');
        if (!empty($filters['start_date'])) $query->whereDate('report_date', '>=', $filters['start_date']);
        if (!empty($filters['end_date'])) $query->whereDate('report_date', '<=', $filters['end_date']);
        if (!empty($filters['user_id'])) $query->where('user_id', $filters['user_id']);
        
        $reports = $query->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=daily_reports_" . date('Y-m-d_His') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($reports) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Arabic in Excel

            // Headers mapping
            $translations = [
                'id' => 'ID',
                'report_date' => 'Date',
                'employee' => 'Employee Name',
                'status' => 'Status',
                "total_clients_received" => "إجمالي العملاء المستلمين",
                "total_sales_achieved" => "إجمالي المبيعات",
                "total_collections_value" => "قيمة التحصيلات",
                "total_clients_in_progress" => "عملاء قيد التواصل",
                "total_clients_upsell" => "عملاء Upsell",
                "total_lost_clients" => "العملاء المفقودين",
                "current_month_target" => "تارجت الشهر الحالي",
                "weekly_target" => "التارجت الأسبوعي",
                "daily_target" => "التارجت اليومي",
                "achieved_till_today_month" => "المحقق حتى اليوم",
                "clients_to_close_this_month" => "عملاء للإغلاق هذا الشهر",
                "top_three_clients_today" => "أهم 3 عملاء لليوم",
                "new_data_received_today" => "داتا جديدة مستلمة اليوم",
                "target_collection_today" => "التحصيل المستهدف لليوم",
                "clients_to_contact_today" => "العملاء المستهدف التواصل معهم",
                "presentations_done_this_month" => "برزنتيشن منذ بداية الشهر",
                "remaining_debts_value" => "المديونيات المتبقية",
                "debts_to_collect_this_month" => "مديونيات للتحصيل هذا الشهر",
                "expected_work_hours_today" => "ساعات العمل المتوقعة",
                "subscribed_clients_to_followup_today" => "متابعة العملاء المشتركين",
                "ai_usage_plan_today" => "خطة استخدام الذكاء الصناعي",
                "vip_client_recommendation" => "ترشيح عميل VIP",
                "ideas_to_work_on_today" => "أفكار للعمل اليوم",
                "data_received_today" => "داتا تم استلامها اليوم",
                "sales_achieved_today" => "مبيعات اليوم",
                "achieved_till_now_month_target" => "المحقق من تارجت الشهر",
                "achieved_till_now_weekly_target" => "المحقق من تارجت الأسبوع",
                "did_achieve_daily_target" => "هل حققت التارجت اليومي؟",
                "collection_achieved_today" => "التحصيل المحقق اليوم",
                "clients_worked_to_close_today" => "عملاء تم العمل لإغلاقهم",
                "did_achieve_collection_target" => "هل حققت هدف التحصيل؟",
                "clients_contacted_today" => "عملاء تم التواصل معهم",
                "presentations_done_today" => "برزنتيشن تمت اليوم",
                "did_upload_presentations" => "تم رفع البرزنتيشن؟",
                "debts_collected_today" => "المديونيات المحصلة اليوم",
                "actual_work_hours_today" => "ساعات العمل الفعلية",
                "proof_of_work_hours" => "دليل ساعات العمل",
                "actual_ai_usage_today" => "استخدام الذكاء الصناعي الفعلي",
                "top_three_clients_results" => "نتائج أهم 3 عملاء",
                "new_vip_client_recommendation" => "ترشيح عميل VIP إضافي",
                "ideas_for_tomorrow" => "أفكار للغد"
            ];

            // Write CSV Header
            fputcsv($file, array_values($translations));

            // Write Rows
            foreach ($reports as $row) {
                $sod = is_array($row->sod_data) ? $row->sod_data : [];
                $eod = is_array($row->eod_data) ? $row->eod_data : [];

                $csvRow = [];
                foreach (array_keys($translations) as $key) {
                    if ($key === 'id') $csvRow[] = $row->id;
                    elseif ($key === 'report_date') $csvRow[] = $row->report_date ? $row->report_date->toDateString() : '';
                    elseif ($key === 'employee') $csvRow[] = $row->user ? $row->user->name : 'Unknown';
                    elseif ($key === 'status') $csvRow[] = $row->status;
                    elseif (array_key_exists($key, $sod)) $csvRow[] = str_replace(["\r", "\n"], " ", $sod[$key]);
                    elseif (array_key_exists($key, $eod)) $csvRow[] = str_replace(["\r", "\n"], " ", $eod[$key]);
                    else $csvRow[] = '';
                }

                fputcsv($file, $csvRow);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Web Views
     */
    public function sodView()
    {
        if (!\Auth::user()->can('manage sod')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
        return view('daily_reports.sod');
    }

    public function eodView()
    {
        if (!\Auth::user()->can('manage eod')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
        return view('daily_reports.eod');
    }

    public function dashboardView()
    {
        if (!\Auth::user()->can('manage reports dashboard')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
        return view('daily_reports.dashboard');
    }
}

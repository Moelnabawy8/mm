<?php

namespace App\Services;

use App\Models\DailyReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyReportService
{
    /**
     * Handle Start-of-Day (SOD) Submission.
     * Focuses on forecasting, goal setting, and historical data retrieval.
     *
     * @param int $userId
     * @param string $reportDate
     * @param array $data
     * @return DailyReport
     * @throws \Exception
     */
    public function submitSod(int $userId, string $reportDate, array $data): DailyReport
    {
        $existing = DailyReport::where('user_id', $userId)
            ->where('report_date', $reportDate)
            ->first();

        // Prevent duplicate entries for the same user/date
        if ($existing && $existing->sod_submitted_at) {
            throw new \Exception("Start-of-Day report already submitted for this date.");
        }

        $report = DailyReport::updateOrCreate(
            ['user_id' => $userId, 'report_date' => $reportDate],
            array_merge($data, [
                'sod_submitted_at' => Carbon::now(),
                'status' => 'draft'
            ])
        );

        return $report;
    }

    /**
     * Handle End-of-Day (EOD) Submission.
     * Focuses on performance measurement, KPI auditing, and AI-driven insights.
     *
     * @param int $userId
     * @param string $reportDate
     * @param array $data
     * @return DailyReport
     * @throws \Exception
     */
    public function submitEod(int $userId, string $reportDate, array $data): DailyReport
    {
        $report = DailyReport::where('user_id', $userId)
            ->where('report_date', $reportDate)
            ->first();

        // Enforce a strict sequence: EOD submission is locked until an SOD entry exists for the same day.
        if (!$report || !$report->sod_submitted_at) {
            throw new \Exception("Cannot submit End-of-Day report without a Start-of-Day entry.");
        }

        // Prevent duplicate EOD entries
        if ($report->eod_submitted_at) {
            throw new \Exception("End-of-Day report already submitted for this date.");
        }

        $report->update(array_merge($data, [
            'eod_submitted_at' => Carbon::now(),
            'status' => 'submitted'
        ]));

        return $report;
    }

    /**
     * Automatically calculate "Cumulative Achievements" from the start of the month/week
     * relative to the current report_date.
     *
     * @param int $userId
     * @param string $reportDate
     * @return array
     */
    public function calculateCumulativeAchievements(int $userId, string $reportDate): array
    {
        $date = Carbon::parse($reportDate);
        
        $startOfMonth = $date->copy()->startOfMonth()->toDateString();
        $startOfWeek = $date->copy()->startOfWeek()->toDateString();

        $monthlyData = DailyReport::where('user_id', $userId)
            ->whereBetween('report_date', [$startOfMonth, $reportDate])
            ->select(
                DB::raw('SUM(sales_count) as total_sales'),
                DB::raw('SUM(collections_actual) as total_collections'),
                DB::raw('SUM(closed_deals) as total_closed_deals'),
                DB::raw('SUM(monthly_target_total) as sum_monthly_targets')
            )
            ->first();

        $weeklyData = DailyReport::where('user_id', $userId)
            ->whereBetween('report_date', [$startOfWeek, $reportDate])
            ->select(
                DB::raw('SUM(sales_count) as total_sales'),
                DB::raw('SUM(collections_actual) as total_collections'),
                DB::raw('SUM(closed_deals) as total_closed_deals'),
                DB::raw('SUM(weekly_target_total) as sum_weekly_targets')
            )
            ->first();

        // Planned vs Actual variance analysis
        $monthlyVariance = ($monthlyData->total_sales ?? 0) - ($monthlyData->sum_monthly_targets ?? 0);
        $weeklyVariance = ($weeklyData->total_sales ?? 0) - ($weeklyData->sum_weekly_targets ?? 0);

        return [
            'monthly' => [
                'actual_sales' => $monthlyData->total_sales ?? 0,
                'planned_target' => $monthlyData->sum_monthly_targets ?? 0,
                'variance' => $monthlyVariance,
                'collections' => $monthlyData->total_collections ?? 0,
                'closed_deals' => $monthlyData->total_closed_deals ?? 0,
            ],
            'weekly' => [
                'actual_sales' => $weeklyData->total_sales ?? 0,
                'planned_target' => $weeklyData->sum_weekly_targets ?? 0,
                'variance' => $weeklyVariance,
                'collections' => $weeklyData->total_collections ?? 0,
                'closed_deals' => $weeklyData->total_closed_deals ?? 0,
            ]
        ];
    }
}

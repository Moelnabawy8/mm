<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Revenue;
use Carbon\Carbon;

class AiReportController extends Controller
{
    public function index()
    {
        $creatorId = \Auth::user()->creatorId();
        $now       = Carbon::now();

        // ── Monthly income vs expense (last 6 months) ──────────────────────────
        $months         = [];
        $incomeByMonth  = [];
        $expenseByMonth = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $months[] = $month->format('M Y');

            $incomeByMonth[] = (float) Revenue::where('created_by', $creatorId)
                ->whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->sum('amount');

            $expenseByMonth[] = (float) Payment::where('created_by', $creatorId)
                ->whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->sum('amount');
        }

        // ── Invoice status breakdown ────────────────────────────────────────────
        $invoiceStats = Invoice::where('created_by', $creatorId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // ── Summary KPIs ───────────────────────────────────────────────────────
        $totalIncome   = Revenue::where('created_by', $creatorId)->sum('amount');
        $totalExpense  = Payment::where('created_by', $creatorId)->sum('amount');
        $totalInvoices = Invoice::where('created_by', $creatorId)->count();
        $totalBills    = Bill::where('created_by', $creatorId)->count();
        $totalEmployees = Employee::where('created_by', $creatorId)->count();
        $netProfit     = $totalIncome - $totalExpense;

        // ── This month highlights ──────────────────────────────────────────────
        $thisMonthIncome  = (float) Revenue::where('created_by', $creatorId)
            ->whereYear('date', $now->year)->whereMonth('date', $now->month)->sum('amount');
        $thisMonthExpense = (float) Payment::where('created_by', $creatorId)
            ->whereYear('date', $now->year)->whereMonth('date', $now->month)->sum('amount');

        // ── AI-style insight messages ──────────────────────────────────────────
        $insights = $this->generateInsights(
            $thisMonthIncome, $thisMonthExpense, $incomeByMonth, $expenseByMonth, $netProfit
        );

        return view('aireport.index', compact(
            'months', 'incomeByMonth', 'expenseByMonth',
            'invoiceStats',
            'totalIncome', 'totalExpense', 'totalInvoices', 'totalBills', 'totalEmployees', 'netProfit',
            'thisMonthIncome', 'thisMonthExpense',
            'insights'
        ));
    }

    private function generateInsights(float $income, float $expense, array $incomes, array $expenses, float $net): array
    {
        $insights = [];

        // Net profit health
        if ($net > 0) {
            $insights[] = ['type' => 'success', 'icon' => 'ti-trending-up',
                'text' => __('Business is profitable. Net profit is positive — keep monitoring expenses.')];
        } elseif ($net < 0) {
            $insights[] = ['type' => 'danger', 'icon' => 'ti-alert-triangle',
                'text' => __('Expenses exceed income overall. Review cost structure to improve profitability.')];
        }

        // This month vs last month
        $lastIncome  = count($incomes) >= 2 ? $incomes[count($incomes) - 2] : 0;
        $lastExpense = count($expenses) >= 2 ? $expenses[count($expenses) - 2] : 0;

        if ($lastIncome > 0 && $income > $lastIncome) {
            $pct = round(($income - $lastIncome) / $lastIncome * 100, 1);
            $insights[] = ['type' => 'success', 'icon' => 'ti-arrow-up-right',
                'text' => __("Income grew by {$pct}% compared to last month.")];
        } elseif ($lastIncome > 0 && $income < $lastIncome) {
            $pct = round(($lastIncome - $income) / $lastIncome * 100, 1);
            $insights[] = ['type' => 'warning', 'icon' => 'ti-arrow-down-right',
                'text' => __("Income dropped by {$pct}% compared to last month.")];
        }

        if ($lastExpense > 0 && $expense > $lastExpense * 1.1) {
            $insights[] = ['type' => 'warning', 'icon' => 'ti-coin',
                'text' => __('Expenses increased significantly this month. Review recent payments.')];
        }

        // Current month margin
        if ($income > 0) {
            $margin = round(($income - $expense) / $income * 100, 1);
            $insights[] = ['type' => $margin >= 20 ? 'success' : ($margin >= 0 ? 'info' : 'danger'),
                'icon' => 'ti-report-analytics',
                'text' => __("This month's profit margin is {$margin}%.")];
        }

        if (empty($insights)) {
            $insights[] = ['type' => 'info', 'icon' => 'ti-info-circle',
                'text' => __('Add financial transactions to start seeing AI-powered insights.')];
        }

        return $insights;
    }
}

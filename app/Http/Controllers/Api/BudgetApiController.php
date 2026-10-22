<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Budget;
use App\Models\ProductServiceCategory;
use App\Models\Revenue;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Bill;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BudgetApiController extends Controller
{
    use ApiResponser;

    public function getBudgets()
    {
        $budgets = Budget::where('created_by', Auth::user()->creatorId())
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['budgets' => $budgets], 'Budgets retrieved.');
    }

    public function createBudget(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'year' => 'required|integer|min:2000|max:2100',
            'period' => 'required|in:monthly,quarterly,half-yearly,yearly',
            'income' => 'required|array',
            'expense' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Validate income and expense categories belong to user's company
        $incomeCategoryIds = array_keys($request->income);
        $expenseCategoryIds = array_keys($request->expense);

        if (!empty($incomeCategoryIds)) {
            $incomeCategories = ProductServiceCategory::whereIn('id', $incomeCategoryIds)
                ->where('created_by', Auth::user()->creatorId())
                ->where('type', 'income')
                ->count();

            if ($incomeCategories != count($incomeCategoryIds)) {
                return $this->error('One or more income categories not found or access denied.', 404);
            }
        }

        if (!empty($expenseCategoryIds)) {
            $expenseCategories = ProductServiceCategory::whereIn('id', $expenseCategoryIds)
                ->where('created_by', Auth::user()->creatorId())
                ->where('type', 'expense')
                ->count();

            if ($expenseCategories != count($expenseCategoryIds)) {
                return $this->error('One or more expense categories not found or access denied.', 404);
            }
        }

        $budget = new Budget();
        $budget->name = $request->name;
        $budget->from = $request->year;
        $budget->period = $request->period;
        $budget->income_data = json_encode($request->income);
        $budget->expense_data = json_encode($request->expense);
        $budget->created_by = Auth::user()->creatorId();
        $budget->save();

        // Send notifications
        $setting = Utility::settings(Auth::user()->creatorId());
        $budgetNotificationArr = [
            'budget_period' => Budget::$period[$request->period],
            'budget_year' => $request->year,
            'budget_name' => $request->name,
        ];

        // Slack Notification
        if (isset($setting['budget_notification']) && $setting['budget_notification'] == 1) {
            Utility::send_slack_msg('new_budget', $budgetNotificationArr);
        }

        // Telegram Notification
        if (isset($setting['telegram_budget_notification']) && $setting['telegram_budget_notification'] == 1) {
            Utility::send_telegram_msg('new_budget', $budgetNotificationArr);
        }

        // Webhook
        $module = 'New Budget';
        $webhook = Utility::webhookSetting($module);
        if ($webhook) {
            $parameter = json_encode($budget);
            $status = Utility::WebhookCall($webhook['url'], $parameter, $webhook['method']);
        }

        return $this->success(['budget' => $budget], 'Budget created successfully.');
    }

    public function getBudget($id)
    {
        $budget = Budget::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$budget) {
            return $this->error('Budget not found.', 404);
        }

        $budget->income_data = json_decode($budget->income_data, true) ?? [];
        $budget->expense_data = json_decode($budget->expense_data, true) ?? [];

        return $this->success(['budget' => $budget], 'Budget detail.');
    }

    public function updateBudget(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'year' => 'required|integer|min:2000|max:2100',
            'period' => 'required|in:monthly,quarterly,half-yearly,yearly',
            'income' => 'required|array',
            'expense' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $budget = Budget::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$budget) {
            return $this->error('Budget not found.', 404);
        }

        // Validate income and expense categories belong to user's company
        $incomeCategoryIds = array_keys($request->income);
        $expenseCategoryIds = array_keys($request->expense);

        if (!empty($incomeCategoryIds)) {
            $incomeCategories = ProductServiceCategory::whereIn('id', $incomeCategoryIds)
                ->where('created_by', Auth::user()->creatorId())
                ->where('type', 'income')
                ->count();

            if ($incomeCategories != count($incomeCategoryIds)) {
                return $this->error('One or more income categories not found or access denied.', 404);
            }
        }

        if (!empty($expenseCategoryIds)) {
            $expenseCategories = ProductServiceCategory::whereIn('id', $expenseCategoryIds)
                ->where('created_by', Auth::user()->creatorId())
                ->where('type', 'expense')
                ->count();

            if ($expenseCategories != count($expenseCategoryIds)) {
                return $this->error('One or more expense categories not found or access denied.', 404);
            }
        }

        $budget->name = $request->name;
        $budget->from = $request->year;
        $budget->period = $request->period;
        $budget->income_data = json_encode($request->income);
        $budget->expense_data = json_encode($request->expense);
        $budget->save();

        return $this->success(['budget' => $budget], 'Budget updated successfully.');
    }

    public function deleteBudget($id)
    {
        $budget = Budget::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$budget) {
            return $this->error('Budget not found.', 404);
        }

        $budget->delete();

        return $this->success([], 'Budget deleted successfully.');
    }

    public function getBudgetReport($id)
    {
        $budget = Budget::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$budget) {
            return $this->error('Budget not found.', 404);
        }

        $budget->income_data = json_decode($budget->income_data, true) ?? [];
        $budget->expense_data = json_decode($budget->expense_data, true) ?? [];

        // Calculate budget totals
        $budgetTotal = [];
        $budgetExpenseTotal = [];

        if (!empty($budget->income_data)) {
            foreach ($budget->income_data as $period => $amounts) {
                foreach ($amounts as $key => $value) {
                    $budgetTotal[$key] = isset($budgetTotal[$key]) ? $budgetTotal[$key] + $value : $value;
                }
            }
        }

        if (!empty($budget->expense_data)) {
            foreach ($budget->expense_data as $period => $amounts) {
                foreach ($amounts as $key => $value) {
                    $budgetExpenseTotal[$key] = isset($budgetExpenseTotal[$key]) ? $budgetExpenseTotal[$key] + $value : $value;
                }
            }
        }

        // Get actual data
        $year = !empty($budget->from) ? $budget->from : date('Y');
        $actualIncomeData = $this->getActualIncomeData($budget, $year);
        $actualExpenseData = $this->getActualExpenseData($budget, $year);

        // Calculate actual totals
        $actualIncomeTotal = [];
        $actualExpenseTotal = [];

        foreach ($actualIncomeData as $categoryId => $periodData) {
            foreach ($periodData as $period => $amount) {
                $actualIncomeTotal[$period] = isset($actualIncomeTotal[$period]) ? $actualIncomeTotal[$period] + $amount : $amount;
            }
        }

        foreach ($actualExpenseData as $categoryId => $periodData) {
            foreach ($periodData as $period => $amount) {
                $actualExpenseTotal[$period] = isset($actualExpenseTotal[$period]) ? $actualExpenseTotal[$period] + $amount : $amount;
            }
        }

        // Calculate profits
        $budgetProfit = [];
        $actualProfit = [];
        $allPeriods = array_unique(array_merge(array_keys($budgetTotal), array_keys($budgetExpenseTotal), array_keys($actualIncomeTotal), array_keys($actualExpenseTotal)));

        foreach ($allPeriods as $period) {
            $budgetProfit[$period] = ($budgetTotal[$period] ?? 0) - ($budgetExpenseTotal[$period] ?? 0);
            $actualProfit[$period] = ($actualIncomeTotal[$period] ?? 0) - ($actualExpenseTotal[$period] ?? 0);
        }

        return $this->success([
            'budget' => $budget,
            'budget_totals' => $budgetTotal,
            'budget_expense_totals' => $budgetExpenseTotal,
            'actual_income_data' => $actualIncomeData,
            'actual_expense_data' => $actualExpenseData,
            'actual_income_totals' => $actualIncomeTotal,
            'actual_expense_totals' => $actualExpenseTotal,
            'budget_profit' => $budgetProfit,
            'actual_profit' => $actualProfit
        ], 'Budget report retrieved.');
    }

    private function getActualIncomeData($budget, $year)
    {
        $incomeData = [];
        $incomeCategories = array_keys($budget->income_data);

        foreach ($incomeCategories as $categoryId) {
            $category = ProductServiceCategory::find($categoryId);
            if (!$category || $category->type !== 'income') continue;

            $periodData = $this->getPeriodData($budget->period, $year, 'income', $categoryId);
            $incomeData[$categoryId] = $periodData;
        }

        return $incomeData;
    }

    private function getActualExpenseData($budget, $year)
    {
        $expenseData = [];
        $expenseCategories = array_keys($budget->expense_data);

        foreach ($expenseCategories as $categoryId) {
            $category = ProductServiceCategory::find($categoryId);
            if (!$category || $category->type !== 'expense') continue;

            $periodData = $this->getPeriodData($budget->period, $year, 'expense', $categoryId);
            $expenseData[$categoryId] = $periodData;
        }

        return $expenseData;
    }

    private function getPeriodData($period, $year, $type, $categoryId)
    {
        $periodData = [];
        $periods = $this->getPeriodRanges($period);

        foreach ($periods as $periodName => $range) {
            $amount = 0;

            if ($type === 'income') {
                // Revenue
                $revenueQuery = Revenue::where('created_by', Auth::user()->creatorId())
                    ->where('category_id', $categoryId)
                    ->whereRaw('YEAR(date) = ?', [$year]);

                if ($range['start_month'] && $range['end_month']) {
                    $revenueQuery->whereRaw('MONTH(date) >= ?', [$range['start_month']])
                        ->whereRaw('MONTH(date) <= ?', [$range['end_month']]);
                }

                $revenueAmount = $revenueQuery->sum('amount');

                // Invoices
                $invoiceQuery = Invoice::where('created_by', Auth::user()->creatorId())
                    ->where('category_id', $categoryId)
                    ->whereRaw('YEAR(send_date) = ?', [$year]);

                if ($range['start_month'] && $range['end_month']) {
                    $invoiceQuery->whereRaw('MONTH(send_date) >= ?', [$range['start_month']])
                        ->whereRaw('MONTH(send_date) <= ?', [$range['end_month']]);
                }

                $invoices = $invoiceQuery->with(['items'])->get();
                $invoiceAmount = 0;
                foreach ($invoices as $invoice) {
                    $invoiceAmount += $invoice->getTotal();
                }

                $amount = $revenueAmount + $invoiceAmount;
            } else {
                // Payments
                $paymentQuery = Payment::where('created_by', Auth::user()->creatorId())
                    ->where('category_id', $categoryId)
                    ->whereRaw('YEAR(date) = ?', [$year]);

                if ($range['start_month'] && $range['end_month']) {
                    $paymentQuery->whereRaw('MONTH(date) >= ?', [$range['start_month']])
                        ->whereRaw('MONTH(date) <= ?', [$range['end_month']]);
                }

                $paymentAmount = $paymentQuery->sum('amount');

                // Bills
                $billQuery = Bill::where('created_by', Auth::user()->creatorId())
                    ->where('category_id', $categoryId)
                    ->whereRaw('YEAR(send_date) = ?', [$year]);

                if ($range['start_month'] && $range['end_month']) {
                    $billQuery->whereRaw('MONTH(send_date) >= ?', [$range['start_month']])
                        ->whereRaw('MONTH(send_date) <= ?', [$range['end_month']]);
                }

                $bills = $billQuery->with(['items'])->get();
                $billAmount = 0;
                foreach ($bills as $bill) {
                    $billAmount += $bill->getTotal();
                }

                $amount = $paymentAmount + $billAmount;
            }

            $periodData[$periodName] = $amount;
        }

        return $periodData;
    }

    private function getPeriodRanges($period)
    {
        $ranges = [];

        switch ($period) {
            case 'monthly':
                for ($i = 1; $i <= 12; $i++) {
                    $monthName = date("F", strtotime(date('Y-' . $i)));
                    $ranges[$monthName] = ['start_month' => $i, 'end_month' => $i];
                }
                break;
            case 'quarterly':
                $ranges['Jan-Mar'] = ['start_month' => 1, 'end_month' => 3];
                $ranges['Apr-Jun'] = ['start_month' => 4, 'end_month' => 6];
                $ranges['Jul-Sep'] = ['start_month' => 7, 'end_month' => 9];
                $ranges['Oct-Dec'] = ['start_month' => 10, 'end_month' => 12];
                break;
            case 'half-yearly':
                $ranges['Jan-Jun'] = ['start_month' => 1, 'end_month' => 6];
                $ranges['Jul-Dec'] = ['start_month' => 7, 'end_month' => 12];
                break;
            case 'yearly':
                $ranges['Jan-Dec'] = ['start_month' => 1, 'end_month' => 12];
                break;
        }

        return $ranges;
    }

    public function getBudgetPeriods()
    {
        return $this->success(['periods' => Budget::$period], 'Budget periods retrieved.');
    }

    public function getIncomeCategories()
    {
        $categories = ProductServiceCategory::where('created_by', Auth::user()->creatorId())
            ->where('type', 'income')
            ->get(['id', 'name']);

        return $this->success(['categories' => $categories], 'Income categories retrieved.');
    }

    public function getExpenseCategories()
    {
        $categories = ProductServiceCategory::where('created_by', Auth::user()->creatorId())
            ->where('type', 'expense')
            ->get(['id', 'name']);

        return $this->success(['categories' => $categories], 'Expense categories retrieved.');
    }

    public function getYears()
    {
        $years = [];
        $startingYear = date('Y', strtotime('+5 year'));
        $endingYear = date('Y');

        foreach (range($endingYear, $startingYear) as $year) {
            $years[$year] = $year;
        }

        return $this->success(['years' => $years], 'Years retrieved.');
    }

    public function getBudgetStats()
    {
        $totalBudgets = Budget::where('created_by', Auth::user()->creatorId())->count();

        // Group by period
        $budgetsByPeriod = Budget::where('created_by', Auth::user()->creatorId())
            ->get()
            ->groupBy('period')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by year
        $budgetsByYear = Budget::where('created_by', Auth::user()->creatorId())
            ->get()
            ->groupBy('from')
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_budgets' => $totalBudgets,
            'budgets_by_period' => $budgetsByPeriod,
            'budgets_by_year' => $budgetsByYear
        ], 'Budget statistics retrieved.');
    }
}

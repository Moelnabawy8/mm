<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ChartOfAccountSubType;
use App\Models\ChartOfAccountType;
use App\Models\CustomField;
use App\Models\Revenue;
use App\Models\InvoicePayment;
use App\Models\Payment;
use App\Models\BillPayment;
use App\Models\Transaction;
use App\Models\TransactionLines;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BankAccountApiController extends Controller
{
    use ApiResponser;

    public function getBankAccounts()
    {
        $accounts = BankAccount::where('created_by', Auth::user()->creatorId())
            ->with(['chartAccount'])
            ->orderBy('holder_name', 'asc')
            ->get();

        return $this->success(['accounts' => $accounts], 'Bank Accounts retrieved.');
    }

    public function createBankAccount(Request $request)
    {
        $rules = [
            'holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'payment_name' => 'required|string|max:255',
            'chart_account_id' => 'nullable|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'contact_number' => 'nullable|string|regex:/^([0-9\s\-\+\(\)]*)$/',
            'bank_address' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if payment name already exists for this user
        $existingAccount = BankAccount::where('payment_name', $request->payment_name)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if ($existingAccount) {
            return $this->error('This payment name already exists.', 400);
        }

        // Check if chart account belongs to user's company
        if ($request->has('chart_account_id') && $request->chart_account_id) {
            $chartAccount = ChartOfAccount::where('id', $request->chart_account_id)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$chartAccount) {
                return $this->error('Chart account not found or access denied.', 404);
            }
        }

        $account = new BankAccount();
        $account->chart_account_id = $request->chart_account_id;
        $account->payment_name = $request->payment_name;
        $account->holder_name = $request->holder_name;
        $account->bank_name = $request->bank_name;
        $account->account_number = $request->account_number;
        $account->opening_balance = $request->opening_balance ?: 0;
        $account->contact_number = $request->contact_number ?: '-';
        $account->bank_address = $request->bank_address ?: '-';
        $account->created_by = Auth::user()->creatorId();
        $account->save();

        // Save custom fields if provided
        if ($request->has('customField')) {
            CustomField::saveData($account, $request->customField);
        }

        return $this->success(['account' => $account], 'Bank account created successfully.');
    }

    public function getBankAccount($id)
    {
        $account = BankAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['chartAccount'])
            ->first();

        if (!$account) {
            return $this->error('Bank account not found.', 404);
        }

        // Load custom fields
        $account->customField = CustomField::getData($account, 'account');

        return $this->success(['account' => $account], 'Bank account detail.');
    }

    public function updateBankAccount(Request $request, $id)
    {
        $rules = [
            'holder_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'payment_name' => 'required|string|max:255',
            'chart_account_id' => 'nullable|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'contact_number' => 'nullable|string|regex:/^([0-9\s\-\+\(\)]*)$/',
            'bank_address' => 'nullable|string',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = BankAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$account) {
            return $this->error('Bank account not found.', 404);
        }

        // Check if payment name already exists for this user (excluding current account)
        $existingAccount = BankAccount::where('payment_name', $request->payment_name)
            ->where('created_by', Auth::user()->creatorId())
            ->where('id', '!=', $id)
            ->first();

        if ($existingAccount) {
            return $this->error('This payment name already exists.', 400);
        }

        // Check if chart account belongs to user's company
        if ($request->has('chart_account_id') && $request->chart_account_id) {
            $chartAccount = ChartOfAccount::where('id', $request->chart_account_id)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$chartAccount) {
                return $this->error('Chart account not found or access denied.', 404);
            }
        }

        $account->chart_account_id = $request->chart_account_id;
        $account->payment_name = $request->payment_name;
        $account->holder_name = $request->holder_name;
        $account->bank_name = $request->bank_name;
        $account->account_number = $request->account_number;
        $account->opening_balance = $request->opening_balance ?: 0;
        $account->contact_number = $request->contact_number ?: '-';
        $account->bank_address = $request->bank_address ?: '-';
        $account->save();

        // Save custom fields if provided
        if ($request->has('customField')) {
            CustomField::saveData($account, $request->customField);
        }

        return $this->success(['account' => $account], 'Bank account updated successfully.');
    }

    public function deleteBankAccount($id)
    {
        $account = BankAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$account) {
            return $this->error('Bank account not found.', 404);
        }

        // Check for related records
        $revenue = Revenue::where('account_id', $account->id)->first();
        $invoicePayment = InvoicePayment::where('account_id', $account->id)->first();
        $transaction = Transaction::where('account', $account->id)->first();
        $payment = Payment::where('account_id', $account->id)->first();
        $billPayment = BillPayment::where('account_id', $account->id)->first();

        // Delete related transaction lines
        TransactionLines::where('reference_id', $account->id)
            ->where('reference', 'Bank Account')
            ->delete();

        if (!empty($revenue) || !empty($invoicePayment) || !empty($transaction) || !empty($payment) || !empty($billPayment)) {
            return $this->error('Cannot delete bank account with related records. Please delete related records first.', 400);
        }

        $account->delete();

        return $this->success([], 'Bank account deleted successfully.');
    }

    public function getChartAccounts()
    {
        $accountTypes = ChartOfAccountType::where('created_by', Auth::user()->creatorId())->get();
        $chartAccounts = [];

        foreach ($accountTypes as $type) {
            $accountSubTypes = ChartOfAccountSubType::where('type', $type->id)
                ->where('created_by', Auth::user()->creatorId())
                ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])
                ->get();

            $temp = [];
            foreach ($accountSubTypes as $accountSubType) {
                $chartOfAccounts = ChartOfAccount::where('sub_type', $accountSubType->id)
                    ->where('parent', '=', 0)
                    ->where('created_by', Auth::user()->creatorId())
                    ->get();

                $incomeSubAccounts = ChartOfAccount::where('sub_type', $accountSubType->id)
                    ->where('parent', '!=', 0)
                    ->where('created_by', Auth::user()->creatorId())
                    ->get();

                $tempData = [
                    'account_name' => $accountSubType->name,
                    'chart_of_accounts' => [],
                    'subAccounts' => [],
                ];

                foreach ($chartOfAccounts as $chartOfAccount) {
                    $tempData['chart_of_accounts'][] = [
                        'id' => $chartOfAccount->id,
                        'account_number' => $chartOfAccount->account_number,
                        'account_name' => $chartOfAccount->name,
                    ];
                }

                foreach ($incomeSubAccounts as $chartOfAccount) {
                    $tempData['subAccounts'][] = [
                        'id' => $chartOfAccount->id,
                        'account_number' => $chartOfAccount->account_number,
                        'account_name' => $chartOfAccount->name,
                        'parent' => $chartOfAccount->parent,
                        'parent_account' => !empty($chartOfAccount->parentAccount) ? $chartOfAccount->parentAccount->account : 0,
                    ];
                }
                $temp[$accountSubType->id] = $tempData;
            }

            $chartAccounts[$type->name] = $temp;
        }

        return $this->success(['chart_accounts' => $chartAccounts], 'Chart accounts retrieved.');
    }

    public function getPaymentMethods()
    {
        $payments = BankAccount::payments();
        return $this->success(['payment_methods' => $payments], 'Payment methods retrieved.');
    }

    public function getAccountBalance($id)
    {
        $account = BankAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$account) {
            return $this->error('Bank account not found.', 404);
        }

        // Calculate current balance based on transactions
        $revenueTotal = Revenue::where('account_id', $account->id)->sum('amount');
        $invoicePaymentTotal = InvoicePayment::where('account_id', $account->id)->sum('amount');
        $paymentTotal = Payment::where('account_id', $account->id)->sum('amount');
        $billPaymentTotal = BillPayment::where('account_id', $account->id)->sum('amount');

        $currentBalance = $account->opening_balance + $revenueTotal + $invoicePaymentTotal - $paymentTotal - $billPaymentTotal;

        return $this->success([
            'account' => $account,
            'opening_balance' => $account->opening_balance,
            'revenue_total' => $revenueTotal,
            'invoice_payment_total' => $invoicePaymentTotal,
            'payment_total' => $paymentTotal,
            'bill_payment_total' => $billPaymentTotal,
            'current_balance' => $currentBalance
        ], 'Account balance retrieved.');
    }

    public function getAccountTransactions($id, Request $request)
    {
        $account = BankAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$account) {
            return $this->error('Bank account not found.', 404);
        }

        // Get date range from request or use default
        $startDate = $request->get('start_date') ? $request->start_date : date('Y-m-01');
        $endDate = $request->get('end_date') ? $request->end_date : date('Y-m-t');

        // Get revenues
        $revenues = Revenue::where('account_id', $account->id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->get();

        // Get invoice payments
        $invoicePayments = InvoicePayment::where('account_id', $account->id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->get();

        // Get payments
        $payments = Payment::where('account_id', $account->id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->get();

        // Get bill payments
        $billPayments = BillPayment::where('account_id', $account->id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->orderBy('date', 'desc')
            ->get();

        return $this->success([
            'revenues' => $revenues,
            'invoice_payments' => $invoicePayments,
            'payments' => $payments,
            'bill_payments' => $billPayments,
            'start_date' => $startDate,
            'end_date' => $endDate
        ], 'Account transactions retrieved.');
    }

    public function getAccountStats()
    {
        $totalAccounts = BankAccount::where('created_by', Auth::user()->creatorId())->count();

        // Calculate total opening balance
        $totalOpeningBalance = BankAccount::where('created_by', Auth::user()->creatorId())
            ->sum('opening_balance');

        // Calculate total current balance
        $accounts = BankAccount::where('created_by', Auth::user()->creatorId())->get();
        $totalCurrentBalance = 0;

        foreach ($accounts as $account) {
            $revenueTotal = Revenue::where('account_id', $account->id)->sum('amount');
            $invoicePaymentTotal = InvoicePayment::where('account_id', $account->id)->sum('amount');
            $paymentTotal = Payment::where('account_id', $account->id)->sum('amount');
            $billPaymentTotal = BillPayment::where('account_id', $account->id)->sum('amount');

            $totalCurrentBalance += $account->opening_balance + $revenueTotal + $invoicePaymentTotal - $paymentTotal - $billPaymentTotal;
        }

        // Group by bank
        $accountsByBank = BankAccount::where('created_by', Auth::user()->creatorId())
            ->get()
            ->groupBy('bank_name')
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_accounts' => $totalAccounts,
            'total_opening_balance' => $totalOpeningBalance,
            'total_current_balance' => $totalCurrentBalance,
            'accounts_by_bank' => $accountsByBank
        ], 'Bank account statistics retrieved.');
    }

    public function getCustomFields()
    {
        $customFields = CustomField::where('created_by', Auth::user()->creatorId())
            ->where('module', '=', 'account')
            ->get();

        return $this->success(['custom_fields' => $customFields], 'Custom fields retrieved.');
    }
}

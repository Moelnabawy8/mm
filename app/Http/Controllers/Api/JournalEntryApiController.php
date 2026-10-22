<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\ChartOfAccount;
use App\Models\ChartOfAccountType;
use App\Models\ChartOfAccountSubType;
use App\Models\BankAccount;
use App\Models\AddTransactionLine;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JournalEntryApiController extends Controller
{
    use ApiResponser;

    public function getJournalEntries()
    {
        $entries = JournalEntry::where('created_by', Auth::user()->creatorId())
            ->with('accounts')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['entries' => $entries], 'Journal Entries retrieved.');
    }

    public function createJournalEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'accounts' => 'required|array|min:2',
            'accounts.*.account' => 'required|exists:chart_of_accounts,id',
            'accounts.*.description' => 'nullable|string',
            'accounts.*.debit' => 'nullable|numeric|min:0',
            'accounts.*.credit' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $accounts = $request->accounts;
        $totalDebit = 0;
        $totalCredit = 0;

        // Calculate totals and validate each account belongs to user's company
        for ($i = 0; $i < count($accounts); $i++) {
            $debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
            $credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
            $totalDebit += $debit;
            $totalCredit += $credit;

            // Check if account belongs to user's company
            $chartAccount = ChartOfAccount::where('id', $accounts[$i]['account'])
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$chartAccount) {
                return $this->error('Account not found or access denied.', 404);
            }
        }

        if ($totalCredit != $totalDebit) {
            return $this->error('Debit and Credit must be Equal.', 400);
        }

        $journal = new JournalEntry();
        $journal->journal_id = $this->journalNumber();
        $journal->date = $request->date;
        $journal->reference = $request->reference;
        $journal->description = $request->description;
        $journal->created_by = Auth::user()->creatorId();
        $journal->save();

        for ($i = 0; $i < count($accounts); $i++) {
            $journalItem = new JournalItem();
            $journalItem->journal = $journal->id;
            $journalItem->account = $accounts[$i]['account'];
            $journalItem->description = $accounts[$i]['description'];
            $journalItem->debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
            $journalItem->credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
            $journalItem->save();

            // Update bank account balance if applicable
            $bankAccounts = BankAccount::where('chart_account_id', '=', $accounts[$i]['account'])->get();
            if (!empty($bankAccounts)) {
                foreach ($bankAccounts as $bankAccount) {
                    $old_balance = $bankAccount->opening_balance;
                    if ($journalItem->debit > 0) {
                        $new_balance = $old_balance - $journalItem->debit;
                    }
                    if ($journalItem->credit > 0) {
                        $new_balance = $old_balance + $journalItem->credit;
                    }
                    if (isset($new_balance)) {
                        $bankAccount->opening_balance = $new_balance;
                        $bankAccount->save();
                    }
                }
            }

            // Add transaction lines for double-entry bookkeeping
            if (isset($accounts[$i]['debit']) && $accounts[$i]['debit'] > 0) {
                $data = [
                    'account_id' => $accounts[$i]['account'],
                    'transaction_type' => 'debit',
                    'transaction_amount' => $accounts[$i]['debit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);

                $account = ChartOfAccount::where('name', 'Accounts Payable')
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();
                $data = [
                    'account_id' => !empty($account) ? $account->id : 0,
                    'transaction_type' => 'credit',
                    'transaction_amount' => $accounts[$i]['debit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);
            } else {
                $data = [
                    'account_id' => $accounts[$i]['account'],
                    'transaction_type' => 'credit',
                    'transaction_amount' => $accounts[$i]['credit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);

                $account = ChartOfAccount::where('name', 'Accounts Receivable')
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();
                $data = [
                    'account_id' => !empty($account) ? $account->id : 0,
                    'transaction_type' => 'debit',
                    'transaction_amount' => $accounts[$i]['credit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);
            }
        }

        return $this->success(['journal' => $journal->load('accounts')], 'Journal entry created successfully.');
    }

    public function getJournalEntry($id)
    {
        $journal = JournalEntry::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with('accounts.chartAccount')
            ->first();

        if (!$journal) {
            return $this->error('Journal entry not found.', 404);
        }

        return $this->success(['journal' => $journal], 'Journal entry detail.');
    }

    public function updateJournalEntry(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'accounts' => 'required|array|min:2',
            'accounts.*.account' => 'required|exists:chart_of_accounts,id',
            'accounts.*.description' => 'nullable|string',
            'accounts.*.debit' => 'nullable|numeric|min:0',
            'accounts.*.credit' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $journal = JournalEntry::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$journal) {
            return $this->error('Journal entry not found.', 404);
        }

        $accounts = $request->accounts;
        $totalDebit = 0;
        $totalCredit = 0;

        // Calculate totals and validate each account belongs to user's company
        for ($i = 0; $i < count($accounts); $i++) {
            $debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
            $credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
            $totalDebit += $debit;
            $totalCredit += $credit;

            // Check if account belongs to user's company
            $chartAccount = ChartOfAccount::where('id', $accounts[$i]['account'])
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$chartAccount) {
                return $this->error('Account not found or access denied.', 404);
            }
        }

        if ($totalCredit != $totalDebit) {
            return $this->error('Debit and Credit must be Equal.', 400);
        }

        $journal->date = $request->date;
        $journal->reference = $request->reference;
        $journal->description = $request->description;
        $journal->save();

        // Delete existing transaction lines
        AddTransactionLine::where('reference_id', $journal->id)
            ->where('reference', 'Journal Entry')
            ->delete();

        for ($i = 0; $i < count($accounts); $i++) {
            $journalItem = JournalItem::find($accounts[$i]['id']);

            if ($journalItem == null) {
                $journalItem = new JournalItem();
                $journalItem->journal = $journal->id;
            }

            if (isset($accounts[$i]['account'])) {
                $journalItem->account = $accounts[$i]['account'];
            }

            $journalItem->description = $accounts[$i]['description'];
            $journalItem->debit = isset($accounts[$i]['debit']) ? $accounts[$i]['debit'] : 0;
            $journalItem->credit = isset($accounts[$i]['credit']) ? $accounts[$i]['credit'] : 0;
            $journalItem->save();

            // Update bank account balance if applicable
            $bankAccounts = BankAccount::where('chart_account_id', '=', $accounts[$i]['account'])->get();
            if (!empty($bankAccounts)) {
                foreach ($bankAccounts as $bankAccount) {
                    $old_balance = $bankAccount->opening_balance;
                    if ($journalItem->debit > 0) {
                        $new_balance = $old_balance - $journalItem->debit;
                    }
                    if ($journalItem->credit > 0) {
                        $new_balance = $old_balance + $journalItem->credit;
                    }
                    if (isset($new_balance)) {
                        $bankAccount->opening_balance = $new_balance;
                        $bankAccount->save();
                    }
                }
            }

            // Add transaction lines for double-entry bookkeeping
            if (isset($accounts[$i]['debit']) && $accounts[$i]['debit'] > 0) {
                $data = [
                    'account_id' => $accounts[$i]['account'],
                    'transaction_type' => 'debit',
                    'transaction_amount' => $accounts[$i]['debit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);

                $account = ChartOfAccount::where('name', 'Accounts Payable')
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();
                $data = [
                    'account_id' => !empty($account) ? $account->id : 0,
                    'transaction_type' => 'credit',
                    'transaction_amount' => $accounts[$i]['debit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);
            } else {
                $data = [
                    'account_id' => $accounts[$i]['account'],
                    'transaction_type' => 'credit',
                    'transaction_amount' => $accounts[$i]['credit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);

                $account = ChartOfAccount::where('name', 'Accounts Receivable')
                    ->where('created_by', Auth::user()->creatorId())
                    ->first();
                $data = [
                    'account_id' => !empty($account) ? $account->id : 0,
                    'transaction_type' => 'debit',
                    'transaction_amount' => $accounts[$i]['credit'],
                    'reference' => 'Journal Entry',
                    'reference_id' => $journal->id,
                    'reference_sub_id' => $journalItem->id,
                    'date' => $journal->date,
                ];
                Utility::addTransactionLines($data);
            }
        }

        return $this->success(['journal' => $journal->load('accounts')], 'Journal entry updated successfully.');
    }

    public function deleteJournalEntry($id)
    {
        $journal = JournalEntry::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$journal) {
            return $this->error('Journal entry not found.', 404);
        }

        // Delete related records
        JournalItem::where('journal', '=', $journal->id)->delete();
        AddTransactionLine::where('reference_id', $journal->id)
            ->where('reference', 'Journal Entry')
            ->delete();
        $journal->delete();

        return $this->success([], 'Journal entry deleted successfully.');
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

            foreach ($accountSubTypes as $accountType) {
                $chartOfAccounts = ChartOfAccount::where('sub_type', $accountType->id)
                    ->where('parent', '=', 0)
                    ->where('created_by', Auth::user()->creatorId())
                    ->get();

                $incomeSubAccounts = ChartOfAccount::where('sub_type', $accountType->id)
                    ->where('parent', '!=', 0)
                    ->where('created_by', Auth::user()->creatorId())
                    ->get();

                $tempData = [
                    'account_name' => $accountType->name,
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
                $temp[$accountType->id] = $tempData;
            }

            $chartAccounts[$type->name] = $temp;
        }

        return $this->success(['chart_accounts' => $chartAccounts], 'Chart accounts retrieved.');
    }

    function journalNumber()
    {
        $latest = JournalEntry::where('created_by', '=', Auth::user()->creatorId())
            ->latest('journal_id')
            ->first();
        if (!$latest) {
            return 1;
        }

        return $latest->journal_id + 1;
    }
}

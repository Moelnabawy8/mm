<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\ChartOfAccountSubType;
use App\Models\ChartOfAccountType;
use App\Models\ChartOfAccountParent;
use App\Models\JournalItem;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChartOfAccountApiController extends Controller
{
    use ApiResponser;

    public function getChartOfAccounts()
    {
        $types = ChartOfAccountType::where('created_by', Auth::user()->creatorId())->get();

        $accounts = ChartOfAccount::whereIn('type', $types->pluck('id'))
            ->where('created_by', Auth::user()->creatorId())
            ->with(['subType', 'parentAccount', 'type'])
            ->get()
            ->groupBy('type');

        $chartAccounts = [];
        foreach ($types as $type) {
            $typeName = $type->name;
            $chartAccounts[$typeName] = $accounts[$type->id] ?? [];
        }

        return $this->success(['chart_accounts' => $chartAccounts, 'types' => $types], 'Chart of Accounts retrieved.');
    }

    public function createChartOfAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'sub_type' => 'required|exists:chart_of_account_sub_types,id',
            'parent' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'is_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if subtype belongs to user's company
        $subType = ChartOfAccountSubType::where('id', $request->sub_type)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$subType) {
            return $this->error('Subtype not found or access denied.', 404);
        }

        // Check if parent account belongs to user's company
        if ($request->has('parent') && $request->parent) {
            $parentAccount = ChartOfAccount::where('id', $request->parent)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if (!$parentAccount) {
                return $this->error('Parent account not found or access denied.', 404);
            }

            // Create or update parent account record
            $existingParentAccount = ChartOfAccountParent::where('name', $parentAccount->name)
                ->where('created_by', Auth::user()->creatorId())
                ->first();

            if ($existingParentAccount) {
                $parentAccountRecord = $existingParentAccount;
            } else {
                $parentAccountRecord = new ChartOfAccountParent();
                $parentAccountRecord->name = $parentAccount->name;
                $parentAccountRecord->sub_type = $request->sub_type;
                $parentAccountRecord->type = $subType->type;
                $parentAccountRecord->account = $request->parent;
                $parentAccountRecord->created_by = Auth::user()->creatorId();
                $parentAccountRecord->save();
            }
        }

        $account = new ChartOfAccount();
        $account->name = $request->name;
        $account->code = $request->code;
        $account->type = $subType->type;
        $account->sub_type = $request->sub_type;
        $account->parent = isset($parentAccountRecord) ? $parentAccountRecord->id : 0;
        $account->description = $request->description;
        $account->is_enabled = $request->has('is_enabled') ? 1 : 0;
        $account->created_by = Auth::user()->creatorId();
        $account->save();

        return $this->success(['account' => $account], 'Account created successfully.');
    }

    public function getChartOfAccount($id)
    {
        $account = ChartOfAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['subType', 'parentAccount', 'type'])
            ->first();

        if (!$account) {
            return $this->error('Account not found.', 404);
        }

        return $this->success(['account' => $account], 'Account detail.');
    }

    public function updateChartOfAccount(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $account = ChartOfAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$account) {
            return $this->error('Account not found.', 404);
        }

        $account->name = $request->name;
        $account->code = $request->code;
        $account->description = $request->description;
        $account->is_enabled = $request->has('is_enabled') ? 1 : 0;
        $account->save();

        return $this->success(['account' => $account], 'Account updated successfully.');
    }

    public function deleteChartOfAccount($id)
    {
        $account = ChartOfAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$account) {
            return $this->error('Account not found.', 404);
        }

        // Check if account has journal items
        $journalItems = JournalItem::where('account', $id)->get();
        if ($journalItems->count() > 0) {
            return $this->error('Cannot delete account with existing transactions.', 400);
        }

        $account->delete();

        return $this->success([], 'Account deleted successfully.');
    }

    public function getAccountTypes()
    {
        $types = ChartOfAccountType::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['types' => $types], 'Account types retrieved.');
    }

    public function getAccountSubTypes($typeId = null)
    {
        $query = ChartOfAccountSubType::where('created_by', Auth::user()->creatorId());

        if ($typeId) {
            $query->where('type', $typeId);
        }

        $subTypes = $query->get();

        // Group by type
        $subTypesByType = $subTypes->groupBy('type')->map(function ($group) {
            return $group->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type
                ];
            });
        });

        return $this->success(['sub_types' => $subTypesByType], 'Account subtypes retrieved.');
    }

    public function getSubTypesByType($typeId)
    {
        $type = ChartOfAccountType::where('id', $typeId)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$type) {
            return $this->error('Account type not found.', 404);
        }

        $subTypes = ChartOfAccountSubType::where('type', $typeId)
            ->where('created_by', Auth::user()->creatorId())
            ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])
            ->get(['id', 'name']);

        return $this->success(['sub_types' => $subTypes], 'Subtypes by type retrieved.');
    }

    public function getParentAccounts($subTypeId = null)
    {
        $query = ChartOfAccount::where('created_by', Auth::user()->creatorId())
            ->where('parent', '!=', 0);

        if ($subTypeId) {
            $query->where('sub_type', $subTypeId);
        }

        $parentAccounts = $query->with(['subType'])->get(['id', 'name', 'sub_type']);

        return $this->success(['parent_accounts' => $parentAccounts], 'Parent accounts retrieved.');
    }

    public function getAccountBalance($id, Request $request)
    {
        $account = ChartOfAccount::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$account) {
            return $this->error('Account not found.', 404);
        }

        // Set date range
        if (!empty($request->start_date) && !empty($request->end_date)) {
            $start = $request->start_date;
            $end = $request->end_date;
        } else {
            $start = date('Y-01-01');
            $end = date('Y-m-d', strtotime('+1 day'));
        }

        // Get journal items for this account
        $journalItems = JournalItem::select('journal_items.*')
            ->leftJoin('journal_entries', 'journal_entries.id', 'journal_items.journal')
            ->where('journal_entries.created_by', Auth::user()->creatorId())
            ->where('journal_items.account', $account->id)
            ->where('journal_entries.date', '>=', $start)
            ->where('journal_entries.date', '<=', $end)
            ->get();

        $debit = $journalItems->sum('debit');
        $credit = $journalItems->sum('credit');
        $balance = $credit - $debit;

        return $this->success([
            'account' => $account,
            'balance' => $balance,
            'debit' => $debit,
            'credit' => $credit,
            'start_date' => $start,
            'end_date' => $end
        ], 'Account balance retrieved.');
    }

    public function getAccountStats()
    {
        $types = ChartOfAccountType::where('created_by', Auth::user()->creatorId())->get();

        $totalAccounts = ChartOfAccount::whereIn('type', $types->pluck('id'))
            ->where('created_by', Auth::user()->creatorId())
            ->count();

        $enabledAccounts = ChartOfAccount::whereIn('type', $types->pluck('id'))
            ->where('created_by', Auth::user()->creatorId())
            ->where('is_enabled', 1)
            ->count();

        // Group by type
        $accountsByType = ChartOfAccount::whereIn('type', $types->pluck('id'))
            ->where('created_by', Auth::user()->creatorId())
            ->with('type')
            ->get()
            ->groupBy('type.name')
            ->map(function ($group) {
                return $group->count();
            });

        // Group by subtype
        $accountsBySubType = ChartOfAccount::where('created_by', Auth::user()->creatorId())
            ->with('subType')
            ->get()
            ->groupBy('subType.name')
            ->map(function ($group) {
                return $group->count();
            });

        return $this->success([
            'total_accounts' => $totalAccounts,
            'enabled_accounts' => $enabledAccounts,
            'disabled_accounts' => $totalAccounts - $enabledAccounts,
            'accounts_by_type' => $accountsByType,
            'accounts_by_subtype' => $accountsBySubType
        ], 'Account statistics retrieved.');
    }

    public function getAccountsForSelect()
    {
        $accounts = ChartOfAccount::where('created_by', Auth::user()->creatorId())
            ->where('is_enabled', 1)
            ->orderBy('code', 'asc')
            ->get(['id', 'name', 'code']);

        $formattedAccounts = $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->code . ' - ' . $account->name
            ];
        });

        return $this->success(['accounts' => $formattedAccounts], 'Accounts for select retrieved.');
    }
}

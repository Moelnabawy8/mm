<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\BankAccount;
use App\Models\ProductServiceCategory;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;

class TransactionApiController extends Controller
{
    use ApiResponser;

    public function getTransactions()
    {
        $transactions = Transaction::where('created_by', Auth::user()->creatorId())->with(['bankAccount'])->orderBy('id', 'desc');

        // Apply filters if provided
        if (request()->has('account') && !empty(request('account'))) {
            $transactions->where('account', request('account'));
        }

        if (request()->has('category') && !empty(request('category'))) {
            $transactions->where('category', request('category'));
        }

        if (request()->has('start_date') && !empty(request('start_date'))) {
            $transactions->whereDate('date', '>=', request('start_date'));
        }

        if (request()->has('end_date') && !empty(request('end_date'))) {
            $transactions->whereDate('date', '<=', request('end_date'));
        }

        $transactions = $transactions->get();

        return $this->success(['transactions' => $transactions], 'Transactions retrieved.');
    }

    public function getTransaction($id)
    {
        $transaction = Transaction::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['bankAccount'])->first();
        if (!$transaction) {
            return $this->error('Transaction not found.', 404);
        }
        return $this->success(['transaction' => $transaction], 'Transaction detail.');
    }

    public function createTransaction(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'category' => 'required',
            'account' => 'required',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $transaction = new Transaction();
        $transaction->amount = $request->amount;
        $transaction->date = $request->date;
        $transaction->category = $request->category;
        $transaction->account = $request->account;
        $transaction->description = $request->description;
        $transaction->created_by = Auth::user()->creatorId();
        $transaction->save();

        return $this->success(['transaction' => $transaction], 'Transaction created successfully.');
    }

    public function updateTransaction(Request $request, $id)
    {
        $validator = \Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'category' => 'required',
            'account' => 'required',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $transaction = Transaction::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$transaction) {
            return $this->error('Transaction not found.', 404);
        }

        $transaction->amount = $request->amount;
        $transaction->date = $request->date;
        $transaction->category = $request->category;
        $transaction->account = $request->account;
        $transaction->description = $request->description;
        $transaction->save();

        return $this->success(['transaction' => $transaction], 'Transaction updated successfully.');
    }

    public function deleteTransaction($id)
    {
        $transaction = Transaction::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$transaction) {
            return $this->error('Transaction not found.', 404);
        }
        $transaction->delete();
        return $this->success([], 'Transaction deleted successfully.');
    }

    public function getBankAccounts()
    {
        $accounts = BankAccount::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['accounts' => $accounts], 'Bank accounts retrieved.');
    }

    public function getCategories()
    {
        $categories = ProductServiceCategory::where('created_by', Auth::user()->creatorId())
            ->whereIn('type', [1, 2])
            ->get();
        return $this->success(['categories' => $categories], 'Categories retrieved.');
    }
}

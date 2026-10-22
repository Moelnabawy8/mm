<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Revenue;
use App\Models\Customer;
use App\Models\BankAccount;
use App\Models\ProductServiceCategory;
use App\Models\Transaction;
use App\Models\Utility;
use App\Models\ChartOfAccount;
use App\Models\AddTransactionLine;
use App\Models\InvoicePayment;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RevenueApiController extends Controller
{
    use ApiResponser;

    public function getRevenues()
    {
        $query = Revenue::where('created_by', Auth::user()->creatorId());

        // Apply filters if provided
        if (request()->has('date') && !empty(request('date'))) {
            if (count(explode('to', request('date'))) > 1) {
                $date_range = explode(' to ', request('date'));
                $query->whereBetween('date', $date_range);
            } else {
                $date_range = [request('date'), request('date')];
                $query->whereBetween('date', $date_range);
            }
        }

        if (request()->has('customer') && !empty(request('customer'))) {
            $query->where('customer_id', '=', request('customer'));
        }

        if (request()->has('account') && !empty(request('account'))) {
            $query->where('account_id', '=', request('account'));
        }

        if (request()->has('category') && !empty(request('category'))) {
            $query->where('category_id', '=', request('category'));
        }

        if (request()->has('payment') && !empty(request('payment'))) {
            $query->where('payment_method', '=', request('payment'));
        }

        $revenues = $query->with(['bankAccount', 'customer', 'category'])->get();

        return $this->success(['revenues' => $revenues], 'Revenues retrieved.');
    }

    public function createRevenue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'account_id' => 'required|exists:bank_accounts,id',
            'category_id' => 'required|exists:product_service_categories,id',
            'customer_id' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if bank account is connected with chart of account
        $bankAccount = BankAccount::find($request->account_id);
        if ($bankAccount && $bankAccount->chart_account_id == 0) {
            return $this->error('This bank account is not connected with chart of account, so please connect first.', 400);
        }

        // Check if customer belongs to user's company
        $customer = Customer::where('id', $request->customer_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$customer) {
            return $this->error('Customer not found or access denied.', 404);
        }

        // Check if category belongs to user's company and is income type
        $category = ProductServiceCategory::where('id', $request->category_id)
            ->where('created_by', Auth::user()->creatorId())
            ->where('type', 'income')
            ->first();

        if (!$category) {
            return $this->error('Category not found or access denied.', 404);
        }

        $revenue = new Revenue();
        $revenue->date = $request->date;
        $revenue->amount = $request->amount;
        $revenue->account_id = $request->account_id;
        $revenue->customer_id = $request->customer_id;
        $revenue->category_id = $request->category_id;
        $revenue->payment_method = 0;
        $revenue->reference = $request->reference;
        $revenue->description = $request->description;
        $revenue->created_by = Auth::user()->creatorId();

        // Handle file upload
        if ($request->hasFile('add_receipt')) {
            $image_size = $request->file('add_receipt')->getSize();
            $result = Utility::updateStorageLimit(Auth::user()->creatorId(), $image_size);

            if ($result == 1) {
                $fileName = time() . "_" . $request->add_receipt->getClientOriginalName();
                $revenue->add_receipt = $fileName;
                $dir = 'uploads/revenue';
                $path = Utility::upload_file($request, 'add_receipt', $fileName, $dir, []);
                if ($path['flag'] == 0) {
                    return $this->error($path['msg'], 400);
                }
            } else {
                return $this->error('Storage limit exceeded.', 400);
            }
        }

        $revenue->save();

        // Add transaction
        $revenue->payment_id = $revenue->id;
        $revenue->type = 'Revenue';
        $revenue->category = $category->name;
        $revenue->user_id = $revenue->customer_id;
        $revenue->user_type = 'Customer';
        $revenue->account = $request->account_id;
        Transaction::addTransaction($revenue);

        // Update customer balance
        Utility::updateUserBalance('customer', $customer->id, $revenue->amount, 'debit');

        // Update bank account balance
        Utility::bankAccountBalance($request->account_id, $revenue->amount, 'credit');

        // Add transaction lines
        $accountId = BankAccount::find($revenue->account_id);
        $data = [
            'account_id' => $accountId->chart_account_id,
            'transaction_type' => 'debit',
            'transaction_amount' => $revenue->amount,
            'reference' => 'Revenue',
            'reference_id' => $revenue->id,
            'reference_sub_id' => 0,
            'date' => $revenue->date,
        ];
        Utility::addTransactionLines($data);

        $account = ChartOfAccount::where('name', 'Accounts Receivable')
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        $data = [
            'account_id' => !empty($account) ? $account->id : 0,
            'transaction_type' => 'credit',
            'transaction_amount' => $revenue->amount,
            'reference' => 'Revenue',
            'reference_id' => $revenue->id,
            'reference_sub_id' => 0,
            'date' => $revenue->date,
        ];
        Utility::addTransactionLines($data);

        return $this->success(['revenue' => $revenue], 'Revenue created successfully.');
    }

    public function getRevenue($id)
    {
        $revenue = Revenue::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['bankAccount', 'customer', 'category'])
            ->first();

        if (!$revenue) {
            return $this->error('Revenue not found.', 404);
        }

        return $this->success(['revenue' => $revenue], 'Revenue detail.');
    }

    public function updateRevenue(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'account_id' => 'required|exists:bank_accounts,id',
            'category_id' => 'required|exists:product_service_categories,id',
            'customer_id' => 'required|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $revenue = Revenue::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$revenue) {
            return $this->error('Revenue not found.', 404);
        }

        // Check if bank account is connected with chart of account
        $bankAccount = BankAccount::find($request->account_id);
        if ($bankAccount && $bankAccount->chart_account_id == 0) {
            return $this->error('This bank account is not connected with chart of account, so please connect first.', 400);
        }

        // Update balances for old amounts
        $customer = Customer::where('id', $revenue->customer_id)->first();
        if (!empty($customer)) {
            Utility::updateUserBalance('customer', $customer->id, $revenue->amount, 'credit'); // For old amount remove
            Utility::updateUserBalance('customer', $customer->id, $request->amount, 'debit');
        }
        Utility::bankAccountBalance($request->account_id, $revenue->amount, 'debit'); // For old amount remove
        Utility::bankAccountBalance($request->account_id, $request->amount, 'credit');

        $revenue->date = $request->date;
        $revenue->amount = $request->amount;
        $revenue->account_id = $request->account_id;
        $revenue->customer_id = $request->customer_id;
        $revenue->category_id = $request->category_id;
        $revenue->payment_method = 0;
        $revenue->reference = $request->reference;
        $revenue->description = $request->description;

        // Handle file upload
        if ($request->hasFile('add_receipt')) {
            $file_path = '/uploads/revenue/' . $revenue->add_receipt;
            $image_size = $request->file('add_receipt')->getSize();
            $result = Utility::updateStorageLimit(Auth::user()->creatorId(), $image_size);

            if ($result == 1) {
                Utility::changeStorageLimit(Auth::user()->creatorId(), $file_path);
                $path = storage_path('uploads/revenue/' . $revenue->add_receipt);

                if (file_exists($path)) {
                    \File::delete($path);
                }
                $fileName = time() . "_" . $request->add_receipt->getClientOriginalName();
                $revenue->add_receipt = $fileName;
                $dir = 'uploads/revenue';
                $path = Utility::upload_file($request, 'add_receipt', $fileName, $dir, []);
                if ($path['flag'] == 0) {
                    return $this->error($path['msg'], 400);
                }
            } else {
                return $this->error('Storage limit exceeded.', 400);
            }
        }

        $revenue->save();

        // Update transaction
        $category = ProductServiceCategory::where('id', $request->category_id)->first();
        $revenue->category = $category->name;
        $revenue->payment_id = $revenue->id;
        $revenue->type = 'Revenue';
        $revenue->account = $request->account_id;
        Transaction::editTransaction($revenue);

        // Update transaction lines
        $accountId = BankAccount::find($revenue->account_id);
        $data = [
            'account_id' => $accountId->chart_account_id,
            'transaction_type' => 'debit',
            'transaction_amount' => $revenue->amount,
            'reference' => 'Revenue',
            'reference_id' => $revenue->id,
            'reference_sub_id' => 0,
            'date' => $revenue->date,
        ];
        Utility::addTransactionLines($data, 'edit', 'notes');

        $account = ChartOfAccount::where('name', 'Accounts Receivable')
            ->where('created_by', Auth::user()->creatorId())
            ->first();
        $data = [
            'account_id' => !empty($account) ? $account->id : 0,
            'transaction_type' => 'credit',
            'transaction_amount' => $revenue->amount,
            'reference' => 'Revenue',
            'reference_id' => $revenue->id,
            'reference_sub_id' => 0,
            'date' => $revenue->date,
        ];
        Utility::addTransactionLines($data, 'edit');

        return $this->success(['revenue' => $revenue], 'Revenue updated successfully.');
    }

    public function deleteRevenue($id)
    {
        $revenue = Revenue::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$revenue) {
            return $this->error('Revenue not found.', 404);
        }

        // Handle file deletion
        if (!empty($revenue->add_receipt)) {
            $file_path = '/uploads/revenue/' . $revenue->add_receipt;
            Utility::changeStorageLimit(Auth::user()->creatorId(), $file_path);
        }

        // Delete transaction lines
        AddTransactionLine::where('reference_id', $revenue->id)->where('reference', 'Revenue')->delete();
        $revenue->delete();

        return $this->success([], 'Revenue deleted successfully.');
    }

    public function getCustomers()
    {
        $customers = Customer::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['customers' => $customers], 'Customers retrieved.');
    }

    public function getAccounts()
    {
        $accounts = BankAccount::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['accounts' => $accounts], 'Accounts retrieved.');
    }

    public function getCategories()
    {
        $categories = ProductServiceCategory::where('created_by', Auth::user()->creatorId())
            ->where('type', 'income')
            ->get();
        return $this->success(['categories' => $categories], 'Categories retrieved.');
    }
}

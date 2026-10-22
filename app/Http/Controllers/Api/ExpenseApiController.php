<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\BillProduct;
use App\Models\BillPayment;
use App\Models\Vendor;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\ProductService;
use App\Models\ProductServiceCategory;
use App\Models\BankAccount;
use App\Models\Utility;
use App\Models\StockReport;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ExpenseApiController extends Controller
{
    use ApiResponser;

    public function getExpenses()
    {
        $expenses = Bill::where('type', 'Expense')->where('created_by', Auth::user()->creatorId())->with(['category', 'vender'])->get();
        return $this->success(['expenses' => $expenses], 'Expenses retrieved.');
    }

    public function createExpense(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required',
            'type' => 'required|in:customer,vender,employee',
            'account_id' => 'required|exists:bank_accounts,id',
            'totalAmount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $bankAccount = BankAccount::find($request->account_id);
        if ($bankAccount && $bankAccount->chart_account_id == 0) {
            return $this->error('This bank account is not connected with chart of account, so please connect first.', 400);
        }

        $expense = new Bill();
        $expense->bill_id = $this->expenseNumber();

        // Set vendor_id based on type
        if ($request->type == 'employee') {
            $expense->vender_id = $request->employee_id;
        } elseif ($request->type == 'customer') {
            $expense->vender_id = $request->customer_id;
        } else {
            $expense->vender_id = $request->vender_id;
        }

        $expense->bill_date = $request->payment_date;
        $expense->status = 4;
        $expense->type = 'Expense';
        $expense->user_type = $request->type;
        $expense->due_date = $request->payment_date;
        $expense->category_id = $request->category_id ?? 0;
        $expense->order_number = 0;
        $expense->created_by = Auth::user()->creatorId();
        $expense->save();

        if ($request->has('items') && is_array($request->items)) {
            $total_amount = 0;
            foreach ($request->items as $item) {
                if (!empty($item['item'])) {
                    $expenseProduct = new BillProduct();
                    $expenseProduct->bill_id = $expense->id;
                    $expenseProduct->product_id = $item['item'];
                    $expenseProduct->quantity = $item['quantity'];
                    $expenseProduct->tax = $item['tax'] ?? 0;
                    $expenseProduct->discount = $item['discount'] ?? 0;
                    $expenseProduct->price = $item['price'];
                    $expenseProduct->description = $item['description'] ?? '';
                    $expenseProduct->save();

                    // Inventory management
                    Utility::total_quantity('plus', $expenseProduct->quantity, $expenseProduct->product_id);

                    // Product Stock Report
                    $type = 'bill';
                    $type_id = $expense->id;
                    $description = $expenseProduct->quantity . '  ' . __('quantity purchase in bill') . ' ' . Auth::user()->expenseNumberFormat($expense->bill_id);
                    Utility::addProductStock($expenseProduct->product_id, $expenseProduct->quantity, $type, $description, $type_id);

                    $total_amount += ($expenseProduct->quantity * $expenseProduct->price);
                }
            }
        }

        $expensePayment = new BillPayment();
        $expensePayment->bill_id = $expense->id;
        $expensePayment->date = $request->payment_date;
        $expensePayment->amount = $request->totalAmount;
        $expensePayment->account_id = $request->account_id;
        $expensePayment->payment_method = 0;
        $expensePayment->reference = 'NULL';
        $expensePayment->description = 'NULL';
        $expensePayment->add_receipt = 'NULL';
        $expensePayment->save();

        if ($request->type == 'customer') {
            Utility::updateUserBalance('customer', $expense->vender_id, $request->totalAmount, 'credit');
        }

        return $this->success(['expense' => $expense->load('products', 'payment')], 'Expense created successfully.');
    }

    public function getExpense($id)
    {
        $expense = Bill::where('id', $id)->where('type', 'Expense')->where('created_by', Auth::user()->creatorId())->with(['category', 'vender', 'products', 'payment'])->first();
        if (!$expense) {
            return $this->error('Expense not found.', 404);
        }
        return $this->success(['expense' => $expense], 'Expense detail.');
    }

    public function updateExpense(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required',
            'type' => 'required|in:customer,vender,employee',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $expense = Bill::where('id', $id)->where('type', 'Expense')->where('created_by', Auth::user()->creatorId())->first();
        if (!$expense) {
            return $this->error('Expense not found.', 404);
        }

        // Update vendor_id based on type
        if ($request->type == 'employee') {
            $expense->vender_id = $request->employee_id;
        } elseif ($request->type == 'customer') {
            $expense->vender_id = $request->customer_id;
        } else {
            $expense->vender_id = $request->vender_id;
        }

        $expense->bill_date = $request->payment_date;
        $expense->user_type = $request->type;
        $expense->due_date = $request->payment_date;
        $expense->category_id = $request->category_id ?? 0;
        $expense->save();

        return $this->success(['expense' => $expense], 'Expense updated successfully.');
    }

    public function deleteExpense($id)
    {
        $expense = Bill::where('id', $id)->where('type', 'Expense')->where('created_by', Auth::user()->creatorId())->first();
        if (!$expense) {
            return $this->error('Expense not found.', 404);
        }

        BillProduct::where('bill_id', $expense->id)->delete();
        BillPayment::where('bill_id', $expense->id)->delete();
        $expense->delete();

        return $this->success([], 'Expense deleted successfully.');
    }

    public function getExpenseCategories()
    {
        $categories = ProductServiceCategory::where('created_by', Auth::user()->creatorId())
            ->whereNotIn('type', ['product & service', 'income'])
            ->get();
        return $this->success(['categories' => $categories], 'Expense categories retrieved.');
    }

    public function getVendors()
    {
        $vendors = Vendor::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['vendors' => $vendors], 'Vendors retrieved.');
    }

    public function getCustomers()
    {
        $customers = Customer::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['customers' => $customers], 'Customers retrieved.');
    }

    public function getEmployees()
    {
        $employees = Employee::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['employees' => $employees], 'Employees retrieved.');
    }

    public function getProducts()
    {
        $products = ProductService::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['products' => $products], 'Products retrieved.');
    }

    public function getBankAccounts()
    {
        $accounts = BankAccount::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['accounts' => $accounts], 'Bank accounts retrieved.');
    }

    function expenseNumber()
    {
        $latest = Bill::where('created_by', '=', Auth::user()->creatorId())->where('type', '=', 'Expense')->latest('bill_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->bill_id + 1;
    }
}

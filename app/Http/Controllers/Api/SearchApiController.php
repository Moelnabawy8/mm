<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Vender;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProductService;
use App\Models\Invoice;
use App\Models\Bill;
use App\Models\Deal;
use App\Models\Task;
use App\Models\Lead;
use App\Models\Contract;
use App\Models\Purchase;
use App\Models\Revenue;
use App\Models\Payment;
use App\Models\BankAccount;
use App\Models\Warehouse;
use App\Models\Department;
use App\Models\Designation;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SearchApiController extends Controller
{
    use ApiResponser;

    public function globalSearch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'type' => 'nullable|string|in:all,customer,vendor,employee,project,product,invoice,bill,deal,task,lead,contract,purchase,revenue,payment,bank_account,warehouse,department,designation',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = $request->query;
        $type = $request->type ?? 'all';
        $creatorId = Auth::user()->creatorId();
        $results = [];

        if ($type === 'all' || $type === 'customer') {
            $customers = Customer::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('contact', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'email', 'contact']);

            if ($customers->isNotEmpty()) {
                $results['customers'] = $customers;
            }
        }

        if ($type === 'all' || $type === 'vendor') {
            $vendors = Vender::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('contact', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'email', 'contact']);

            if ($vendors->isNotEmpty()) {
                $results['vendors'] = $vendors;
            }
        }

        if ($type === 'all' || $type === 'employee') {
            $employees = Employee::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('employee_id', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'email', 'phone', 'employee_id']);

            if ($employees->isNotEmpty()) {
                $results['employees'] = $employees;
            }
        }

        if ($type === 'all' || $type === 'project') {
            $projects = Project::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('project_name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'project_name', 'status', 'start_date', 'end_date']);

            if ($projects->isNotEmpty()) {
                $results['projects'] = $projects;
            }
        }

        if ($type === 'all' || $type === 'product') {
            $products = ProductService::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'sku', 'sale_price', 'type']);

            if ($products->isNotEmpty()) {
                $results['products'] = $products;
            }
        }

        if ($type === 'all' || $type === 'invoice') {
            $invoices = Invoice::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('invoice_id', 'like', "%{$query}%")
                        ->orWhere('client_name', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'invoice_id', 'client_name', 'status', 'total_amount']);

            if ($invoices->isNotEmpty()) {
                $results['invoices'] = $invoices;
            }
        }

        if ($type === 'all' || $type === 'bill') {
            $bills = Bill::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('bill_id', 'like', "%{$query}%")
                        ->orWhere('vender_name', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'bill_id', 'vender_name', 'status', 'total_amount']);

            if ($bills->isNotEmpty()) {
                $results['bills'] = $bills;
            }
        }

        if ($type === 'all' || $type === 'deal') {
            $deals = Deal::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('deal_source', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'deal_source', 'status', 'expected_close_date']);

            if ($deals->isNotEmpty()) {
                $results['deals'] = $deals;
            }
        }

        if ($type === 'all' || $type === 'task') {
            $tasks = \App\Models\ProjectTask::whereHas('project', function ($q) use ($creatorId) {
                $q->where('created_by', $creatorId);
            })
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%")
                        ->orWhere('priority', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'priority', 'status', 'due_date']);

            if ($tasks->isNotEmpty()) {
                $results['tasks'] = $tasks;
            }
        }

        if ($type === 'all' || $type === 'lead') {
            $leads = Lead::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'email', 'phone', 'status']);

            if ($leads->isNotEmpty()) {
                $results['leads'] = $leads;
            }
        }

        if ($type === 'all' || $type === 'contract') {
            $contracts = Contract::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('subject', 'like', "%{$query}%")
                        ->orWhere('contract_type', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'subject', 'contract_type', 'status', 'start_date', 'end_date']);

            if ($contracts->isNotEmpty()) {
                $results['contracts'] = $contracts;
            }
        }

        if ($type === 'all' || $type === 'purchase') {
            $purchases = Purchase::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('purchase_id', 'like', "%{$query}%")
                        ->orWhere('status', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'purchase_id', 'status', 'purchase_date']);

            if ($purchases->isNotEmpty()) {
                $results['purchases'] = $purchases;
            }
        }

        if ($type === 'all' || $type === 'revenue') {
            $revenues = Revenue::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('reference', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'reference', 'amount', 'date']);

            if ($revenues->isNotEmpty()) {
                $results['revenues'] = $revenues;
            }
        }

        if ($type === 'all' || $type === 'bank_account') {
            $bankAccounts = BankAccount::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('holder_name', 'like', "%{$query}%")
                        ->orWhere('bank_name', 'like', "%{$query}%")
                        ->orWhere('account_number', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'holder_name', 'bank_name', 'account_number']);

            if ($bankAccounts->isNotEmpty()) {
                $results['bank_accounts'] = $bankAccounts;
            }
        }

        if ($type === 'all' || $type === 'warehouse') {
            $warehouses = Warehouse::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('address', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name', 'address']);

            if ($warehouses->isNotEmpty()) {
                $results['warehouses'] = $warehouses;
            }
        }

        if ($type === 'all' || $type === 'department') {
            $departments = Department::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name']);

            if ($departments->isNotEmpty()) {
                $results['departments'] = $departments;
            }
        }

        if ($type === 'all' || $type === 'designation') {
            $designations = Designation::where('created_by', $creatorId)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get(['id', 'name']);

            if ($designations->isNotEmpty()) {
                $results['designations'] = $designations;
            }
        }

        return $this->success([
            'results' => $results,
            'query' => $query,
            'total_results' => array_sum(array_map(function ($typeResults) {
                return $typeResults->count();
            }, $results))
        ], 'Search results retrieved.');
    }

    public function quickSearch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'entity' => 'required|string|in:customer,vendor,employee,project,product',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $query = $request->query;
        $entity = $request->entity;
        $creatorId = Auth::user()->creatorId();
        $results = [];

        switch ($entity) {
            case 'customer':
                $results = Customer::where('created_by', $creatorId)
                    ->where('name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get(['id', 'name', 'email']);
                break;

            case 'vendor':
                $results = Vender::where('created_by', $creatorId)
                    ->where('name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get(['id', 'name', 'email']);
                break;

            case 'employee':
                $results = Employee::where('created_by', $creatorId)
                    ->where('name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get(['id', 'name', 'email']);
                break;

            case 'project':
                $results = Project::where('created_by', $creatorId)
                    ->where('project_name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get(['id', 'project_name', 'status']);
                break;

            case 'product':
                $results = ProductService::where('created_by', $creatorId)
                    ->where('name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get(['id', 'name', 'sku', 'sale_price']);
                break;
        }

        return $this->success([
            'results' => $results,
            'entity' => $entity,
            'query' => $query
        ], 'Quick search results retrieved.');
    }
}

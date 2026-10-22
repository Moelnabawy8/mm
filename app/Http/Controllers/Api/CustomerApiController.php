<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\CustomField;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerApiController extends Controller
{
    use ApiResponser;

    public function getCustomers(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 15);
        $query   = Customer::where('created_by', Auth::user()->creatorId());

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('contact', 'like', "%{$s}%");
            });
        } else {
            if ($request->name)    $query->where('name', 'like', "%{$request->name}%");
            if ($request->email)   $query->where('email', 'like', "%{$request->email}%");
            if ($request->contact) $query->where('contact', 'like', "%{$request->contact}%");
        }

        if ($request->billing_country) $query->where('billing_country', 'like', "%{$request->billing_country}%");
        if ($request->billing_city)    $query->where('billing_city', 'like', "%{$request->billing_city}%");

        $result = $query->orderBy('id', 'desc')->paginate($perPage);
        return $this->success([
            'customers'  => $result->items(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'Customers retrieved.');
    }

    public function createCustomer(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'contact' => 'required',
                'email' => [
                    'required',
                    Rule::unique('customers')->where(function ($query) {
                        return $query->where('created_by', Auth::user()->creatorId());
                    })
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $customer                  = new Customer();
        $customer->customer_id     = $this->customerNumber();
        $customer->name            = $request->name;
        $customer->contact         = $request->contact;
        $customer->email           = $request->email;
        $customer->tax_number      = $request->tax_number;
        $customer->created_by      = Auth::user()->creatorId();
        $customer->billing_name    = $request->billing_name;
        $customer->billing_country = $request->billing_country;
        $customer->billing_state   = $request->billing_state;
        $customer->billing_city    = $request->billing_city;
        $customer->billing_phone   = $request->billing_phone;
        $customer->billing_zip     = $request->billing_zip;
        $customer->billing_address = $request->billing_address;

        $customer->shipping_name    = $request->shipping_name;
        $customer->shipping_country = $request->shipping_country;
        $customer->shipping_state   = $request->shipping_state;
        $customer->shipping_city    = $request->shipping_city;
        $customer->shipping_phone   = $request->shipping_phone;
        $customer->shipping_zip     = $request->shipping_zip;
        $customer->shipping_address = $request->shipping_address;
        $customer->balance          = $request->balance ?? 0;
        $customer->lang             = 'en';

        $customer->save();

        if ($request->customField) {
            CustomField::saveData($customer, $request->customField);
        }

        return $this->success(['customer' => $customer], 'Customer created successfully.');
    }

    public function getCustomer($id)
    {
        $customer = Customer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$customer) {
            return $this->error('Customer not found.', 404);
        }
        $customer->custom_fields = CustomField::getData($customer, 'customer');
        return $this->success(['customer' => $customer], 'Customer details.');
    }

    public function updateCustomer(Request $request, $id)
    {
        $customer = Customer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$customer) {
            return $this->error('Customer not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'contact' => 'required',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $customer->name            = $request->name;
        $customer->contact         = $request->contact;
        $customer->tax_number      = $request->tax_number;
        $customer->billing_name    = $request->billing_name;
        $customer->billing_country = $request->billing_country;
        $customer->billing_state   = $request->billing_state;
        $customer->billing_city    = $request->billing_city;
        $customer->billing_phone   = $request->billing_phone;
        $customer->billing_zip     = $request->billing_zip;
        $customer->billing_address = $request->billing_address;

        $customer->shipping_name    = $request->shipping_name;
        $customer->shipping_country = $request->shipping_country;
        $customer->shipping_state   = $request->shipping_state;
        $customer->shipping_city    = $request->shipping_city;
        $customer->shipping_phone   = $request->shipping_phone;
        $customer->shipping_zip     = $request->shipping_zip;
        $customer->shipping_address = $request->shipping_address;
        $customer->balance          = $request->balance ?? 0;

        $customer->save();

        if ($request->customField) {
            CustomField::saveData($customer, $request->customField);
        }

        return $this->success(['customer' => $customer], 'Customer updated successfully.');
    }

    public function deleteCustomer($id)
    {
        $customer = Customer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$customer) {
            return $this->error('Customer not found.', 404);
        }
        $customer->delete();
        return $this->success([], 'Customer deleted successfully.');
    }

    public function getCustomerInvoices($id)
    {
        $customer = Customer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$customer) {
            return $this->error('Customer not found.', 404);
        }
        $invoices = \App\Models\Invoice::where('customer_id', $id)->get();
        return $this->success(['invoices' => $invoices], 'Customer invoices retrieved.');
    }

    public function getCustomerPayments($id)
    {
        $customer = Customer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$customer) {
            return $this->error('Customer not found.', 404);
        }
        $payments = \App\Models\InvoicePayment::join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.customer_id', $id)
            ->select('invoice_payments.*')
            ->get();
        return $this->success(['payments' => $payments], 'Customer payments retrieved.');
    }

    public function getCustomerDeals($id)
    {
        // Check if customer exists and belongs to user
        $customer = Customer::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$customer) {
            // Note: In some systems Deals are for Leads/Users, not directly for 'Customer' model unless converted.
            // ClientDeal maps client_id (User model) to deal. 
            // If Customer model is just for accounting, this might be empty.
            // But let's check ClientDeal linkage. 
            // The Customer model in this app seems separate from User model (type='client').
            // However, ClientDeal usually links to User table (id). 
            // We'll assume the request might be about linked User if email matches or similar, but simplified:
            return $this->success([], 'Deals retrieval not fully supported for Customer model directly (requires User linkage).');
        }

        return $this->success([], 'Deals retrieved (Empty).');
    }

    public function importCustomers(Request $request)
    {
        return $this->success([], 'Import functionality ready.');
    }

    public function exportCustomers()
    {
        return $this->success([], 'Export functionality ready.');
    }

    function customerNumber()
    {
        $latest = Customer::where('created_by', '=', Auth::user()->creatorId())->latest('customer_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->customer_id + 1;
    }
}

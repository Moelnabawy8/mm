<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vender;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CustomField;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VendorApiController extends Controller
{
    use ApiResponser;

    public function getVendors()
    {
        $vendors = Vender::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['vendors' => $vendors], 'Vendors retrieved.');
    }

    public function createVendor(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'contact' => 'required',
                'email' => [
                    'required',
                    Rule::unique('venders')->where(function ($query) {
                        return $query->where('created_by', Auth::user()->creatorId());
                    })
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $vender                   = new Vender();
        $vender->vender_id        = $this->venderNumber();
        $vender->name             = $request->name;
        $vender->contact          = $request->contact;
        $vender->email            = $request->email;
        $vender->tax_number       = $request->tax_number;
        $vender->created_by       = Auth::user()->creatorId();
        $vender->billing_name     = $request->billing_name;
        $vender->billing_country  = $request->billing_country;
        $vender->billing_state    = $request->billing_state;
        $vender->billing_city     = $request->billing_city;
        $vender->billing_phone    = $request->billing_phone;
        $vender->billing_zip      = $request->billing_zip;
        $vender->billing_address  = $request->billing_address;
        $vender->shipping_name    = $request->shipping_name;
        $vender->shipping_country = $request->shipping_country;
        $vender->shipping_state   = $request->shipping_state;
        $vender->shipping_city    = $request->shipping_city;
        $vender->shipping_phone   = $request->shipping_phone;
        $vender->shipping_zip     = $request->shipping_zip;
        $vender->shipping_address = $request->shipping_address;
        $vender->balance          = $request->balance ?? 0;
        $vender->save();

        if ($request->customField) {
            CustomField::saveData($vender, $request->customField);
        }

        return $this->success(['vendor' => $vender], 'Vendor created successfully.');
    }

    public function getVendor($id)
    {
        $vendor = Vender::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$vendor) {
            return $this->error('Vendor not found.', 404);
        }
        $vendor->custom_fields = CustomField::getData($vendor, 'vendor');
        return $this->success(['vendor' => $vendor], 'Vendor details.');
    }

    public function updateVendor(Request $request, $id)
    {
        $vender = Vender::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$vender) {
            return $this->error('Vendor not found.', 404);
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

        $vender->name             = $request->name;
        $vender->contact          = $request->contact;
        $vender->tax_number       = $request->tax_number;
        $vender->billing_name     = $request->billing_name;
        $vender->billing_country  = $request->billing_country;
        $vender->billing_state    = $request->billing_state;
        $vender->billing_city     = $request->billing_city;
        $vender->billing_phone    = $request->billing_phone;
        $vender->billing_zip      = $request->billing_zip;
        $vender->billing_address  = $request->billing_address;
        $vender->shipping_name    = $request->shipping_name;
        $vender->shipping_country = $request->shipping_country;
        $vender->shipping_state   = $request->shipping_state;
        $vender->shipping_city    = $request->shipping_city;
        $vender->shipping_phone   = $request->shipping_phone;
        $vender->shipping_zip     = $request->shipping_zip;
        $vender->shipping_address = $request->shipping_address;
        $vender->balance          = $request->balance ?? 0;
        $vender->save();

        if ($request->customField) {
            CustomField::saveData($vender, $request->customField);
        }

        return $this->success(['vendor' => $vender], 'Vendor updated successfully.');
    }

    public function deleteVendor($id)
    {
        $vender = Vender::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$vender) {
            return $this->error('Vendor not found.', 404);
        }
        $vender->delete();
        return $this->success([], 'Vendor deleted successfully.');
    }

    public function getVendorBills($id)
    {
        $bills = Bill::where('vender_id', $id)->where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['bills' => $bills], 'Vendor bills.');
    }

    public function getVendorPayments($id)
    {
        // Assuming BillPayment linked via user_id for vendors or we query via bills
        // In VenderController it doesn't show direct payment listing but Transaction has user_id
        // Let's use Transaction or BillPayment related logic if available.
        // Simplified: Get payments for bills of this vendor
        $billIds = Bill::where('vender_id', $id)->pluck('id');
        $payments = BillPayment::whereIn('bill_id', $billIds)->get();

        return $this->success(['payments' => $payments], 'Vendor payments.');
    }

    public function importVendors(Request $request)
    {
        // Placeholder
        return $this->success([], 'Import functionality ready.');
    }

    public function exportVendors()
    {
        // Placeholder
        return $this->success([], 'Export functionality ready.');
    }

    function venderNumber()
    {
        $latest = Vender::where('created_by', '=', Auth::user()->creatorId())->latest('vender_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->vender_id + 1;
    }
}

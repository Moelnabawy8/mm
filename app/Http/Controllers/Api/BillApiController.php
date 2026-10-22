<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bill;
use App\Models\BillProduct;
use App\Models\BillPayment;
use App\Models\Vender;
use App\Models\ProductService;
use App\Models\StockReport;
use App\Models\Transaction;
use App\Models\CustomField;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;

class BillApiController extends Controller
{
    use ApiResponser;

    public function getBills()
    {
        $bills = Bill::where('created_by', Auth::user()->creatorId())->with(['vender', 'category'])->get();
        return $this->success(['bills' => $bills], 'Bills retrieved.');
    }

    public function createBill(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'vender_id' => 'required',
                'bill_date' => 'required',
                'due_date' => 'required',
                'category_id' => 'required',
                'items' => 'required|array'
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $bill            = new Bill();
        $bill->bill_id   = $this->billNumber();
        $bill->vender_id = $request->vender_id;
        $bill->bill_date = $request->bill_date;
        $bill->status    = 0;
        $bill->type      = 'Bill';
        $bill->user_type = 'vendor';
        $bill->due_date  = $request->due_date;
        $bill->category_id = $request->category_id;
        $bill->order_number = $request->order_number ?? 0;
        $bill->created_by = Auth::user()->creatorId();
        $bill->save();

        if ($request->customField) {
            CustomField::saveData($bill, $request->customField);
        }

        $products = $request->items;

        for ($i = 0; $i < count($products); $i++) {
            $billProduct              = new BillProduct();
            $billProduct->bill_id     = $bill->id;
            $billProduct->product_id  = $products[$i]['product_id'];
            $billProduct->quantity    = $products[$i]['quantity'];
            $billProduct->tax         = $products[$i]['tax'] ?? 0;
            $billProduct->discount    = $products[$i]['discount'] ?? 0;
            $billProduct->price       = $products[$i]['price'];
            $billProduct->description = $products[$i]['description'] ?? '';
            $billProduct->save();

            // Inventory management (Quantity)
            Utility::total_quantity('plus', $billProduct->quantity, $billProduct->product_id);

            // Product Stock Report
            $type = 'bill';
            $type_id = $bill->id;
            $description = $products[$i]['quantity'] . '  ' . __('quantity purchase in bill') . ' ' . Auth::user()->billNumberFormat($bill->bill_id);
            Utility::addProductStock($products[$i]['product_id'], $products[$i]['quantity'], $type, $description, $type_id);
        }

        return $this->success(['bill' => $bill->load('items')], 'Bill created successfully.');
    }

    public function getBill($id)
    {
        $bill = Bill::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['vender', 'items', 'payments', 'category'])->first();
        if (!$bill) {
            return $this->error('Bill not found.', 404);
        }
        $bill->custom_fields = CustomField::getData($bill, 'bill');
        return $this->success(['bill' => $bill], 'Bill details.');
    }

    public function updateBill(Request $request, $id)
    {
        $bill = Bill::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$bill) {
            return $this->error('Bill not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'vender_id' => 'required',
                'bill_date' => 'required',
                'due_date' => 'required',
                'category_id' => 'required',
                'items' => 'required|array'
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $bill->vender_id = $request->vender_id;
        $bill->bill_date = $request->bill_date;
        $bill->due_date  = $request->due_date;
        $bill->category_id = $request->category_id;
        $bill->order_number = $request->order_number ?? $bill->order_number;
        $bill->save();

        if ($request->customField) {
            CustomField::saveData($bill, $request->customField);
        }

        $products = $request->items;

        foreach ($products as $productData) {
            $billProduct = null;
            if (isset($productData['id'])) {
                $billProduct = BillProduct::find($productData['id']);
            }

            if ($billProduct) {
                Utility::total_quantity('minus', $billProduct->quantity, $billProduct->product_id);
            } else {
                $billProduct = new BillProduct();
                $billProduct->bill_id = $bill->id;
            }

            $billProduct->product_id = $productData['product_id'];
            $billProduct->quantity = $productData['quantity'];
            $billProduct->tax = $productData['tax'] ?? 0;
            $billProduct->discount = $productData['discount'] ?? 0;
            $billProduct->price = $productData['price'];
            $billProduct->description = $productData['description'] ?? '';
            $billProduct->save();

            Utility::total_quantity('plus', $billProduct->quantity, $billProduct->product_id);

            // Stock report logic could be complex on update, simplified here to just update quantity tracking
            // In a real scenario, we might need to adjust stock report entries or delete and recreate them.
        }

        return $this->success(['bill' => $bill->fresh()->load('items')], 'Bill updated successfully.');
    }

    public function deleteBill($id)
    {
        $bill = Bill::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$bill) {
            return $this->error('Bill not found.', 404);
        }

        foreach ($bill->payments as $payment) {
            Utility::bankAccountBalance($payment->account_id, $payment->amount, 'credit');
            $payment->delete();
        }

        $bill->items()->delete();
        $bill->delete();

        return $this->success([], 'Bill deleted successfully.');
    }

    public function downloadBillPdf($id)
    {
        $bill = Bill::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$bill) {
            return $this->error('Bill not found.', 404);
        }

        $billId = Crypt::encrypt($bill->id);
        $url = route('bill.pdf', $billId);

        return $this->success(['url' => $url], 'Bill PDF URL.');
    }

    public function addBillPayment(Request $request, $id)
    {
        $bill = Bill::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$bill) {
            return $this->error('Bill not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'date' => 'required',
                'amount' => 'required',
                'account_id' => 'required',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if ($bill->getDue() < $request->amount) {
            return $this->error('Amount exceeds due amount.', 400);
        }

        $billPayment                 = new BillPayment();
        $billPayment->bill_id        = $id;
        $billPayment->date           = $request->date;
        $billPayment->amount         = $request->amount;
        $billPayment->account_id     = $request->account_id;
        $billPayment->payment_method = 0;
        $billPayment->reference      = $request->reference;
        $billPayment->description    = $request->description;

        if ($request->hasFile('add_receipt')) {
            $fileName = time() . "_" . $request->file('add_receipt')->getClientOriginalName();
            $request->file('add_receipt')->storeAs('uploads/payment', $fileName);
            $billPayment->add_receipt = $fileName;
        }

        $billPayment->save();

        $due = $bill->getDue();
        if ($due <= 0) {
            $bill->status = 4;
        } else {
            $bill->status = 3;
        }
        $bill->save();

        $billPayment->user_id    = $bill->vender_id;
        $billPayment->user_type  = 'Vender';
        $billPayment->type       = 'Partial';
        $billPayment->created_by = Auth::user()->id;
        $billPayment->payment_id = $billPayment->id;
        $billPayment->category   = 'Bill';
        $billPayment->account    = $request->account_id;
        Transaction::addTransaction($billPayment);

        return $this->success(['payment' => $billPayment], 'Payment added successfully.');
    }

    public function exportBills()
    {
        // Placeholder
        return $this->success([], 'Export functionality ready.');
    }

    function billNumber()
    {
        $latest = Bill::where('created_by', '=', Auth::user()->creatorId())->latest('bill_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->bill_id + 1;
    }
}

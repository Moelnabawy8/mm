<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\PurchasePayment;
use App\Models\ProductService;
use App\Models\ProductServiceCategory;
use App\Models\Vender;
use App\Models\Utility;
use App\Models\WarehouseProduct;
use App\Models\warehouse;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PurchaseApiController extends Controller
{
    use ApiResponser;

    public function getPurchases()
    {
        $purchases = Purchase::where('created_by', Auth::user()->creatorId())
            ->with(['vender', 'category', 'warehouse'])
            ->get();

        return $this->success(['purchases' => $purchases], 'Purchases retrieved.');
    }

    public function createPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vender_id' => 'required|exists:venders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'purchase_date' => 'required|date',
            'category_id' => 'required|exists:product_service_categories,id',
            'items' => 'required|array',
            'items.*.item' => 'required|exists:product_services,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if vendor belongs to user's company
        $vendor = Vender::where('id', $request->vender_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$vendor) {
            return $this->error('Vendor not found or access denied.', 404);
        }

        // Check if warehouse belongs to user's company
        $warehouse = warehouse::where('id', $request->warehouse_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$warehouse) {
            return $this->error('Warehouse not found or access denied.', 404);
        }

        $purchase = new Purchase();
        $purchase->purchase_id = $this->purchaseNumber();
        $purchase->vender_id = $request->vender_id;
        $purchase->warehouse_id = $request->warehouse_id;
        $purchase->purchase_date = $request->purchase_date;
        $purchase->purchase_number = !empty($request->purchase_number) ? $request->purchase_number : 0;
        $purchase->status = 0;
        $purchase->category_id = $request->category_id;
        $purchase->created_by = Auth::user()->creatorId();
        $purchase->save();

        $products = $request->items;
        $totalAmount = 0;

        for ($i = 0; $i < count($products); $i++) {
            $purchaseProduct = new PurchaseProduct();
            $purchaseProduct->purchase_id = $purchase->id;
            $purchaseProduct->product_id = $products[$i]['item'];
            $purchaseProduct->quantity = $products[$i]['quantity'];
            $purchaseProduct->tax = $products[$i]['tax'] ?? 0;
            $purchaseProduct->discount = $products[$i]['discount'] ?? 0;
            $purchaseProduct->price = $products[$i]['price'];
            $purchaseProduct->description = $products[$i]['description'] ?? '';
            $purchaseProduct->save();

            // Inventory management (Quantity)
            Utility::total_quantity('plus', $purchaseProduct->quantity, $purchaseProduct->product_id);

            // Product Stock Report
            $type = 'purchase';
            $type_id = $purchase->id;
            $description = $products[$i]['quantity'] . '  ' . __(' quantity add in purchase') . ' ' . Auth::user()->purchaseNumberFormat($purchase->purchase_id);
            Utility::addProductStock($products[$i]['item'], $products[$i]['quantity'], $type, $description, $type_id);

            // Warehouse Stock Report
            if (isset($products[$i]['item'])) {
                Utility::addWarehouseStock($products[$i]['item'], $products[$i]['quantity'], $request->warehouse_id);
            }

            $totalAmount += ($purchaseProduct->quantity * $purchaseProduct->price);
        }

        return $this->success(['purchase' => $purchase->load('products')], 'Purchase created successfully.');
    }

    public function getPurchase($id)
    {
        $purchase = Purchase::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['vender', 'category', 'warehouse', 'products', 'payment'])
            ->first();

        if (!$purchase) {
            return $this->error('Purchase not found.', 404);
        }

        return $this->success(['purchase' => $purchase], 'Purchase detail.');
    }

    public function updatePurchase(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'vender_id' => 'required|exists:venders,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'purchase_date' => 'required|date',
            'category_id' => 'required|exists:product_service_categories,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $purchase = Purchase::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$purchase) {
            return $this->error('Purchase not found.', 404);
        }

        // Check if purchase can be edited (not in final status)
        if ($purchase->status == 3 || $purchase->status == 4) {
            return $this->error('Purchase cannot be edited in current status.', 400);
        }

        // Check if vendor belongs to user's company
        $vendor = Vender::where('id', $request->vender_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$vendor) {
            return $this->error('Vendor not found or access denied.', 404);
        }

        // Check if warehouse belongs to user's company
        $warehouse = warehouse::where('id', $request->warehouse_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$warehouse) {
            return $this->error('Warehouse not found or access denied.', 404);
        }

        $purchase->vender_id = $request->vender_id;
        $purchase->warehouse_id = $request->warehouse_id;
        $purchase->purchase_date = $request->purchase_date;
        $purchase->category_id = $request->category_id;
        $purchase->save();

        return $this->success(['purchase' => $purchase], 'Purchase updated successfully.');
    }

    public function deletePurchase($id)
    {
        $purchase = Purchase::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$purchase) {
            return $this->error('Purchase not found.', 404);
        }

        // Delete related products and payments
        PurchaseProduct::where('purchase_id', $purchase->id)->delete();
        PurchasePayment::where('purchase_id', $purchase->id)->delete();
        $purchase->delete();

        return $this->success([], 'Purchase deleted successfully.');
    }

    public function getVendors()
    {
        $vendors = Vender::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['vendors' => $vendors], 'Vendors retrieved.');
    }

    public function getCategories()
    {
        $categories = ProductServiceCategory::where('created_by', Auth::user()->creatorId())
            ->where('type', 'expense')
            ->get();
        return $this->success(['categories' => $categories], 'Categories retrieved.');
    }

    public function getWarehouses()
    {
        $warehouses = warehouse::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['warehouses' => $warehouses], 'Warehouses retrieved.');
    }

    public function getProducts()
    {
        $products = ProductService::where('created_by', Auth::user()->creatorId())
            ->where('type', '!=', 'service')
            ->get();
        return $this->success(['products' => $products], 'Products retrieved.');
    }

    function purchaseNumber()
    {
        $latest = Purchase::where('created_by', '=', Auth::user()->creatorId())->latest('purchase_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->purchase_id + 1;
    }
}

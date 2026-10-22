<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\warehouse;
use App\Models\WarehouseProduct;
use App\Models\ProductService;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WarehouseApiController extends Controller
{
    use ApiResponser;

    public function getWarehouses()
    {
        $warehouses = warehouse::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['warehouses' => $warehouses], 'Warehouses retrieved.');
    }

    public function createWarehouse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $warehouse = new warehouse();
        $warehouse->name = $request->name;
        $warehouse->address = $request->address;
        $warehouse->city = $request->city;
        $warehouse->city_zip = $request->city_zip;
        $warehouse->created_by = Auth::user()->creatorId();
        $warehouse->save();

        return $this->success(['warehouse' => $warehouse], 'Warehouse created successfully.');
    }

    public function getWarehouse($id)
    {
        $warehouse = warehouse::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$warehouse) {
            return $this->error('Warehouse not found.', 404);
        }
        return $this->success(['warehouse' => $warehouse], 'Warehouse detail.');
    }

    public function updateWarehouse(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $warehouse = warehouse::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$warehouse) {
            return $this->error('Warehouse not found.', 404);
        }

        $warehouse->name = $request->name;
        $warehouse->address = $request->address;
        $warehouse->city = $request->city;
        $warehouse->city_zip = $request->city_zip;
        $warehouse->save();

        return $this->success(['warehouse' => $warehouse], 'Warehouse updated successfully.');
    }

    public function deleteWarehouse($id)
    {
        $warehouse = warehouse::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$warehouse) {
            return $this->error('Warehouse not found.', 404);
        }
        $warehouse->delete();
        return $this->success([], 'Warehouse deleted successfully.');
    }

    public function getWarehouseProducts($id)
    {
        $warehouse = warehouse::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$warehouse) {
            return $this->error('Warehouse not found.', 404);
        }

        $products = WarehouseProduct::where('warehouse_id', $id)->where('created_by', Auth::user()->creatorId())->with('product')->get();
        return $this->success(['products' => $products], 'Warehouse products retrieved.');
    }

    public function addWarehouseProduct(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:product_services,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $warehouse = warehouse::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$warehouse) {
            return $this->error('Warehouse not found.', 404);
        }

        $product = ProductService::where('id', $request->product_id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $warehouseProduct = new WarehouseProduct();
        $warehouseProduct->warehouse_id = $id;
        $warehouseProduct->product_id = $request->product_id;
        $warehouseProduct->quantity = $request->quantity;
        $warehouseProduct->created_by = Auth::user()->creatorId();
        $warehouseProduct->save();

        return $this->success(['warehouse_product' => $warehouseProduct], 'Product added to warehouse successfully.');
    }

    public function createWarehouseTransfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_warehouse' => 'required|exists:warehouses,id',
            'to_warehouse' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:product_services,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Check if from warehouse belongs to user
        $fromWarehouse = warehouse::where('id', $request->from_warehouse)->where('created_by', Auth::user()->creatorId())->first();
        if (!$fromWarehouse) {
            return $this->error('Source warehouse not found.', 404);
        }

        // Check if to warehouse belongs to user
        $toWarehouse = warehouse::where('id', $request->to_warehouse)->where('created_by', Auth::user()->creatorId())->first();
        if (!$toWarehouse) {
            return $this->error('Destination warehouse not found.', 404);
        }

        // Check if product exists and belongs to user
        $product = ProductService::where('id', $request->product_id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        // Check if from warehouse has enough quantity
        $fromWarehouseProduct = WarehouseProduct::where('warehouse_id', $request->from_warehouse)
            ->where('product_id', $request->product_id)
            ->first();

        if (!$fromWarehouseProduct || $fromWarehouseProduct->quantity < $request->quantity) {
            return $this->error('Insufficient quantity in source warehouse.', 400);
        }

        // Subtract from source warehouse
        $fromWarehouseProduct->quantity -= $request->quantity;
        $fromWarehouseProduct->save();

        // Add to destination warehouse
        $toWarehouseProduct = WarehouseProduct::where('warehouse_id', $request->to_warehouse)
            ->where('product_id', $request->product_id)
            ->first();

        if ($toWarehouseProduct) {
            $toWarehouseProduct->quantity += $request->quantity;
            $toWarehouseProduct->save();
        } else {
            $toWarehouseProduct = new WarehouseProduct();
            $toWarehouseProduct->warehouse_id = $request->to_warehouse;
            $toWarehouseProduct->product_id = $request->product_id;
            $toWarehouseProduct->quantity = $request->quantity;
            $toWarehouseProduct->created_by = Auth::user()->creatorId();
            $toWarehouseProduct->save();
        }

        return $this->success([], 'Warehouse transfer completed successfully.');
    }
}

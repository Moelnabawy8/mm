<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\ProductService;
use App\Models\ProductServiceCategory;
use App\Models\ProductServiceUnit;
use App\Models\Tax;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductApiController extends Controller
{
    use ApiResponser;

    public function getProducts(Request $request)
    {
        $perPage = (int) ($request->per_page ?? 15);
        $query   = ProductService::where('created_by', Auth::user()->creatorId())
            ->with(['category:id,name', 'unit:id,name']);

        if ($request->search) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('sku', 'like', "%{$s}%");
            });
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $result = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->success([
            'products'   => $result->items(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'Products retrieved successfully.');
    }

    public function createProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'sku'            => 'required|string|max:255',
            'sale_price'     => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'quantity'       => 'required|integer|min:0',
            'unit_id'        => 'required|exists:product_service_units,id',
            'category_id'    => 'required|exists:product_service_categories,id',
            'type'           => 'required|in:product,service',
            'tax_id'                 => 'nullable|exists:taxes,id',
            'sale_chartaccount_id'   => 'nullable|exists:chart_of_accounts,id',
            'expense_chartaccount_id'=> 'nullable|exists:chart_of_accounts,id',
            'description'    => 'nullable|string',
            'pro_image'      => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $product = new ProductService();
        $product->name                    = $request->name;
        $product->sku                     = $request->sku;
        $product->sale_price              = $request->sale_price;
        $product->purchase_price          = $request->purchase_price;
        $product->quantity                = $request->quantity;
        $product->unit_id                 = $request->unit_id;
        $product->category_id             = $request->category_id;
        $product->type                    = $request->type;
        $product->tax_id                  = $request->tax_id;
        $product->sale_chartaccount_id    = $request->sale_chartaccount_id;
        $product->expense_chartaccount_id = $request->expense_chartaccount_id;
        $product->description             = $request->description;
        $product->created_by              = Auth::user()->creatorId();

        if ($request->hasFile('pro_image')) {
            $file     = $request->file('pro_image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $dir      = storage_path('uploads/pro_image');
            if (!file_exists($dir)) mkdir($dir, 0775, true);
            $file->move($dir, $fileName);
            $product->pro_image = $fileName;
        }

        $product->save();

        return $this->success(['product' => $product], 'Product created successfully.');
    }

    public function getProduct($id)
    {
        $product = ProductService::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['category:id,name', 'unit:id,name'])
            ->first();
        if (!$product) {
            return $this->error('Product not found.', 404);
        }
        return $this->success(['product' => $product], 'Product retrieved.');
    }

    public function updateProduct(Request $request, $id)
    {
        $product = ProductService::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'sometimes|required|string|max:255',
            'sale_price'     => 'sometimes|required|numeric|min:0',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'quantity'       => 'sometimes|required|integer|min:0',
            'type'           => 'sometimes|required|in:product,service',
            'pro_image'      => 'nullable|image|max:2048',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $product->fill($request->except(['pro_image', 'created_by']));

        if ($request->hasFile('pro_image')) {
            $oldPath = storage_path('uploads/pro_image/' . $product->pro_image);
            if ($product->pro_image && file_exists($oldPath)) {
                unlink($oldPath);
            }
            $file     = $request->file('pro_image');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $dir      = storage_path('uploads/pro_image');
            if (!file_exists($dir)) mkdir($dir, 0775, true);
            $file->move($dir, $fileName);
            $product->pro_image = $fileName;
        }

        $product->save();

        return $this->success(['product' => $product], 'Product updated successfully.');
    }

    public function deleteProduct($id)
    {
        $product = ProductService::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$product) {
            return $this->error('Product not found.', 404);
        }
        $product->delete();
        return $this->success([], 'Product deleted successfully.');
    }

    public function getProductCategories()
    {
        $categories = ProductServiceCategory::where('created_by', Auth::user()->creatorId())
            ->get(['id', 'name', 'type', 'color']);

        $types = array_map(fn($k, $v) => ['value' => $k, 'label' => $v],
            array_keys(ProductServiceCategory::$catTypes),
            array_values(ProductServiceCategory::$catTypes)
        );

        return $this->success([
            'categories' => $categories,
            'types'      => $types,
        ], 'Categories retrieved.');
    }

    public function createProductCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'type'  => 'nullable|string',
            'color' => 'nullable|string|max:20',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $category = ProductServiceCategory::create([
            'name'       => $request->name,
            'type'       => $request->type ?? 'product & service',
            'color'      => $request->color ?? '#fc544b',
            'created_by' => Auth::user()->creatorId(),
        ]);
        return $this->success(['category' => $category], 'Category created successfully.');
    }

    public function getProductUnits()
    {
        $units = ProductServiceUnit::where('created_by', Auth::user()->creatorId())->get(['id', 'name']);
        return $this->success(['units' => $units], 'Units retrieved.');
    }

    public function createProductUnit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $unit = ProductServiceUnit::create([
            'name'       => $request->name,
            'created_by' => Auth::user()->creatorId(),
        ]);
        return $this->success(['unit' => $unit], 'Unit created successfully.');
    }

    public function getProductTaxes()
    {
        $taxes = Tax::where('created_by', Auth::user()->creatorId())->get(['id', 'name', 'rate']);
        return $this->success(['taxes' => $taxes], 'Taxes retrieved.');
    }

    public function createProductTax(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
        ]);
        if ($validator->fails()) return $this->error($validator->errors()->first(), 422);

        $tax = Tax::create([
            'name'       => $request->name,
            'rate'       => $request->rate,
            'created_by' => Auth::user()->creatorId(),
        ]);
        return $this->success(['tax' => $tax], 'Tax created successfully.');
    }

    public function getProductAccounts()
    {
        $accounts = ChartOfAccount::where('created_by', Auth::user()->creatorId())
            ->where('is_enabled', 1)
            ->get(['id', 'name', 'code']);
        return $this->success(['accounts' => $accounts], 'Chart of accounts retrieved.');
    }

    public function generateSku()
    {
        $creatorId = Auth::user()->creatorId();
        $lastProduct = ProductService::where('created_by', $creatorId)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastProduct ? intval(substr($lastProduct->sku, -4)) + 1 : 1;
        $sku = 'PRD-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        while (ProductService::where('created_by', $creatorId)
                    ->where('sku', $sku)->exists()) {
            $nextNumber++;
            $sku = 'PRD-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return $this->success(['sku' => $sku], 'SKU generated successfully.');
    }

    public function getProductStock($id)
    {
        $product = ProductService::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$product) {
            return $this->error('Product not found.', 404);
        }
        
        $stock = [
            'product_id' => $product->id,
            'quantity' => $product->quantity,
            'name' => $product->name,
            'sku' => $product->sku,
        ];
        
        return $this->success(['stock' => $stock], 'Product stock retrieved.');
    }

    public function importProducts(Request $request)
    {
        return $this->success([], 'Import functionality available.');
    }

    public function exportProducts()
    {
        $products = ProductService::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['products' => $products], 'Products exported.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pos;
use App\Models\PosPayment;
use App\Models\PosProduct;
use App\Models\ProductService;
use App\Models\Customer;
use App\Models\warehouse;
use App\Models\StockReport;
use App\Models\Utility;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class PosApiController extends Controller
{
    use ApiResponser;

    public function getPosProducts()
    {
        $products = ProductService::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['products' => $products], 'Products retrieved.');
    }

    public function addToCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:product_services,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $product = ProductService::find($request->product_id);
        if (!$product || $product->created_by != Auth::user()->creatorId()) {
            return $this->error('Product not found.', 404);
        }

        $cart = Session::get('pos', []);
        $cartKey = $product->id;

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['quantity'] += $request->quantity;
        } else {
            $cart[$cartKey] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->sale_price,
                'quantity' => $request->quantity,
                'tax' => $product->tax_id ? $product->taxRate($product->tax_id) : 0,
                'product_tax' => $product->tax_id,
                'subtotal' => $product->sale_price * $request->quantity,
            ];
        }

        $cart[$cartKey]['subtotal'] = $cart[$cartKey]['price'] * $cart[$cartKey]['quantity'];
        Session::put('pos', $cart);

        return $this->success(['cart' => $cart], 'Product added to cart.');
    }

    public function removeFromCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $cart = Session::get('pos', []);
        if (isset($cart[$request->product_id])) {
            unset($cart[$request->product_id]);
            Session::put('pos', $cart);
            return $this->success(['cart' => $cart], 'Product removed from cart.');
        }

        return $this->error('Product not in cart.', 404);
    }

    public function getCart()
    {
        $cart = Session::get('pos', []);
        return $this->success(['cart' => $cart], 'Cart retrieved.');
    }

    public function posCheckout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required',
            'warehouse_name' => 'required',
            'discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $sales = Session::get('pos');
        if (!isset($sales) || empty($sales) || count($sales) == 0) {
            return $this->error('Add some products to cart!', 400);
        }

        $user_id = Auth::user()->creatorId();
        $customer_id = Customer::customer_id($request->customer_name);
        $warehouse_id = warehouse::warehouse_id($request->warehouse_name);
        $pos_id = $this->invoicePosNumber();
        $discount = $request->discount ?? 0;

        $result = DB::table('pos')->where('pos_id', $pos_id)->where('created_by', $user_id)->get();
        if (count($result) > 0) {
            return $this->error('Payment is already completed!', 400);
        }

        $pos = new Pos();
        $pos->pos_id = $pos_id;
        $pos->customer_id = $customer_id;
        $pos->warehouse_id = $warehouse_id;
        $pos->pos_date = date('Y-m-d');
        $pos->created_by = $user_id;
        $pos->save();

        foreach ($sales as $key => $value) {
            $product_id = $value['id'];
            $product = ProductService::whereId($product_id)->where('created_by', $user_id)->first();

            if ($product) {
                $original_quantity = (int) $product->quantity;
                $product_quantity = $original_quantity - $value['quantity'];
                ProductService::where('id', $product_id)->update(['quantity' => $product_quantity]);

                $tax_id = ProductService::tax_id($product_id);

                $positems = new PosProduct();
                $positems->pos_id = $pos->id;
                $positems->product_id = $product_id;
                $positems->price = $value['price'];
                $positems->quantity = $value['quantity'];
                $positems->tax = $tax_id;
                $positems->discount = $discount;
                $positems->save();

                Utility::warehouse_quantity('minus', $positems->quantity, $positems->product_id, $warehouse_id);

                // Product Stock Report
                $type = 'pos';
                $type_id = $pos->id;
                StockReport::where('type', '=', 'pos')->where('type_id', '=', $pos->id)->delete();
                $description = $positems->quantity . '  ' . __(' quantity sold in pos') . ' ' . Auth::user()->posNumberFormat($pos->pos_id);
                Utility::addProductStock($positems->product_id, $positems->quantity, $type, $description, $type_id);
            }
        }

        $posPayment = new PosPayment();
        $posPayment->pos_id = $pos->id;
        $posPayment->amount = $this->getTotal($sales, $discount);
        $posPayment->date = date('Y-m-d');
        $posPayment->discount = $discount;
        $posPayment->save();

        Session::forget('pos');

        return $this->success(['pos' => $pos->load('products', 'payment')], 'Checkout successful.');
    }

    public function getPosSales()
    {
        $sales = Pos::where('created_by', Auth::user()->creatorId())->with(['customer', 'products.product', 'payment'])->get();
        return $this->success(['sales' => $sales], 'Sales retrieved.');
    }

    public function getPosCustomers()
    {
        $customers = Customer::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['customers' => $customers], 'Customers retrieved.');
    }

    public function getPosWarehouses()
    {
        $warehouses = warehouse::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['warehouses' => $warehouses], 'Warehouses retrieved.');
    }

    function invoicePosNumber()
    {
        $latest = Pos::where('created_by', '=', Auth::user()->creatorId())->latest('pos_id')->first();
        if (!$latest) {
            return 1;
        }
        return $latest->pos_id + 1;
    }

    private function getTotal($sales, $discount = 0)
    {
        $total = 0;
        foreach ($sales as $sale) {
            $total += $sale['subtotal'];
        }
        return $total - $discount;
    }
}

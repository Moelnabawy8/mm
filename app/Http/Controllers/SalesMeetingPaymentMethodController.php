<?php

namespace App\Http\Controllers;

use App\Models\SalesMeetingPaymentMethod;
use Illuminate\Http\Request;

class SalesMeetingPaymentMethodController extends Controller
{
    public function index()
    {
        if (\Auth::user()->can('manage lead stage')) {
            $paymentMethods = SalesMeetingPaymentMethod::where('created_by', '=', \Auth::user()->creatorId())->get();
            return view('sales_meeting_payment_methods.index', compact('paymentMethods'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create()
    {
        if (\Auth::user()->can('create lead stage')) {
            return view('sales_meeting_payment_methods.create');
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create lead stage')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:20',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $paymentMethod = new SalesMeetingPaymentMethod();
            $paymentMethod->name = $request->name;
            $paymentMethod->created_by = \Auth::user()->creatorId();
            $paymentMethod->save();

            return redirect()->route('sales-meeting-payment-methods.index')->with('success', __('Payment Method successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id)
    {
        if (\Auth::user()->can('edit lead stage')) {
            $paymentMethod = SalesMeetingPaymentMethod::find($id);

            return view('sales_meeting_payment_methods.edit', compact('paymentMethod'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit lead stage')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:20',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $paymentMethod = SalesMeetingPaymentMethod::find($id);
            $paymentMethod->name = $request->name;
            $paymentMethod->save();

            return redirect()->route('sales-meeting-payment-methods.index')->with('success', __('Payment Method successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete lead stage')) {
            $paymentMethod = SalesMeetingPaymentMethod::find($id);
            $paymentMethod->delete();

            return redirect()->route('sales-meeting-payment-methods.index')->with('success', __('Payment Method successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}

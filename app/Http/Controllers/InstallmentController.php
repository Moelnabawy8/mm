<?php

namespace App\Http\Controllers;

use App\Models\Installment;
use App\Models\DealPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InstallmentController extends Controller
{
    public function index(Request $request)
    {
        if (\Auth::user()->can('manage deal')) {
            $usr = \Auth::user();
            $query = Installment::query();

            // Filter for Sales Employee only (Alaa,Saba,aber and fadi users see everything)
            if (($usr->hasRole('Sales Employee') || $usr->hasRole('sales employee')) &&
                $usr->type != 'Alaa,Saba,aber' &&
                $usr->type != 'fadi'
            ) {
                // Get leads assigned to this user
                $userLeadIds = \App\Models\UserLead::where('user_id', $usr->id)->pluck('lead_id');
                // Filter installments to only those where the deal purchase's deal's lead is assigned to this user
                $query->whereHas('dealPurchase.deal', function ($q) use ($userLeadIds) {
                    $q->whereIn('lead_id', $userLeadIds);
                });
            }

            // Apply Filters
            if ($request->deal_purchase_id) {
                $query->where('deal_purchase_id', $request->deal_purchase_id);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->min_amount && $request->max_amount) {
                $query->whereBetween('amount', [$request->min_amount, $request->max_amount]);
            } elseif ($request->min_amount) {
                $query->where('amount', '>=', $request->min_amount);
            } elseif ($request->max_amount) {
                $query->where('amount', '<=', $request->max_amount);
            }
            if ($request->date_from && $request->date_to) {
                $query->whereBetween('date', [$request->date_from, $request->date_to]);
            } elseif ($request->date_from) {
                $query->where('date', '>=', $request->date_from);
            } elseif ($request->date_to) {
                $query->where('date', '<=', $request->date_to);
            }

            // Aggregate stats (not affected by pagination)
            $total = (clone $query)->sum('amount');
            $totalPaid = (clone $query)->where('status', 'paid')->sum('amount');
            $totalUnpaid = (clone $query)->where('status', 'unpaid')->sum('amount');
            $totalHopeless = (clone $query)->where('is_hopeless', true)->sum('amount');
            $totalExemption = (clone $query)->sum('exemption');
            $hopelessCount = (clone $query)->where('is_hopeless', true)->count();
            $exemptionCount = (clone $query)->where('exemption', '>', 0)->count();
            $paidCount = (clone $query)->where('status', 'paid')->count();
            $unpaidCount = (clone $query)->where('status', 'unpaid')->count();

            $installments = $query->with('dealPurchase.deal')
                ->orderBy('id', 'desc')
                ->paginate(20)
                ->appends($request->query());

            // Filter options for the view
            // Also filter deal purchases dropdown for Sales Employee only (Alaa,Saba,aber and fadi users see everything)
            if (($usr->hasRole('Sales Employee') || $usr->hasRole('sales employee')) &&
                $usr->type != 'Alaa,Saba,aber' &&
                $usr->type != 'fadi'
            ) {
                $userLeadIds = \App\Models\UserLead::where('user_id', $usr->id)->pluck('lead_id');
                $dealPurchases = DealPurchase::with('deal')
                    ->where('created_by', $usr->ownerId())
                    ->whereHas('deal', function ($q) use ($userLeadIds) {
                        $q->whereIn('lead_id', $userLeadIds);
                    })
                    ->get();
            } else {
                $dealPurchases = DealPurchase::with('deal')->where('created_by', $usr->ownerId())->get();
            }

            if ($request->ajax()) {
                return view('installments._table', compact('installments', 'total', 'totalPaid', 'totalUnpaid', 'totalHopeless', 'totalExemption', 'hopelessCount', 'exemptionCount', 'paidCount', 'unpaidCount'));
            }

            return view('installments.index', compact('installments', 'total', 'totalPaid', 'totalUnpaid', 'totalHopeless', 'totalExemption', 'hopelessCount', 'exemptionCount', 'paidCount', 'unpaidCount', 'dealPurchases'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create(Request $request)
    {
        if (\Auth::user()->can('create deal')) {
            // We might want to show more details in the dropdown like deal name or purchase ID
            // For now, let's fetch DealPurchases with their related Deal to show a better name if possible, 
            // but the pluck method above is efficient. Let's stick to simple ID or maybe format it in view if passed as object.
            // Actually, let's pass objects to the view to allow better formatting.
            $dealPurchases = DealPurchase::with(['deal', 'installments'])->where('created_by', \Auth::user()->ownerId())->get();

            // Get lead_id if passed from lead page
            $leadId = $request->query('lead_id');

            return view('installments.create', compact('dealPurchases', 'leadId'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create deal')) {
            $validator = Validator::make($request->all(), [
                'deal_purchase_id' => 'required|exists:deal_purchases,id',
                'amount' => 'required|numeric|min:0.01',
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first())->withInput();
            }

            $dealPurchase = DealPurchase::find($request->deal_purchase_id);
            // Relaxed check: We trust the user if they can see the deal purchase in the dropdown or if they have 'create deal' permission.
            // DealPurchaseController does not strictly check ownership of related models in store(), it just checks 'create deal'.

            $totalPrice = $dealPurchase->price;
            $existingInstallments = $dealPurchase->installments()->sum('amount');

            if (round($existingInstallments + $request->amount, 2) > round($totalPrice, 2)) {
                return redirect()->back()->with('error', __('The value of all installments cannot exceed the total price in the deal purchase. ' .
                    __('Total Price: ') . $totalPrice . ', ' . __('Existing: ') . $existingInstallments . ', ' . __('Current: ') . $request->amount))->withInput();
            }

            $installment = new Installment();
            $installment->deal_purchase_id = $request->deal_purchase_id;
            $installment->amount = $request->amount;
            $installment->exemption = $request->exemption ?? 0;
            $installment->is_hopeless = $request->is_hopeless ?? false;
            $installment->date = $request->date;
            $installment->description = $request->description;
            $installment->status = $request->status ?? 'unpaid';
            $installment->created_by = \Auth::user()->ownerId();
            $installment->save();

            // Check if we came from a lead page
            if ($request->has('lead_id') && $request->lead_id) {
                return redirect()->route('leads.show', $request->lead_id)->with('success', __('Installment successfully created.'));
            }

            return redirect()->route('installments.index')->with('success', __('Installment successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show($id)
    {
        if (\Auth::user()->can('manage deal')) {
            $installment = Installment::with(['dealPurchase.deal.lead'])->findOrFail($id);
            return view('installments.show', compact('installment'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id, Request $request)
    {
        if (\Auth::user()->can('edit deal')) {
            $installment = Installment::find($id);
            $dealPurchases = DealPurchase::with(['deal', 'installments'])->where('created_by', \Auth::user()->ownerId())->get();

            // Get lead_id if passed from lead page
            $leadId = $request->query('lead_id');

            return view('installments.edit', compact('installment', 'dealPurchases', 'leadId'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit deal')) {
            $validator = Validator::make($request->all(), [
                'deal_purchase_id' => 'required|exists:deal_purchases,id',
                'amount' => 'required|numeric|min:0.01',
                'date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first())->withInput();
            }

            $installment = Installment::find($id);
            $dealPurchase = DealPurchase::find($request->deal_purchase_id);

            $totalPrice = $dealPurchase->price;

            // Exclude current installment amount from sum check
            $existingInstallments = $dealPurchase->installments()->where('id', '!=', $id)->sum('amount');

            if (round($existingInstallments + $request->amount, 2) > round($totalPrice, 2)) {
                return redirect()->back()->with('error', __('The value of all installments cannot exceed the total price in the deal purchase. ' .
                    __('Total Price: ') . $totalPrice . ', ' . __('Existing: ') . $existingInstallments . ', ' . __('Current: ') . $request->amount))->withInput();
            }

            $installment->deal_purchase_id = $request->deal_purchase_id;
            $installment->amount = $request->amount;
            $installment->exemption = $request->exemption ?? $installment->exemption ?? 0;
            $installment->is_hopeless = $request->is_hopeless ?? $installment->is_hopeless ?? false;
            $installment->date = $request->date;
            $installment->description = $request->description;
            $installment->status = $request->status ?? $installment->status ?? 'unpaid';
            $installment->save();

            // Check if we came from a lead page
            if ($request->has('lead_id') && $request->lead_id) {
                return redirect()->route('leads.show', $request->lead_id)->with('success', __('Installment successfully updated.'));
            }

            return redirect()->route('installments.index')->with('success', __('Installment successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete deal')) {
            $installment = Installment::find($id);
            $installment->delete();

            // Check if we came from a lead page
            if (request()->has('lead_id') && request()->lead_id) {
                return redirect()->route('leads.show', request()->lead_id)->with('success', __('Installment successfully deleted.'));
            }

            return redirect()->route('installments.index')->with('success', __('Installment successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}

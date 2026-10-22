<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DealPurchase;
use App\Models\Installment;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InstallmentApiController extends Controller
{
    use ApiResponser;

    public function getInstallments(Request $request)
    {
        $query = Installment::with(['dealPurchase.deal.lead'])
            ->whereHas('dealPurchase.deal', function ($q) {
                $q->where('created_by', Auth::user()->creatorId());
            });

        if ($request->deal_purchase_id) {
            $query->where('deal_purchase_id', $request->deal_purchase_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        } elseif ($request->date_from) {
            $query->where('date', '>=', $request->date_from);
        } elseif ($request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }

        $all = (clone $query)->orderBy('date')->get();

        $totalCount      = $all->count();
        $totalAmount     = (float) $all->sum('amount');
        $paidItems       = $all->where('status', 'paid');
        $unpaidItems     = $all->where('status', 'unpaid');
        $hopelessItems   = $all->where('is_hopeless', true);
        $exemptionItems  = $all->where('exemption', '>', 0);

        $paidAmount      = (float) $paidItems->sum('amount');
        $unpaidAmount    = (float) $unpaidItems->sum('amount');
        $hopelessAmount  = (float) $hopelessItems->sum('amount');
        $totalExemption  = (float) $all->sum('exemption');

        $stats = [
            'total_count'          => $totalCount,
            'total_amount'         => $totalAmount,
            'paid_count'           => $paidItems->count(),
            'paid_amount'          => $paidAmount,
            'paid_percentage'      => $totalCount > 0 ? round($paidItems->count() * 100 / $totalCount) : 0,
            'unpaid_count'         => $unpaidItems->count(),
            'unpaid_amount'        => $unpaidAmount,
            'unpaid_percentage'    => $totalCount > 0 ? round($unpaidItems->count() * 100 / $totalCount) : 0,
            'hopeless_count'       => $hopelessItems->count(),
            'hopeless_amount'      => $hopelessAmount,
            'hopeless_percentage'  => $totalCount > 0 ? round($hopelessItems->count() * 100 / $totalCount) : 0,
            'exemption_count'      => $exemptionItems->count(),
            'total_exemption'      => $totalExemption,
            'exemption_percentage' => $totalCount > 0 ? round($exemptionItems->count() * 100 / $totalCount) : 0,
        ];

        $perPage = (int) ($request->per_page ?? 15);
        $result  = $query->orderBy('date')->paginate($perPage);

        return $this->success([
            'installments' => $result->items(),
            'pagination'   => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
            'stats' => $stats,
        ], 'Installments retrieved.');
    }

    public function createInstallment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deal_purchase_id' => 'required|exists:deal_purchases,id',
            'amount'           => 'required|numeric|min:0',
            'date'             => 'required|date',
            'description'      => 'nullable|string',
            'status'           => 'nullable|in:paid,unpaid',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Ensure the deal purchase belongs to this user
        $dealPurchase = DealPurchase::whereHas('deal', function ($q) {
            $q->where('created_by', Auth::user()->creatorId());
        })->find($request->deal_purchase_id);

        if (!$dealPurchase) {
            return $this->error('Deal purchase not found or access denied.', 404);
        }

        $installment = Installment::create([
            'deal_purchase_id' => $request->deal_purchase_id,
            'amount'           => $request->amount,
            'date'             => $request->date,
            'description'      => $request->description,
            'status'           => $request->status ?? 'unpaid',
            'created_by'       => Auth::user()->creatorId(),
        ]);

        $installment->load('dealPurchase.deal.lead');

        return $this->success(['installment' => $installment], 'Installment created successfully.');
    }

    public function getInstallment($id)
    {
        $installment = Installment::with(['dealPurchase.deal.lead'])
            ->whereHas('dealPurchase.deal', function ($q) {
                $q->where('created_by', Auth::user()->creatorId());
            })
            ->find($id);

        if (!$installment) {
            return $this->error('Installment not found.', 404);
        }

        return $this->success(['installment' => $installment], 'Installment retrieved.');
    }

    public function updateInstallment(Request $request, $id)
    {
        $installment = Installment::whereHas('dealPurchase.deal', function ($q) {
            $q->where('created_by', Auth::user()->creatorId());
        })->find($id);

        if (!$installment) {
            return $this->error('Installment not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'amount'      => 'sometimes|required|numeric|min:0',
            'date'        => 'sometimes|required|date',
            'description' => 'nullable|string',
            'status'      => 'nullable|in:paid,unpaid',
            'is_hopeless' => 'nullable|boolean',
            'exemption'   => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $installment->fill($request->only([
            'amount', 'date', 'description', 'status', 'is_hopeless', 'exemption',
        ]));
        $installment->save();

        return $this->success(['installment' => $installment], 'Installment updated successfully.');
    }

    public function deleteInstallment($id)
    {
        $installment = Installment::whereHas('dealPurchase.deal', function ($q) {
            $q->where('created_by', Auth::user()->creatorId());
        })->find($id);

        if (!$installment) {
            return $this->error('Installment not found.', 404);
        }

        $installment->delete();

        return $this->success([], 'Installment deleted successfully.');
    }

    public function updateInstallmentStatus(Request $request, $id)
    {
        $installment = Installment::whereHas('dealPurchase.deal', function ($q) {
            $q->where('created_by', Auth::user()->creatorId());
        })->find($id);

        if (!$installment) {
            return $this->error('Installment not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:paid,unpaid',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $installment->status = $request->status;
        $installment->save();

        return $this->success(['installment' => $installment], 'Installment status updated successfully.');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealPurchase;
use App\Models\DealPurchaseAttachment;
use App\Models\Installment;
use App\Models\SalesMeetingPaymentMethod;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DealPurchaseApiController extends Controller
{
    use ApiResponser;

    public function getDealPurchases(Request $request)
    {
        $query = DealPurchase::with(['deal.lead', 'installments', 'attachments'])
            ->whereHas('deal', function ($q) {
                $q->where('created_by', Auth::user()->creatorId());
            });

        if ($request->deal_id) {
            $query->where('deal_id', $request->deal_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('purchase_date', [$request->date_from, $request->date_to]);
        } elseif ($request->date_from) {
            $query->where('purchase_date', '>=', $request->date_from);
        } elseif ($request->date_to) {
            $query->where('purchase_date', '<=', $request->date_to);
        }

        $statsQuery = clone $query;
        $stats = $statsQuery->selectRaw('
            COUNT(*) as total_deals,
            SUM(price) as total_price,
            SUM(paid_amount) as total_paid_amount,
            SUM(price - paid_amount) as total_remaining_amount
        ')->first();

        $perPage = (int) ($request->per_page ?? 15);
        $result  = $query->with('paymentMethodDetails')->orderBy('id', 'desc')->paginate($perPage);

        return $this->success([
            'summary' => [
                'total_deals'            => (int) $stats->total_deals,
                'total_price'            => (float) $stats->total_price,
                'total_paid_amount'      => (float) $stats->total_paid_amount,
                'total_remaining_amount' => (float) $stats->total_remaining_amount,
            ],
            'deal_purchases' => $result->items(),
            'pagination'     => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 'Deal purchases retrieved.');
    }

    public function createDealPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deal_id'            => 'required|exists:deals,id|unique:deal_purchases,deal_id',
            'price'              => 'required|numeric|min:0',
            'currency'           => 'required|string',
            'payment_method'     => 'required',
            'payment_type'       => 'required|in:one-time,installment',
            'paid_amount'        => 'required|numeric|min:0',
            'purchase_date'      => 'required|date',
            'status'             => 'required|in:pending,complete',
            'products'           => 'nullable|array',
            'installments'       => 'nullable|array',
            'installments.*.amount'      => 'required_with:installments|numeric|min:0',
            'installments.*.date'        => 'required_with:installments|date',
            'installments.*.description' => 'nullable|string',
            'installments.*.status'      => 'nullable|in:paid,unpaid',
        ], [
            'deal_id.unique' => 'This deal already has a purchase record. Each deal can only have one purchase.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        // Ensure the deal belongs to this user
        $deal = Deal::where('id', $request->deal_id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$deal) {
            return $this->error('Deal not found or access denied.', 404);
        }

        $dealPurchase = new DealPurchase();
        $dealPurchase->deal_id        = $request->deal_id;
        $dealPurchase->price          = $request->price;
        $dealPurchase->currency       = $request->currency;
        $dealPurchase->payment_method = $request->payment_method;
        $dealPurchase->payment_type   = $request->payment_type;
        $dealPurchase->paid_amount    = $request->payment_type === 'one-time' ? $request->price : $request->paid_amount;
        $dealPurchase->status         = $request->status;
        $dealPurchase->purchase_date  = $request->purchase_date;
        $dealPurchase->created_by     = Auth::user()->creatorId();
        $dealPurchase->products       = $request->products;
        $dealPurchase->save();

        if ($request->payment_type === 'installment' && $request->has('installments')) {
            $installmentsData = $request->installments;
            $totalInstallments = array_sum(array_column($installmentsData, 'amount'));
            $maxUpfront = $request->price - $totalInstallments;
            if ($dealPurchase->paid_amount > $maxUpfront) {
                $dealPurchase->paid_amount = max(0, $maxUpfront);
                $dealPurchase->save();
            }

            foreach ($installmentsData as $installmentData) {
                Installment::create([
                    'deal_purchase_id' => $dealPurchase->id,
                    'amount'           => $installmentData['amount'],
                    'date'             => $installmentData['date'],
                    'description'      => $installmentData['description'] ?? null,
                    'status'           => $installmentData['status'] ?? 'unpaid',
                    'created_by'       => Auth::user()->creatorId(),
                ]);
            }
        }

        $dealPurchase->load(['deal.lead', 'installments', 'attachments']);

        return $this->success(['deal_purchase' => $dealPurchase], 'Deal purchase created successfully.');
    }

    public function getDealPurchase($id)
    {
        $dealPurchase = DealPurchase::with(['deal.lead', 'deal.stage', 'installments', 'attachments', 'paymentMethodDetails'])
            ->whereHas('deal', function ($q) {
                $q->where('created_by', Auth::user()->creatorId());
            })
            ->find($id);

        if (!$dealPurchase) {
            return $this->error('Deal purchase not found.', 404);
        }

        return $this->success(['deal_purchase' => $dealPurchase], 'Deal purchase retrieved.');
    }

    public function updateDealPurchase(Request $request, $id)
    {
        $dealPurchase = DealPurchase::whereHas('deal', function ($q) {
            $q->where('created_by', Auth::user()->creatorId());
        })->find($id);

        if (!$dealPurchase) {
            return $this->error('Deal purchase not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'deal_id'            => 'required|exists:deals,id|unique:deal_purchases,deal_id,' . $id,
            'price'              => 'required|numeric|min:0',
            'currency'           => 'required|string',
            'payment_method'     => 'required',
            'payment_type'       => 'required|in:one-time,installment',
            'paid_amount'        => 'required|numeric|min:0',
            'purchase_date'      => 'required|date',
            'status'             => 'required|in:pending,complete',
            'products'           => 'nullable|array',
            'installments'       => 'nullable|array',
            'installments.*.id'          => 'nullable|exists:installments,id',
            'installments.*.amount'      => 'required_with:installments|numeric|min:0',
            'installments.*.date'        => 'required_with:installments|date',
            'installments.*.description' => 'nullable|string',
            'installments.*.status'      => 'nullable|in:paid,unpaid',
        ], [
            'deal_id.unique' => 'This deal already has a purchase record. Each deal can only have one purchase.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $dealPurchase->deal_id        = $request->deal_id;
        $dealPurchase->price          = $request->price;
        $dealPurchase->currency       = $request->currency;
        $dealPurchase->payment_method = $request->payment_method;
        $dealPurchase->payment_type   = $request->payment_type;
        $dealPurchase->status         = $request->status;
        $dealPurchase->purchase_date  = $request->purchase_date;
        $dealPurchase->products       = $request->products;

        if ($request->payment_type === 'one-time') {
            $dealPurchase->paid_amount = $request->price;
            $dealPurchase->installments()->delete();
        } else {
            $dealPurchase->paid_amount = $request->paid_amount;

            if ($request->has('installments')) {
                $installmentsData = $request->installments;
                $totalInstallments = array_sum(array_column($installmentsData, 'amount'));
                $maxUpfront = $request->price - $totalInstallments;
                if ($dealPurchase->paid_amount > $maxUpfront) {
                    $dealPurchase->paid_amount = max(0, $maxUpfront);
                }

                $keptIds = [];
                foreach ($installmentsData as $installmentData) {
                    if (!empty($installmentData['id'])) {
                        $installment = Installment::find($installmentData['id']);
                        if ($installment && $installment->deal_purchase_id == $dealPurchase->id) {
                            $installment->amount      = $installmentData['amount'];
                            $installment->date        = $installmentData['date'];
                            $installment->description = $installmentData['description'] ?? null;
                            $installment->status      = $installmentData['status'] ?? $installment->status ?? 'unpaid';
                            $installment->save();
                            $keptIds[] = $installment->id;
                        }
                    } else {
                        $installment = Installment::create([
                            'deal_purchase_id' => $dealPurchase->id,
                            'amount'           => $installmentData['amount'],
                            'date'             => $installmentData['date'],
                            'description'      => $installmentData['description'] ?? null,
                            'status'           => $installmentData['status'] ?? 'unpaid',
                            'created_by'       => Auth::user()->creatorId(),
                        ]);
                        $keptIds[] = $installment->id;
                    }
                }
                $dealPurchase->installments()->whereNotIn('id', $keptIds)->delete();
            }
        }

        $dealPurchase->save();
        $dealPurchase->load(['deal.lead', 'installments', 'attachments']);

        return $this->success(['deal_purchase' => $dealPurchase], 'Deal purchase updated successfully.');
    }

    public function deleteDealPurchase($id)
    {
        $dealPurchase = DealPurchase::whereHas('deal', function ($q) {
            $q->where('created_by', Auth::user()->creatorId());
        })->find($id);

        if (!$dealPurchase) {
            return $this->error('Deal purchase not found.', 404);
        }

        $dealPurchase->delete();

        return $this->success([], 'Deal purchase deleted successfully.');
    }

    public function uploadAttachment(Request $request, $id)
    {
        $dealPurchase = DealPurchase::whereHas('deal', function ($q) {
            $q->where('created_by', Auth::user()->creatorId());
        })->find($id);

        if (!$dealPurchase) {
            return $this->error('Deal purchase not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,txt,doc,docx,pdf',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $file = $request->file('file');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $storagePath = 'deal_purchase_attachments/' . $id;
        $file->storeAs($storagePath, $fileName, 'public');

        $attachment = DealPurchaseAttachment::create([
            'deal_purchase_id' => $id,
            'file_name'        => $file->getClientOriginalName(),
            'file_path'        => $storagePath . '/' . $fileName,
            'file_type'        => str_starts_with($file->getClientMimeType(), 'image/') ? 'image' : 'document',
            'mime_type'        => $file->getClientMimeType(),
            'file_size'        => $file->getSize(),
            'uploaded_by'      => Auth::id(),
        ]);

        return $this->success(['attachment' => $attachment], 'Attachment uploaded successfully.');
    }

    public function getPaymentMethods()
    {
        $methods = SalesMeetingPaymentMethod::all();
        return $this->success(['payment_methods' => $methods], 'Payment methods retrieved.');
    }

    public function getPaymentTypes()
    {
        $types = [
            ['value' => 'one-time',     'label' => 'One Time'],
            ['value' => 'installment',  'label' => 'Installment'],
        ];
        return $this->success(['payment_types' => $types], 'Payment types retrieved.');
    }

    public function getStatuses()
    {
        $statuses = [
            ['value' => 'pending',  'label' => 'Pending'],
            ['value' => 'complete', 'label' => 'Complete'],
        ];
        return $this->success(['statuses' => $statuses], 'Statuses retrieved.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealPurchase;
use App\Models\DealPurchaseAttachment;
use App\Models\SalesMeetingPaymentMethod;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DealPurchaseController extends Controller
{
    /**
     * Above this many candidates the Deal field goes back to the searched
     * picker. A lead normally has one or two deals; this is a guard against the
     * pathological case, not an expectation.
     */
    const DROPDOWN_LIMIT = 50;

    /**
     * Purchases the current tenant is allowed to touch.
     *
     * Scoping goes through the deal (`deals.created_by`), exactly like
     * DealPurchaseApiController and DealController@index, rather than through
     * `deal_purchases.created_by`: the deal is the row whose ownership every
     * other screen already trusts.
     */
    private function ownedPurchases()
    {
        return DealPurchase::whereHas('deal', function ($q) {
            $q->where('created_by', \Auth::user()->ownerId());
        });
    }

    /**
     * Load one purchase of the current tenant or abort 404.
     * Used by show/edit/update/destroy/attachments, which all used to take a
     * bare id and hand back any tenant's record.
     */
    private function findOwnedPurchase($id, array $with = [])
    {
        return $this->ownedPurchases()->with($with)->whereKey($id)->firstOrFail();
    }

    /** Does the given lead belong to $usr, expressed as a subquery (not a pluck). */
    private function assignedLeadIdSubQuery($userId)
    {
        return \DB::table('user_leads')->select('lead_id')->where('user_id', $userId);
    }

    /** Sales Employees only see purchases on leads assigned to them. */
    private function restrictedToOwnLeads($usr)
    {
        return ($usr->hasRole('Sales Employee') || $usr->hasRole('sales employee'))
            && $usr->type != 'Alaa,Saba,aber'
            && $usr->type != 'fadi';
    }

    public function index(Request $request)
    {
        if (\Auth::user()->can('manage deal')) {
            $usr = \Auth::user();
            $query = $this->ownedPurchases();

            // Filter for Sales Employee only (Alaa,Saba,aber and fadi users see everything)
            if ($this->restrictedToOwnLeads($usr)) {
                // Filter deal purchases to only those where the deal's lead is assigned to this user
                $sub = $this->assignedLeadIdSubQuery($usr->id);
                $query->whereHas('deal', function ($q) use ($sub) {
                    $q->whereIn('lead_id', $sub);
                });
            }

            // Apply Filters
            if ($request->deal_id) {
                $query->where('deal_id', $request->deal_id);
            }
            if ($request->status) {
                $query->where('status', $request->status);
            }
            // Filter by employee (through deal's lead assignment)
            if ($request->employee_id) {
                $employeeSub = $this->assignedLeadIdSubQuery($request->employee_id);
                $query->whereHas('deal', function ($q) use ($employeeSub) {
                    $q->whereIn('lead_id', $employeeSub);
                });
            }
            if ($request->min_price && $request->max_price) {
                $query->whereBetween('price', [$request->min_price, $request->max_price]);
            } elseif ($request->min_price) {
                $query->where('price', '>=', $request->min_price);
            } elseif ($request->max_price) {
                $query->where('price', '<=', $request->max_price);
            }
            if ($request->date_from && $request->date_to) {
                $query->whereBetween('purchase_date', [$request->date_from, $request->date_to]);
            } elseif ($request->date_from) {
                $query->where('purchase_date', '>=', $request->date_from);
            } elseif ($request->date_to) {
                $query->where('purchase_date', '<=', $request->date_to);
            }

            // Aggregate totals for ALL filtered records (before pagination).
            //
            // "Paid" is the upfront paid_amount PLUS every installment already marked
            // paid -- the same rule the detail page and InstallmentController use.
            // Summing paid_amount alone made the list report a bigger remaining
            // balance than the purchase's own detail page.
            $paidExpr = '(deal_purchases.paid_amount + COALESCE((SELECT SUM(i.amount) FROM installments i'
                . " WHERE i.deal_purchase_id = deal_purchases.id AND i.status = 'paid'"
                . " AND deal_purchases.payment_type = 'installment'), 0))";

            $totals = (clone $query)->selectRaw(
                'COUNT(*) as rows_count,'
                . ' COALESCE(SUM(deal_purchases.price), 0) as total_price,'
                . " COALESCE(SUM($paidExpr), 0) as total_paid,"
                . " COALESCE(SUM(GREATEST(deal_purchases.price - $paidExpr, 0)), 0) as total_remaining,"
                . ' COUNT(DISTINCT deal_purchases.currency) as currency_count'
            )->first();

            $totalCount = (int) ($totals->rows_count ?? 0);
            $grandTotalPrice = (float) ($totals->total_price ?? 0);
            $grandTotalPaid = (float) ($totals->total_paid ?? 0);
            $grandTotalRemaining = (float) ($totals->total_remaining ?? 0);

            // Those sums add different currencies together when the tenant uses more
            // than one, so the view warns instead of presenting a meaningless number.
            $mixedCurrency = ((int) ($totals->currency_count ?? 0)) > 1;

            $firstResult = (clone $query)->select('currency')->orderBy('id', 'desc')->first();
            $defaultCurrency = $firstResult->currency ?? 'USD';

            $dealPurchases = $query
                ->with(['deal.lead.users', 'attachments'])
                ->withSum(['installments as paid_installments_sum' => function ($q) {
                    $q->where('status', 'paid');
                }], 'amount')
                ->orderBy('id', 'desc')
                ->paginate(20)
                ->appends($request->query());

            // Filter options for the view.
            // The Deal filter is a server-searched picker (deal-purchase.deals.search),
            // NOT a <select> holding every deal of the tenant: custom.js runs
            // Choices.js over every .select2 on page load and builds a DOM node per
            // option, which locks the browser before the modal is ever opened.
            $selectedDealName = '';
            if ($request->deal_id) {
                $selectedDealName = (string) $this->searchableDeals($usr)
                    ->where('id', $request->deal_id)
                    ->value('name');
            }

            // The Employee filter offers sales people only, using the one definition
            // in LeadScope that the leads page also uses. The list here used to be a
            // hard-coded set of user types and role names, so anyone who sits in a
            // Sales department without one of those exact labels was missing from it.
            $employees = \App\Services\LeadScope::salesUsersQuery($usr)
                ->orderBy('users.name')
                ->get()
                ->pluck('name', 'id');

            if ($request->ajax()) {
                return view('admin.deal_purchase._table', compact('dealPurchases', 'totalCount', 'grandTotalPrice', 'grandTotalPaid', 'grandTotalRemaining', 'defaultCurrency', 'mixedCurrency'));
            }

            return view('admin.deal_purchase.index', compact('dealPurchases', 'selectedDealName', 'totalCount', 'employees', 'grandTotalPrice', 'grandTotalPaid', 'grandTotalRemaining', 'defaultCurrency', 'mixedCurrency'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if (\Auth::user()->can('create deal')) {
            // Get lead_id if passed from lead page
            $leadId = $request->query('lead_id');

            // Deals that don't have a purchase yet. Only the COUNT is needed here --
            // the picker fetches names from deal-purchase.deals.search on demand.
            $dealsQuery = $this->selectableDealsQuery($leadId);
            $dealsCount = $dealsQuery->count();

            // Opened from a lead the candidates are few, so the form offers a
            // plain dropdown. Null means "no lead, or too many" and the view
            // falls back to the searched picker.
            //
            // Fetched before $selectedDeal on purpose: first() puts a limit on
            // the builder, and a get() after it would come back short.
            $leadDeals = $leadId && $dealsCount > 0 && $dealsCount <= self::DROPDOWN_LIMIT
                ? $dealsQuery->orderBy('name')->get(['id', 'name'])
                : null;

            // Pre-select when there is exactly one candidate (the lead_id flow).
            $selectedDeal = $dealsCount === 1
                ? ($leadDeals ? $leadDeals->first() : $dealsQuery->first())
                : null;

            $paymentMethods = SalesMeetingPaymentMethod::all()->pluck('name', 'id');
            $currencies = Utility::currencies();

            return view('admin.deal_purchase.create', compact('dealsCount', 'selectedDeal', 'leadDeals', 'paymentMethods', 'currencies', 'leadId'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /** Deals of this tenant, further narrowed to the caller's own leads for a Sales Employee. */
    private function searchableDeals($usr = null)
    {
        $usr = $usr ?: \Auth::user();
        $query = Deal::where('created_by', $usr->ownerId());

        if ($this->restrictedToOwnLeads($usr)) {
            $query->whereIn('lead_id', $this->assignedLeadIdSubQuery($usr->id));
        }

        return $query;
    }

    /** Deals that may still receive a purchase (plus $keepDealId when editing). */
    private function selectableDealsQuery($leadId = null, $keepDealId = null)
    {
        $query = $this->searchableDeals()->where(function ($q) use ($keepDealId) {
            $q->whereDoesntHave('dealPurchase');
            if ($keepDealId) {
                $q->orWhere('id', $keepDealId);
            }
        });

        if ($leadId) {
            $query->where('lead_id', $leadId);
        }

        return $query;
    }

    /**
     * JSON search backing the deal picker on the index filter and the create/edit forms.
     * mode=free limits the results to deals without a purchase (create/edit).
     */
    public function dealsSearch(Request $request)
    {
        if (!\Auth::user()->can('manage deal')) {
            return response()->json([], 403);
        }

        $term = trim((string) $request->get('q', ''));

        // The create form passes the lead it was opened from. Without this the
        // picker offered every free deal in the company, so a lead with two
        // deals listed dozens of other people's.
        //
        // It can only narrow: selectableDealsQuery() is built on searchableDeals(),
        // which is already scoped to the tenant and, for a Sales Employee, to
        // their own leads.
        $leadId = $request->get('lead_id');

        $query = $request->get('mode') === 'free'
            ? $this->selectableDealsQuery($leadId ?: null, $request->get('except'))
            : $this->searchableDeals();

        if ($term !== '') {
            $query->where('name', 'like', '%' . $term . '%');
        }

        $deals = $query->orderBy('name')->limit(25)->get(['id', 'name']);

        return response()->json($deals->map(function ($deal) {
            return ['id' => $deal->id, 'text' => $deal->name];
        })->values());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (\Auth::user()->can('create deal')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'deal_id' => 'required|exists:deals,id|unique:deal_purchases,deal_id',
                    'price' => 'required|numeric|min:0',
                    'currency' => 'required|string|max:20',
                    'payment_method' => 'required|string|max:191',
                    'payment_type' => 'required|in:one-time,installment',
                    'paid_amount' => 'required|numeric|min:0|lte:price',
                    'purchase_date' => 'required|date',
                    'status' => 'required|in:pending,complete',
                    'products' => 'nullable|array',
                    'installments_data' => 'nullable|json',
                ],
                [
                    'deal_id.unique' => __('This deal already has a purchase record. Each deal can only have one purchase.'),
                    'paid_amount.lte' => __('Paid amount can not be greater than the total price.'),
                ]
            );

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->getMessageBag()->first());
            }

            // exists:deals,id above only proves the deal exists somewhere -- make sure
            // it is this tenant's deal before attaching a purchase to it.
            if (!$this->searchableDeals()->where('id', $request->deal_id)->exists()) {
                return redirect()->back()->withInput()->with('error', __('Deal not found or access denied.'));
            }

            $dealPurchase = new DealPurchase();
            $dealPurchase->deal_id = $request->deal_id;
            $dealPurchase->price = $request->price;
            $dealPurchase->currency = $request->currency;
            $dealPurchase->payment_method = $request->payment_method;
            $dealPurchase->payment_type = $request->payment_type;

            // Logic for paid_amount
            if ($request->payment_type == 'one-time') {
                $dealPurchase->paid_amount = $request->price;
            } else {
                $dealPurchase->paid_amount = $request->paid_amount;
            }

            $dealPurchase->status = $request->status;
            $dealPurchase->purchase_date = $request->purchase_date;
            $dealPurchase->created_by = \Auth::user()->ownerId();
            $dealPurchase->products = $request->products;
            $dealPurchase->save();

            // Handle installments if payment type is installment
            if ($request->payment_type == 'installment' && $request->has('installments_data')) {
                $installmentsData = json_decode($request->installments_data, true);

                if ($installmentsData && is_array($installmentsData)) {
                    // Calculate total installments amount
                    $totalInstallments = array_sum(array_column($installmentsData, 'amount'));

                    // Validate: paid_amount + installments should not exceed price
                    // If it does, adjust paid_amount to be: price - totalInstallments
                    $maxUpfront = $request->price - $totalInstallments;
                    if ($dealPurchase->paid_amount > $maxUpfront) {
                        $dealPurchase->paid_amount = max(0, $maxUpfront);
                        $dealPurchase->save();
                    }

                    foreach ($installmentsData as $installmentData) {
                        $installment = new \App\Models\Installment();
                        $installment->deal_purchase_id = $dealPurchase->id;
                        $installment->amount = $installmentData['amount'];
                        $installment->date = $installmentData['date'];
                        $installment->description = $installmentData['description'] ?? null;
                        $installment->status = $installmentData['status'] ?? 'unpaid';
                        $installment->created_by = \Auth::user()->ownerId();
                        $installment->save();
                    }
                }
            }

            // Check if we came from a lead page
            if ($request->has('lead_id') && $request->lead_id) {
                return redirect()->route('leads.show', $request->lead_id)->with('success', __('Deal Purchase successfully created.'));
            }

            return redirect()->route('deal-purchase.index')->with('success', __('Deal Purchase successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (\Auth::user()->can('manage deal')) {
            $dealPurchase = $this->findOwnedPurchase($id, ['deal.lead', 'installments', 'attachments']);
            return view('admin.deal_purchase.show', compact('dealPurchase'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id, Request $request)
    {
        if (\Auth::user()->can('edit deal')) {
            $dealPurchase = $this->findOwnedPurchase($id, ['deal', 'installments']);

            // Current deal + deals without purchases -- resolved by the picker, not plucked.
            $dealsCount = $this->selectableDealsQuery(null, $dealPurchase->deal_id)->count();
            $selectedDeal = $dealPurchase->deal;

            $paymentMethods = SalesMeetingPaymentMethod::all()->pluck('name', 'id');
            $currencies = Utility::currencies();

            // Get lead_id if passed from lead page
            $leadId = $request->query('lead_id');

            return view('admin.deal_purchase.edit', compact('dealPurchase', 'dealsCount', 'selectedDeal', 'paymentMethods', 'currencies', 'leadId'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit deal')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'deal_id' => 'required|exists:deals,id|unique:deal_purchases,deal_id,' . $id,
                    'price' => 'required|numeric|min:0',
                    'currency' => 'required|string|max:20',
                    'payment_method' => 'required|string|max:191',
                    'payment_type' => 'required|in:one-time,installment',
                    'paid_amount' => 'required|numeric|min:0|lte:price',
                    'purchase_date' => 'required|date',
                    'status' => 'required|in:pending,complete',
                    'products' => 'nullable|array',
                    'installments_data' => 'nullable|json',
                ],
                [
                    'deal_id.unique' => __('This deal already has a purchase record. Each deal can only have one purchase.'),
                    'paid_amount.lte' => __('Paid amount can not be greater than the total price.'),
                ]
            );

            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', $validator->getMessageBag()->first());
            }

            $dealPurchase = $this->findOwnedPurchase($id);

            if (!$this->searchableDeals()->where('id', $request->deal_id)->exists()) {
                return redirect()->back()->withInput()->with('error', __('Deal not found or access denied.'));
            }

            $dealPurchase->deal_id = $request->deal_id;
            $dealPurchase->price = $request->price;
            $dealPurchase->currency = $request->currency;
            $dealPurchase->payment_method = $request->payment_method;
            $dealPurchase->payment_type = $request->payment_type;

            // Logic for paid_amount
            if ($request->payment_type == 'one-time') {
                $dealPurchase->paid_amount = $request->price;

                // Delete all installments if switching to one-time
                $dealPurchase->installments()->delete();
            } else {
                $dealPurchase->paid_amount = $request->paid_amount;

                // Handle installments synchronization
                if ($request->has('installments_data')) {
                    $installmentsData = json_decode($request->installments_data, true);

                    if ($installmentsData && is_array($installmentsData)) {
                        // Calculate total installments amount
                        $totalInstallments = array_sum(array_column($installmentsData, 'amount'));

                        // Validate: paid_amount + installments should not exceed price
                        // If it does, adjust paid_amount to be: price - totalInstallments
                        $maxUpfront = $request->price - $totalInstallments;
                        if ($dealPurchase->paid_amount > $maxUpfront) {
                            $dealPurchase->paid_amount = max(0, $maxUpfront);
                        }

                        // Track which installments to keep
                        $existingIds = [];

                        foreach ($installmentsData as $installmentData) {
                            if (isset($installmentData['id']) && !empty($installmentData['id'])) {
                                // Update existing installment
                                $installment = \App\Models\Installment::find($installmentData['id']);
                                if ($installment && $installment->deal_purchase_id == $dealPurchase->id) {
                                    $installment->amount = $installmentData['amount'];
                                    $installment->date = $installmentData['date'];
                                    $installment->description = $installmentData['description'] ?? null;
                                    $installment->status = $installmentData['status'] ?? $installment->status ?? 'unpaid';
                                    $installment->save();
                                    $existingIds[] = $installment->id;
                                }
                            } else {
                                // Create new installment
                                $installment = new \App\Models\Installment();
                                $installment->deal_purchase_id = $dealPurchase->id;
                                $installment->amount = $installmentData['amount'];
                                $installment->date = $installmentData['date'];
                                $installment->description = $installmentData['description'] ?? null;
                                $installment->status = $installmentData['status'] ?? 'unpaid';
                                $installment->created_by = \Auth::user()->ownerId();
                                $installment->save();
                                $existingIds[] = $installment->id;
                            }
                        }

                        // Delete installments that are not in the list
                        $dealPurchase->installments()->whereNotIn('id', $existingIds)->delete();
                    }
                }
            }

            $dealPurchase->status = $request->status;
            $dealPurchase->purchase_date = $request->purchase_date;
            $dealPurchase->products = $request->products;
            $dealPurchase->save();

            // Check if we came from a lead page
            if ($request->has('lead_id') && $request->lead_id) {
                return redirect()->route('leads.show', $request->lead_id)->with('success', __('Deal Purchase successfully updated.'));
            }

            return redirect()->route('deal-purchase.index')->with('success', __('Deal Purchase successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (\Auth::user()->can('delete deal')) {
            $dealPurchase = $this->findOwnedPurchase($id);

            $dealPurchase->delete();

            // Check if we came from a lead page
            if (request()->has('lead_id') && request()->lead_id) {
                return redirect()->route('leads.show', request()->lead_id)->with('success', __('Deal Purchase successfully deleted.'));
            }

            return redirect()->route('deal-purchase.index')->with('success', __('Deal Purchase successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function getProducts($deal_id)
    {
        if (!\Auth::user()->can('manage deal')) {
            return response()->json([], 403);
        }

        // Scoped: this used to read the products of ANY deal id, in any tenant.
        $deal = $this->searchableDeals()->where('id', $deal_id)->first();
        if ($deal) {
            $products = $deal->products(); // This returns a collection of ProductService
            // Return HTML or JSON?
            // User request: "products= these will be the products included in the deal..."
            // I'll return a JSON list of names and prices to render in JS.

            $productList = [];
            foreach ($products as $product) {
                $productList[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->sale_price, // Assuming sale_price exists
                ];
            }
            return response()->json($productList);
        }

        return response()->json(['error' => __('Deal not found or access denied.')], 404);
    }

    // Deal Purchase Attachments

    /**
     * Upload attachment for a deal purchase.
     */
    public function uploadAttachment($id, Request $request)
    {
        if (!\Auth::user()->can('edit deal')) {
            return response()->json(['is_success' => false, 'error' => __('Permission Denied.')], 401);
        }

        $dealPurchase = $this->findOwnedPurchase($id);

        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,txt,doc,docx,pdf'
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $storagePath = 'deal_purchase_attachments/' . $id;

        $file->storeAs($storagePath, $fileName, 'public');

        $attachment = DealPurchaseAttachment::create([
            'deal_purchase_id' => $id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $storagePath . '/' . $fileName,
            'file_type' => $this->getFileType($file->getClientMimeType()),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => \Auth::id(),
        ]);

        return response()->json([
            'is_success' => true,
            'success_msg' => __('File uploaded successfully'),
            'attachment' => $attachment,
            'download' => route('deal-purchase.attachment.download', [$id, $attachment->id]),
            'delete' => route('deal-purchase.attachment.delete', [$id, $attachment->id]),
        ]);
    }

    /**
     * Download attachment.
     */
    public function downloadAttachment($id, $attachmentId)
    {
        // This route had no authorisation at all: any logged-in user could pull
        // any tenant's invoice by guessing ids.
        if (!\Auth::user()->can('manage deal')) {
            abort(403);
        }

        $dealPurchase = $this->findOwnedPurchase($id);
        $attachment = DealPurchaseAttachment::where('deal_purchase_id', $id)->findOrFail($attachmentId);

        // Files are stored in public disk
        $filePath = storage_path('app/public/' . $attachment->file_path);

        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', __('File not found.'));
        }

        return response()->download($filePath, $attachment->file_name);
    }

    /**
     * Serve attachment inline (for displaying images).
     */
    public function serveAttachment($id, $attachmentId)
    {
        if (!\Auth::user()->can('manage deal')) {
            abort(403);
        }

        $dealPurchase = $this->findOwnedPurchase($id);
        $attachment = DealPurchaseAttachment::where('deal_purchase_id', $id)->findOrFail($attachmentId);

        // Files are stored in public disk
        $filePath = storage_path('app/public/' . $attachment->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'File not found.');
        }

        return response()->file($filePath, [
            'Content-Type' => $attachment->mime_type,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Delete attachment.
     */
    public function deleteAttachment($id, $attachmentId)
    {
        if (!\Auth::user()->can('edit deal')) {
            return response()->json(['is_success' => false, 'error' => __('Permission Denied.')], 401);
        }

        $dealPurchase = $this->findOwnedPurchase($id);

        $attachment = DealPurchaseAttachment::where('deal_purchase_id', $id)->findOrFail($attachmentId);

        // Delete file from public storage
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json([
            'is_success' => true,
            'success_msg' => __('File deleted successfully'),
        ]);
    }

    /**
     * Determine file type based on MIME type.
     */
    private function getFileType($mimeType)
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        return 'document';
    }
}

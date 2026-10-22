<?php

namespace App\Http\Controllers;

use App\Models\CustomField;
use App\Models\Deal;
use App\Models\DealAttachment;
use App\Models\Label;
use App\Models\ProductService;
use App\Models\User;
use App\Models\Lead;
use App\Models\Utility;
use App\Services\LeadScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DealController extends Controller
{
    /**
     * Display a listing of the redeal.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $usr = \Auth::user();

        if ($usr->can('manage deal')) {
            $query = Deal::where('created_by', '=', $usr->ownerId());

            // Which leads this user may see, resolved as a SQL subquery instead of
            // pluck()->toArray(): on a big tenant the old code pulled every assigned
            // lead id into PHP and sent it back as a multi-thousand element IN (...).
            $isSalesManager = $usr->hasRole('Sales Manager') || $usr->hasRole('sales manager');
            $visibleLeadIds = $this->visibleLeadIdSubQuery($usr);

            if ($visibleLeadIds === false) {
                $query->whereRaw('1 = 0');
            } elseif ($visibleLeadIds !== null) {
                $query->whereIn('lead_id', $visibleLeadIds);
            }

            // Apply Filters
            if ($request->name) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }
            if ($request->lead_id) {
                $query->where('lead_id', $request->lead_id);
            }
            if ($request->stage_id) {
                $query->where('stage_id', $request->stage_id);
            }
            if ($request->user_id) {
                // Filter by user - subquery over user_leads
                $query->whereIn('lead_id', \DB::table('user_leads')
                    ->select('lead_id')
                    ->where('user_id', $request->user_id));
            }
            if ($request->products) {
                $products_ids = explode(',', $request->products);
                $query->where(function ($q) use ($products_ids) {
                    foreach ($products_ids as $id) {
                        $q->orWhereRaw("FIND_IN_SET(?, deals.products)", [$id]);
                    }
                });
            }
            if ($request->date_from && $request->date_to) {
                $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
            } elseif ($request->date_from) {
                $query->where('created_at', '>=', $request->date_from);
            } elseif ($request->date_to) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $deals = $query->with(['lead', 'lead.users', 'stage'])
                ->withCount('attachments')
                ->orderBy('id', 'desc')
                ->paginate(20)
                ->appends($request->query());

            // Pre-load product names for current page (avoids N+1)
            $allProductIds = $deals->flatMap(function ($deal) {
                return $deal->products ? explode(',', $deal->products) : [];
            })->unique()->filter()->values()->toArray();
            $productsMap = !empty($allProductIds)
                ? ProductService::whereIn('id', $allProductIds)->pluck('name', 'id')
                : collect([]);

            // The Lead filter is an AJAX autocomplete (deals.leads.search). The full
            // lead list is NEVER rendered here: it produced one <option> per lead and
            // then Choices.js built a DOM node for each one, which froze the browser.
            // Only the currently filtered lead is needed, to label the input.
            $selectedLead = null;
            if ($request->lead_id) {
                $selectedLead = Lead::where('created_by', $usr->ownerId())
                    ->where('id', $request->lead_id)
                    ->first(['id', 'name']);
            }

            $stages = \App\Models\Stage::where('created_by', $usr->ownerId())->orderBy('order')->pluck('name', 'id');
            $products = ProductService::where('created_by', $usr->ownerId())->pluck('name', 'id');

            // The Employee filter offers sales people only, using the one definition
            // in LeadScope that the leads page also uses. The list here used to be a
            // hard-coded set of user types and role names, so anyone who sits in a
            // Sales department without one of those exact labels was missing from it.
            $usersQuery = LeadScope::salesUsersQuery($usr);
            if ($isSalesManager && $usr->sales_group) {
                // A Sales Manager still only sees their own group.
                $usersQuery->where('users.sales_group', $usr->sales_group);
            }
            $users = $usersQuery->get()->pluck('name', 'id');

            if ($request->ajax()) {
                return view('deals._table', compact('deals', 'productsMap'));
            }

            return view('deals.index', compact('deals', 'selectedLead', 'stages', 'products', 'users', 'productsMap'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }



    /**
     * A lead must exist AND belong to the current tenant.
     */
    private function leadExistsRule($usr)
    {
        return \Illuminate\Validation\Rule::exists('leads', 'id')
            ->where('created_by', $usr->ownerId());
    }

    /**
     * Lead ids this user is allowed to see deals/leads for.
     *
     * null  => no restriction (may see everything in the tenant)
     * false => may see nothing
     * otherwise a query builder selecting lead_id, usable as a subquery.
     */
    private function visibleLeadIdSubQuery($usr)
    {
        $isSalesManager = $usr->hasRole('Sales Manager') || $usr->hasRole('sales manager');

        // Sales Manager is checked first, exactly as the original index() did:
        // a manager stays limited to their group even if they can 'view all leads'.
        if ($isSalesManager) {
            if (!$usr->sales_group) {
                return false;
            }

            return \DB::table('user_leads')
                ->select('lead_id')
                ->whereIn(
                    'user_id',
                    User::where('created_by', $usr->creatorId())
                        ->where('sales_group', $usr->sales_group)
                        ->select('id')
                );
        }

        $canViewAllDeals = (
            $usr->type == 'company' ||
            $usr->type == 'CSO' ||
            $usr->type == 'Alaa,Saba,aber' ||
            $usr->type == 'fadi' ||
            $usr->can('view all leads')
        );

        if ($canViewAllDeals) {
            return null;
        }

        return \DB::table('user_leads')
            ->select('lead_id')
            ->where('user_id', $usr->id);
    }

    /**
     * Autocomplete source for the Lead pickers on the deal screens.
     * Returns at most 25 rows, so neither the payload nor the widget grows with
     * the size of the leads table.
     */
    public function leadsSearch(Request $request)
    {
        $usr = \Auth::user();

        if (!$usr->can('manage deal') && !$usr->can('create deal') && !$usr->can('edit deal')) {
            return response()->json([], 401);
        }

        $visibleLeadIds = $this->visibleLeadIdSubQuery($usr);
        if ($visibleLeadIds === false) {
            return response()->json([]);
        }

        $query = Lead::where('created_by', '=', $usr->ownerId());
        if ($visibleLeadIds !== null) {
            $query->whereIn('id', $visibleLeadIds);
        }

        $term = trim((string) $request->get('q'));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                    ->orWhere('phone', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            });
        }

        $leads = $query->orderBy('id', 'desc')->limit(25)->get(['id', 'name', 'phone']);

        // name and phone are sent apart so the picker can put them on their own
        // lines. Joined into one "name - phone" string they rendered in the wrong
        // order for Arabic names, because the bidirectional algorithm moved the
        // digits to the other end of the line.
        return response()->json(
            $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => (string) $lead->phone,
                    // kept so a browser holding the previous script still shows something
                    'text' => $lead->name . ($lead->phone ? ' - ' . $lead->phone : ''),
                ];
            })->values()
        );
    }

    /**
     * Show the form for creating a new redeal.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (\Auth::user()->can('create deal')) {
            $stages = \App\Models\Stage::where('created_by', '=', \Auth::user()->ownerId())->orderBy('order')->get()->pluck('name', 'id');
            $customFields = CustomField::where('module', '=', 'deal')->get();
            // Get ALL products for the dropdown
            $products = ProductService::where('created_by', '=', \Auth::user()->ownerId())->get()->pluck('name', 'id');

            return view('deals.create', compact('stages', 'customFields', 'products'));
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    /**
     * Store a newly created redeal in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $usr = \Auth::user();
        if ($usr->can('create deal')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    // 'exists:leads,id' alone let a deal be attached to another
                    // company's lead, so the rule is scoped to the tenant.
                    'lead_id' => ['required', $this->leadExistsRule($usr)],
                    'stage_id' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $lead = Lead::find($request->lead_id);
            $stage = \App\Models\Stage::find($request->stage_id);

            $deal = new Deal();
            $deal->name = $request->name;
            $deal->lead_id = $request->lead_id;
            if ($request->products) {
                $deal->products = implode(',', array_filter($request->products));
            }
            $deal->created_by = $usr->ownerId();
            $deal->added_by = $usr->id; // The actual employee who created this deal
            $deal->stage_id = $request->stage_id;
            $deal->status = $stage ? $stage->name : null; // Set status from stage name for consistency
            $deal->pipeline_id = $lead->pipeline_id ?? 0; // Default to 0 or handled elsewhere if pipeline removed
            $deal->notes = $request->notes;
            $deal->datetime = $request->datetime;
            $deal->save();

            CustomField::saveData($deal, $request->customField);

            return redirect()->back()->with('success', __('Deal successfully created!'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Display the specified redeal.
     *
     * @param \App\Deal $deal
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Deal $deal)
    {
        // Without these two checks any logged-in user could open any deal id,
        // including deals belonging to another company.
        if (!\Auth::user()->can('view deal')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        if ($deal->created_by != \Auth::user()->ownerId()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        if ($deal->is_active) {
            $customFields = CustomField::where('module', '=', 'deal')->get();
            $deal->customField = CustomField::getData($deal, 'deal')->toArray();

            return view('deals.show', compact('deal', 'customFields'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for editing the specified redeal.
     *
     * @param \App\Deal $deal
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Deal $deal)
    {
        if (\Auth::user()->can('edit deal')) {
            if ($deal->created_by == \Auth::user()->ownerId()) {
                $deal->load('lead:id,name');
                $deal->customField = CustomField::getData($deal, 'deal');
                $customFields = CustomField::where('module', '=', 'deal')->get();

                $deal->products = explode(',', (string) $deal->products);
                $stages = \App\Models\Stage::where('created_by', '=', \Auth::user()->ownerId())->orderBy('order')->get()->pluck('name', 'id');
                // Get ALL products for the dropdown
                $products = ProductService::where('created_by', '=', \Auth::user()->ownerId())->get()->pluck('name', 'id');

                return view('deals.edit', compact('deal', 'customFields', 'stages', 'products'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    /**
     * Update the specified redeal in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Deal $deal
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Deal $deal)
    {
        if (\Auth::user()->can('edit deal')) {
            if ($deal->created_by == \Auth::user()->ownerId()) {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'lead_id' => ['required', $this->leadExistsRule(\Auth::user())],
                        'stage_id' => 'required',
                    ]
                );

                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $deal->name = $request->name;
                $deal->lead_id = $request->lead_id;

                if ($request->products) {
                    $deal->products = implode(",", array_filter($request->products));
                } else {
                    $deal->products = null;
                }
                $deal->notes = $request->notes;
                $deal->stage_id = $request->stage_id;
                // Update status from stage name for consistency
                $stage = \App\Models\Stage::find($request->stage_id);
                $deal->status = $stage ? $stage->name : null;
                $deal->datetime = $request->datetime;
                $deal->save();

                CustomField::saveData($deal, $request->customField);

                return redirect()->back()->with('success', __('Deal successfully updated!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Remove the specified redeal from storage.
     *
     * @param \App\Deal $deal
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Deal $deal)
    {
        if (\Auth::user()->can('delete deal')) {
            if ($deal->created_by == \Auth::user()->ownerId()) {
                // Store lead_id before deleting the deal
                $leadId = $deal->lead_id;
                $deal->delete();

                // If there's a referer and it contains the lead show page, redirect there
                $referer = request()->headers->get('referer');
                if ($leadId && $referer && str_contains($referer, '/leads/' . $leadId)) {
                    return redirect()->route('leads.show', $leadId)->with('success', __('Deal successfully deleted!'));
                }

                return redirect()->route('deals.index')->with('success', __('Deal successfully deleted!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }



    private static $dealData = NUll;
    public function deal($item)
    {
        if (self::$dealData == null) {
            $deal = Deal::find($item);
            self::$dealData = $deal;
        }
        return self::$dealData;
    }

    public function labels($id)
    {
        if (\Auth::user()->can('edit deal')) {
            $deal = Deal::find($id);
            if ($deal->created_by == \Auth::user()->ownerId()) {
                $labels = Label::where('pipeline_id', '=', $deal->pipeline_id)->where('created_by', \Auth::user()->creatorId())->get();
                $selected = $deal->labels();
                if ($selected) {
                    $selected = $selected->pluck('name', 'id')->toArray();
                } else {
                    $selected = [];
                }

                return view('deals.labels', compact('deal', 'labels', 'selected'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function labelStore($id, Request $request)
    {
        if (\Auth::user()->can('edit deal')) {
            $deal = Deal::find($id);
            if ($deal->created_by == \Auth::user()->ownerId()) {
                if ($request->labels) {
                    $deal->labels = implode(',', $request->labels);
                } else {
                    $deal->labels = $request->labels;
                }
                $deal->save();

                return redirect()->back()->with('success', __('Labels successfully updated!'));
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }





    public function productEdit($id)
    {
        if (\Auth::user()->can('edit deal')) {
            $deal = Deal::find($id);
            if ($deal->created_by == \Auth::user()->ownerId()) {
                // Get ALL products except those already assigned to the deal
                $products = ProductService::where('created_by', '=', \Auth::user()->ownerId())
                    ->whereNOTIn('id', explode(',', $deal->products))
                    ->get()
                    ->pluck('name', 'id');

                return view('deals.products', compact('deal', 'products'));
            } else {
                return response()->json(['error' => __('Permission Denied.')], 401);
            }
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function productUpdate($id, Request $request)
    {
        $usr = \Auth::user();
        if ($usr->can('edit deal')) {
            $deal = Deal::find($id);

            if ($deal->created_by == $usr->ownerId()) {
                if (!empty($request->products)) {
                    $products = array_filter($request->products);
                    $old_products = explode(',', (string) $deal->products);
                    $deal->products = implode(',', array_unique(array_filter(array_merge($old_products, $products))));
                    $deal->save();
                }

                if (!empty($products) && !empty($request->products)) {
                    return redirect()->back()->with('success', __('Products successfully updated!'))->with('status', 'products');
                } else {
                    return redirect()->back()->with('error', __('Please Select Valid Product!'))->with('status', 'general');
                }
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
        }
    }

    public function productDestroy($id, $product_id)
    {
        if (\Auth::user()->can('edit deal')) {
            $deal = Deal::find($id);
            if ($deal->created_by == \Auth::user()->ownerId()) {
                $products = explode(',', (string) $deal->products);
                foreach ($products as $key => $product) {
                    if ($product_id == $product) {
                        unset($products[$key]);
                    }
                }
                $deal->products = implode(',', $products);
                $deal->save();

                return redirect()->back()->with('success', __('Products successfully deleted!'))->with('status', 'products');
            } else {
                return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'))->with('status', 'products');
        }
    }



    public function noteStore($id, Request $request)
    {
        if (\Auth::user()->can('edit deal')) {
            $deal = Deal::find($id);
            if ($deal->created_by == \Auth::user()->ownerId()) {
                $deal->notes = $request->notes;
                $deal->save();

                return response()->json(
                    [
                        'is_success' => true,
                        'success' => __('Note successfully saved!'),
                    ],
                    200
                );
            } else {
                return response()->json(
                    [
                        'is_success' => false,
                        'error' => __('Permission Denied.'),
                    ],
                    401
                );
            }
        } else {
            return response()->json(
                [
                    'is_success' => false,
                    'error' => __('Permission Denied.'),
                ],
                401
            );
        }
    }











    public function changeStatus(Request $request, $id)
    {
        $deal = Deal::where('id', '=', $id)->first();
        $deal->status = $request->deal_status;
        $deal->save();

        return redirect()->back();
    }

    // Deal Calls


    // Deal email


    // Deal Attachments

    /**
     * Upload attachment for a deal.
     */
    public function uploadAttachment($id, Request $request)
    {
        if (!\Auth::user()->can('edit deal')) {
            return response()->json(['is_success' => false, 'error' => __('Permission Denied.')], 401);
        }

        $deal = Deal::findOrFail($id);
        if ($deal->created_by != \Auth::user()->ownerId()) {
            return response()->json(['is_success' => false, 'error' => __('Permission Denied.')], 401);
        }

        $request->validate([
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,txt,doc,docx,pdf'
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $storagePath = 'deal_attachments/' . $id;

        $file->storeAs($storagePath, $fileName, 'public');

        $attachment = DealAttachment::create([
            'deal_id' => $id,
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
            'download' => route('deals.attachment.download', [$id, $attachment->id]),
            'delete' => route('deals.attachment.delete', [$id, $attachment->id]),
        ]);
    }

    /**
     * Download attachment.
     */
    public function downloadAttachment($id, $attachmentId)
    {
        $deal = Deal::findOrFail($id);

        // Only the owning tenant may download
        if ($deal->created_by != \Auth::user()->ownerId()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $attachment = DealAttachment::where('deal_id', $id)->findOrFail($attachmentId);

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
        $deal = Deal::findOrFail($id);

        // Only the owning tenant may view
        if ($deal->created_by != \Auth::user()->ownerId()) {
            abort(403);
        }

        $attachment = DealAttachment::where('deal_id', $id)->findOrFail($attachmentId);

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

        $deal = Deal::findOrFail($id);
        if ($deal->created_by != \Auth::user()->ownerId()) {
            return response()->json(['is_success' => false, 'error' => __('Permission Denied.')], 401);
        }

        $attachment = DealAttachment::where('deal_id', $id)->findOrFail($attachmentId);

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

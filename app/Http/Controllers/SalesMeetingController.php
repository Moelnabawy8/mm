<?php

namespace App\Http\Controllers;

use App\Models\SalesMeeting;
use App\Models\SalesMeetingType;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SalesMeetingController extends Controller
{
    private function isSalesEmployee($usr)
    {
        return $usr->hasRole('Sales Employee') || $usr->hasRole('sales employee');
    }

    /**
     * Leads this user may work with, as a query. Sales Employees are limited to
     * their assigned leads through a subquery, never a plucked id list.
     */
    private function leadQuery($usr)
    {
        $query = Lead::where('created_by', $usr->creatorId());
        if ($this->isSalesEmployee($usr)) {
            $query->whereIn('id', \DB::table('user_leads')->select('lead_id')->where('user_id', $usr->id));
        }

        return $query;
    }

    /**
     * Meetings this user may see. Every route that takes a meeting id goes
     * through here, so another company's meeting (or, for a Sales Employee,
     * a meeting of an unassigned lead) resolves to null instead of loading.
     */
    private function meetingQuery($usr)
    {
        $query = SalesMeeting::where('created_by', $usr->creatorId());
        if ($this->isSalesEmployee($usr)) {
            $query->whereIn('lead_id', \DB::table('user_leads')->select('lead_id')->where('user_id', $usr->id));
        }

        return $query;
    }

    private function canUseLeads($usr)
    {
        return $usr->can('manage lead') || $usr->can('create lead') || $usr->can('edit lead');
    }

    public function index(Request $request)
    {
        if (\Auth::user()->can('manage lead')) {
            $usr = \Auth::user();
            $query = $this->meetingQuery($usr);

            // Apply Filters
            if ($request->title) {
                $query->where('title', 'like', '%' . $request->title . '%');
            }
            if ($request->lead_id) {
                $query->where('lead_id', $request->lead_id);
            }
            if ($request->deal_id) {
                $query->where('deal_id', $request->deal_id);
            }
            if ($request->meeting_type_id) {
                $query->where('meeting_type_id', $request->meeting_type_id);
            }
            if ($request->date_from && $request->date_to) {
                $query->whereBetween('datetime', [$request->date_from, $request->date_to . ' 23:59:59']);
            } elseif ($request->date_from) {
                $query->where('datetime', '>=', $request->date_from);
            } elseif ($request->date_to) {
                $query->where('datetime', '<=', $request->date_to . ' 23:59:59');
            }

            // Stats via aggregate queries (not affected by pagination)
            $stats = [
                'total_meetings' => (clone $query)->count(),
                'total_calls' => (clone $query)->whereHas('meetingType', function ($q) { $q->where('name', 'Call'); })->count(),
                'total_presentations' => (clone $query)->whereHas('meetingType', function ($q) { $q->where('name', 'Presentation'); })->count(),
                'total_clients' => (clone $query)->distinct('lead_id')->count('lead_id'),
                'total_deals' => (clone $query)->whereNotNull('deal_id')->distinct('deal_id')->count('deal_id'),
            ];

            // lead.users is the assignment pivot the Assigned Staff column reads.
            $meetings = $query->with(['lead.users', 'deal', 'meetingType'])
                ->orderBy('id', 'desc')
                ->paginate(20)
                ->appends($request->query());

            if ($request->ajax()) {
                return view('sales_meetings._table', compact('meetings', 'stats'));
            }

            // The Lead / Deal filters are AJAX pickers. The full lead and deal lists
            // are NEVER rendered: one <option> per row plus a Choices.js node for each
            // froze the browser. Only the currently filtered values are labelled.
            $selectedLead = $request->lead_id
                ? $this->leadQuery($usr)->where('id', $request->lead_id)->first(['id', 'name'])
                : null;
            $selectedDeal = $request->deal_id
                ? Deal::whereIn('lead_id', $this->leadQuery($usr)->select('id'))->where('id', $request->deal_id)->first(['id', 'name'])
                : null;
            $types = SalesMeetingType::where('created_by', $usr->creatorId())->pluck('name', 'id');

            return view('sales_meetings.index', compact('meetings', 'selectedLead', 'selectedDeal', 'types', 'stats'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Autocomplete source for the Lead pickers. At most 25 rows.
     */
    public function leadsSearch(Request $request)
    {
        $usr = \Auth::user();
        if (!$this->canUseLeads($usr)) {
            return response()->json([], 401);
        }

        $query = $this->leadQuery($usr);
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
        return response()->json($leads->map(function ($lead) {
            return [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => (string) $lead->phone,
                // kept so a browser holding the previous script still shows something
                'text' => $lead->name . ($lead->phone ? ' - ' . $lead->phone : ''),
            ];
        })->values());
    }

    /**
     * Autocomplete source for the Deal filter. Scoped through the lead, so a
     * deal is only found when its lead is visible to this user.
     */
    public function dealsSearch(Request $request)
    {
        $usr = \Auth::user();
        if (!$this->canUseLeads($usr)) {
            return response()->json([], 401);
        }

        $query = Deal::whereIn('lead_id', $this->leadQuery($usr)->select('id'));
        $term = trim((string) $request->get('q'));
        if ($term !== '') {
            $query->where('name', 'like', '%' . $term . '%');
        }

        $deals = $query->orderBy('id', 'desc')->limit(25)->get(['id', 'name']);

        return response()->json($deals->map(function ($deal) {
            return ['id' => $deal->id, 'text' => $deal->name];
        })->values());
    }

    public function create(Request $request)
    {
        if (\Auth::user()->can('create lead')) {
            $usr = \Auth::user();
            $types = SalesMeetingType::where('created_by', $usr->creatorId())->pluck('name', 'id');
            $products = ProductService::where('created_by', $usr->creatorId())->pluck('name', 'id');

            $lead_id = (int) $request->query('lead_id', 0);
            $deals = [];
            if ($lead_id > 0) {
                if ($this->leadQuery($usr)->where('id', $lead_id)->exists()) {
                    $deals = Deal::where('lead_id', $lead_id)->pluck('name', 'id');
                } else {
                    $lead_id = 0;
                }
            }

            return view('sales_meetings.create', compact('types', 'lead_id', 'deals', 'products'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    private function validator(Request $request, $usr)
    {
        return \Validator::make(
            $request->all(),
            [
                'title' => 'required|max:255',
                // Plain 'required' let a meeting point at another company's lead or
                // at a deal of a different lead.
                'lead_id' => ['required', Rule::exists('leads', 'id')->where('created_by', $usr->creatorId())],
                'deal_id' => ['nullable', Rule::exists('deals', 'id')->where('lead_id', $request->lead_id)],
                'meeting_type_id' => 'required',
                'datetime' => 'required|date',
                'duration' => 'nullable|integer|min:1',
                // Files land in a web-served directory, so executable types are refused.
                'attachment' => 'nullable|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,gif,webp,zip,rar',
            ]
        );
    }

    private function fillAndSave(SalesMeeting $meeting, Request $request, $usr)
    {
        $meeting->title = $request->title;
        $meeting->lead_id = $request->lead_id;
        $meeting->deal_id = $request->deal_id ?: null;
        $meeting->meeting_type_id = $request->meeting_type_id;
        $meeting->datetime = Carbon::parse($request->datetime);
        // An empty optional field must be NULL: '' into an integer column is a 500 under strict mode.
        $meeting->duration = $request->duration !== null && $request->duration !== '' ? (int) $request->duration : null;
        $meeting->link = $request->link;
        $meeting->notes = $request->notes;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $original = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', basename($file->getClientOriginalName()));
            $fileName = time() . '_' . $original;
            $file->storeAs('sales_meeting_files', $fileName); // Storage path
            $meeting->attachment = $fileName;
        }

        $meeting->save();

        // Only this company's products can be attached.
        $productIds = ProductService::where('created_by', $usr->creatorId())
            ->whereIn('id', array_filter((array) $request->products))
            ->pluck('id');
        $meeting->products()->sync($productIds);
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create lead')) {
            $usr = \Auth::user();
            $validator = $this->validator($request, $usr);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            if (!$this->leadQuery($usr)->where('id', $request->lead_id)->exists()) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            $meeting = new SalesMeeting();
            $meeting->created_by = $usr->creatorId();
            $this->fillAndSave($meeting, $request, $usr);

            return redirect()->back()->with('success', __('Meeting successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id)
    {
        if (\Auth::user()->can('edit lead')) {
            $usr = \Auth::user();
            $meeting = $this->meetingQuery($usr)->with(['products', 'lead:id,name'])->find($id);
            if (!$meeting) {
                return response()->json(['error' => __('Meeting not found.')], 404);
            }

            $types = SalesMeetingType::where('created_by', $usr->creatorId())->pluck('name', 'id');
            $deals = $meeting->lead_id ? Deal::where('lead_id', $meeting->lead_id)->pluck('name', 'id') : [];
            $products = ProductService::where('created_by', $usr->creatorId())->pluck('name', 'id');

            return view('sales_meetings.edit', compact('meeting', 'types', 'deals', 'products'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit lead')) {
            $usr = \Auth::user();
            $meeting = $this->meetingQuery($usr)->find($id);
            if (!$meeting) {
                return redirect()->back()->with('error', __('Meeting not found.'));
            }

            $validator = $this->validator($request, $usr);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            if (!$this->leadQuery($usr)->where('id', $request->lead_id)->exists()) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            $this->fillAndSave($meeting, $request, $usr);

            return redirect()->back()->with('success', __('Meeting successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete lead')) {
            $meeting = $this->meetingQuery(\Auth::user())->find($id);
            if (!$meeting) {
                return redirect()->back()->with('error', __('Meeting not found.'));
            }
            $meeting->delete();

            return redirect()->back()->with('success', __('Meeting successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getDeals(Request $request)
    {
        $usr = \Auth::user();
        if (!$this->canUseLeads($usr)) {
            return response()->json([], 401);
        }
        // Without this check any lead id, including another company's, returned its deals.
        if (!$request->lead_id || !$this->leadQuery($usr)->where('id', $request->lead_id)->exists()) {
            return response()->json([]);
        }

        $deals = Deal::where('lead_id', $request->lead_id)->pluck('name', 'id');
        return response()->json($deals);
    }

    public function show($id)
    {
        if (\Auth::user()->can('manage lead')) {
            $meeting = $this->meetingQuery(\Auth::user())->with(['lead', 'deal', 'meetingType', 'products'])->find($id);
            if (!$meeting) {
                return redirect()->route('sales-meetings.index')->with('error', __('Meeting not found.'));
            }
            return view('sales_meetings.show', compact('meeting'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Uploads were stored on the default disk (storage/app) while the links
     * pointed at /storage (the public disk), so they never opened. Serve the
     * file through here instead: tenant-checked, and found on either disk.
     */
    public function attachment($id)
    {
        $usr = \Auth::user();
        if (!$usr->can('manage lead')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $meeting = $this->meetingQuery($usr)->find($id);
        if ($meeting && $meeting->attachment) {
            $path = 'sales_meeting_files/' . basename($meeting->attachment);
            foreach (array_unique([config('filesystems.default'), 'local', 'public']) as $disk) {
                if (\Storage::disk($disk)->exists($path)) {
                    return \Storage::disk($disk)->download($path, $meeting->attachment);
                }
            }
        }

        return redirect()->back()->with('error', __('File not found.'));
    }

    public function noteStore($id, Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $meeting = $this->meetingQuery(\Auth::user())->find($id);
            if (!$meeting) {
                return response()->json(['is_success' => false, 'error' => __('Meeting not found.')], 404);
            }
            $meeting->notes = $request->notes;
            $meeting->save();

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
                    'error' => __('Permission denied.'),
                ],
                401
            );
        }
    }

    public function addProduct($id, Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $usr = \Auth::user();
            $meeting = $this->meetingQuery($usr)->find($id);
            if (!$meeting) {
                return redirect()->back()->with('error', __('Meeting not found.'));
            }

            if ($request->has('products') && count((array) $request->products) > 0) {
                $productIds = ProductService::where('created_by', $usr->creatorId())
                    ->whereIn('id', (array) $request->products)
                    ->pluck('id');
                // attach() on a product already linked hit the smp_unique index (500).
                $meeting->products()->syncWithoutDetaching($productIds);
            }

            return redirect()->back()->with('success', __('Products successfully added.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function removeProduct($id, $productId)
    {
        if (\Auth::user()->can('edit lead')) {
            $meeting = $this->meetingQuery(\Auth::user())->find($id);
            if (!$meeting) {
                return redirect()->back()->with('error', __('Meeting not found.'));
            }
            $meeting->products()->detach($productId);

            return redirect()->back()->with('success', __('Product successfully removed.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}

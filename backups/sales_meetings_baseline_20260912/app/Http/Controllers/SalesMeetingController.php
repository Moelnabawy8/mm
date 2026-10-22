<?php

namespace App\Http\Controllers;

use App\Models\SalesMeeting;
use App\Models\SalesMeetingType;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\ProductService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalesMeetingController extends Controller
{
    public function index(Request $request)
    {
        if (\Auth::user()->can('manage lead')) {
            $usr = \Auth::user();
            $query = SalesMeeting::where('created_by', '=', $usr->creatorId());

            // If user is a Sales Employee, only show meetings for their assigned leads
            if ($usr->hasRole('Sales Employee') || $usr->hasRole('sales employee')) {
                $userLeadIds = \App\Models\UserLead::where('user_id', $usr->id)->pluck('lead_id');
                $query->whereIn('lead_id', $userLeadIds);
            }

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

            $meetings = $query->with(['lead', 'deal', 'meetingType'])
                ->orderBy('id', 'desc')
                ->paginate(20)
                ->appends($request->query());

            // Filter options for the view
            // If user is a Sales Employee, only show their assigned leads in the filter
            if ($usr->hasRole('Sales Employee') || $usr->hasRole('sales employee')) {
                $userLeadIds = \App\Models\UserLead::where('user_id', $usr->id)->pluck('lead_id');
                $leads = Lead::where('created_by', $usr->creatorId())->whereIn('id', $userLeadIds)->pluck('name', 'id');
            } else {
                $leads = Lead::where('created_by', $usr->creatorId())->pluck('name', 'id');
            }
            $deals = Deal::where('created_by', $usr->ownerId())->pluck('name', 'id');
            $types = SalesMeetingType::where('created_by', $usr->creatorId())->pluck('name', 'id');

            if ($request->ajax()) {
                return view('sales_meetings._table', compact('meetings', 'stats'));
            }

            return view('sales_meetings.index', compact('meetings', 'leads', 'deals', 'types', 'stats'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create(Request $request)
    {
        if (\Auth::user()->can('create lead')) {
            $leads = Lead::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $types = SalesMeetingType::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $products = ProductService::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');

            $lead_id = $request->query('lead_id', 0);
            $deals = [];
            if ($lead_id > 0) {
                $deals = Deal::where('lead_id', $lead_id)->get()->pluck('name', 'id');
            }

            return view('sales_meetings.create', compact('leads', 'types', 'lead_id', 'deals', 'products'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create lead')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title' => 'required',
                    'lead_id' => 'required',
                    'meeting_type_id' => 'required',
                    'datetime' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $meeting = new SalesMeeting();
            $meeting->title = $request->title;
            $meeting->lead_id = $request->lead_id;
            $meeting->deal_id = $request->deal_id;
            $meeting->meeting_type_id = $request->meeting_type_id;
            $meeting->datetime = $request->datetime;
            $meeting->duration = $request->duration;
            $meeting->link = $request->link;
            $meeting->notes = $request->notes;
            $meeting->created_by = \Auth::user()->creatorId();

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->storeAs('sales_meeting_files', $fileName); // Storage path
                $meeting->attachment = $fileName;
            }

            $meeting->save();

            // Sync products
            if ($request->has('products')) {
                $meeting->products()->sync($request->products);
            }

            return redirect()->back()->with('success', __('Meeting successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id)
    {
        if (\Auth::user()->can('edit lead')) {
            $meeting = SalesMeeting::with('products')->find($id);
            $leads = Lead::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $types = SalesMeetingType::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $deals = Deal::where('lead_id', $meeting->lead_id)->get()->pluck('name', 'id');
            $products = ProductService::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');

            return view('sales_meetings.edit', compact('meeting', 'leads', 'types', 'deals', 'products'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit lead')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title' => 'required',
                    'lead_id' => 'required',
                    'meeting_type_id' => 'required',
                    'datetime' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $meeting = SalesMeeting::find($id);
            $meeting->title = $request->title;
            $meeting->lead_id = $request->lead_id;
            $meeting->deal_id = $request->deal_id;
            $meeting->meeting_type_id = $request->meeting_type_id;
            $meeting->datetime = $request->datetime;
            $meeting->duration = $request->duration;
            $meeting->link = $request->link;
            $meeting->notes = $request->notes;

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->storeAs('sales_meeting_files', $fileName);
                $meeting->attachment = $fileName;
            }

            $meeting->save();

            // Sync products
            if ($request->has('products')) {
                $meeting->products()->sync($request->products);
            } else {
                $meeting->products()->detach();
            }

            return redirect()->back()->with('success', __('Meeting successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete lead')) {
            $meeting = SalesMeeting::find($id);
            $meeting->delete();

            return redirect()->back()->with('success', __('Meeting successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getDeals(Request $request)
    {
        $deals = Deal::where('lead_id', $request->lead_id)->pluck('name', 'id');
        return response()->json($deals);
    }

    public function show($id)
    {
        if (\Auth::user()->can('manage lead')) {
            $meeting = SalesMeeting::with(['lead', 'deal', 'meetingType', 'products'])->find($id);
            return view('sales_meetings.show', compact('meeting'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function noteStore($id, Request $request)
    {
        if (\Auth::user()->can('edit lead')) {
            $meeting = SalesMeeting::find($id);
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
            $meeting = SalesMeeting::find($id);

            if ($request->has('products') && count($request->products) > 0) {
                $meeting->products()->attach($request->products);
            }

            return redirect()->back()->with('success', __('Products successfully added.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function removeProduct($id, $productId)
    {
        if (\Auth::user()->can('edit lead')) {
            $meeting = SalesMeeting::find($id);
            $meeting->products()->detach($productId);

            return redirect()->back()->with('success', __('Product successfully removed.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}

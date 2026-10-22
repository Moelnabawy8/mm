<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesMeeting;
use App\Models\SalesMeetingType;
use App\Models\Lead;
use App\Models\Deal;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SalesMeetingApiController extends Controller
{
    use ApiResponser;

    public function getMeetings(Request $request)
    {
        $query = SalesMeeting::where('created_by', Auth::user()->creatorId())
            ->with(['lead', 'deal', 'meetingType', 'products']);

        if ($request->search) {
            $s = $request->search;
            $query->where('title', 'like', "%{$s}%");
        } elseif ($request->title) {
            $query->where('title', 'like', "%{$request->title}%");
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

        $allMeetings = (clone $query)->get();
        $stats = [
            'total_meetings'      => $allMeetings->count(),
            'total_calls'         => $allMeetings->filter(fn($m) => optional($m->meetingType)->name === 'Call')->count(),
            'total_presentations' => $allMeetings->filter(fn($m) => optional($m->meetingType)->name === 'Presentation')->count(),
            'total_clients'       => $allMeetings->pluck('lead_id')->unique()->count(),
            'total_deals'         => $allMeetings->whereNotNull('deal_id')->pluck('deal_id')->unique()->count(),
        ];

        $perPage = (int) ($request->per_page ?? 15);
        $result  = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->success([
            'meetings'   => $result->items(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
            'stats' => $stats,
        ], 'Sales meetings retrieved.');
    }

    public function createMeeting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:190',
            'lead_id'          => 'required|exists:leads,id',
            'meeting_type_id'  => 'required|exists:sales_meeting_types,id',
            'datetime'         => 'required|date',
            'deal_id'          => 'nullable|exists:deals,id',
            'duration'         => 'nullable|string',
            'link'             => 'nullable|url',
            'notes'            => 'nullable|string',
            'products'         => 'nullable|array',
            'products.*'       => 'exists:product_services,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $meeting = new SalesMeeting();
        $meeting->title           = $request->title;
        $meeting->lead_id         = $request->lead_id;
        $meeting->deal_id         = $request->deal_id;
        $meeting->meeting_type_id = $request->meeting_type_id;
        $meeting->datetime        = $request->datetime;
        $meeting->duration        = $request->duration;
        $meeting->link            = $request->link;
        $meeting->notes           = $request->notes;
        $meeting->created_by      = Auth::user()->creatorId();
        $meeting->save();

        if ($request->has('products')) {
            $meeting->products()->sync($request->products);
        }

        $meeting->load(['lead', 'deal', 'meetingType', 'products']);

        return $this->success(['meeting' => $meeting], 'Sales meeting created successfully.');
    }

    public function getMeeting($id)
    {
        $meeting = SalesMeeting::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['lead', 'deal', 'meetingType', 'products'])
            ->first();

        if (!$meeting) {
            return $this->error('Sales meeting not found.', 404);
        }

        return $this->success(['meeting' => $meeting], 'Sales meeting retrieved.');
    }

    public function updateMeeting(Request $request, $id)
    {
        $meeting = SalesMeeting::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$meeting) {
            return $this->error('Sales meeting not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:190',
            'lead_id'          => 'required|exists:leads,id',
            'meeting_type_id'  => 'required|exists:sales_meeting_types,id',
            'datetime'         => 'required|date',
            'deal_id'          => 'nullable|exists:deals,id',
            'duration'         => 'nullable|string',
            'link'             => 'nullable|url',
            'notes'            => 'nullable|string',
            'products'         => 'nullable|array',
            'products.*'       => 'exists:product_services,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $meeting->title           = $request->title;
        $meeting->lead_id         = $request->lead_id;
        $meeting->deal_id         = $request->deal_id;
        $meeting->meeting_type_id = $request->meeting_type_id;
        $meeting->datetime        = $request->datetime;
        $meeting->duration        = $request->duration;
        $meeting->link            = $request->link;
        $meeting->notes           = $request->notes;
        $meeting->save();

        if ($request->has('products')) {
            $meeting->products()->sync($request->products);
        } else {
            $meeting->products()->detach();
        }

        $meeting->load(['lead', 'deal', 'meetingType', 'products']);

        return $this->success(['meeting' => $meeting], 'Sales meeting updated successfully.');
    }

    public function deleteMeeting($id)
    {
        $meeting = SalesMeeting::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->first();

        if (!$meeting) {
            return $this->error('Sales meeting not found.', 404);
        }

        $meeting->products()->detach();
        $meeting->delete();

        return $this->success([], 'Sales meeting deleted successfully.');
    }

    public function getMeetingTypes()
    {
        $types = SalesMeetingType::where('created_by', Auth::user()->creatorId())->get();
        return $this->success(['types' => $types], 'Meeting types retrieved.');
    }
}

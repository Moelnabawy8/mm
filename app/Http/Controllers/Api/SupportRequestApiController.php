<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\SupportRequest;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * API twin of the web SupportRequestController. Same permissions, same
 * requester/supporter rules and the same notifications, but the lead and the
 * supporter must belong to the caller's company: the web store accepts any
 * lead_id / supporter_id that exists in the whole database.
 */
class SupportRequestApiController extends Controller
{
    use ApiResponser;

    public function getSupportRequests(Request $request)
    {
        if (!Auth::user()->can('manage support request')) {
            return $this->error('Permission denied.', 403);
        }

        $userId = Auth::user()->id;
        $query = SupportRequest::with(['lead:id,name,phone,email', 'requester:id,name,email', 'supporter:id,name,email']);

        if ($request->view_type === 'requested') {
            $query->where('requester_id', $userId);
        } elseif ($request->view_type === 'assigned') {
            $query->where('supporter_id', $userId);
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('requester_id', $userId)->orWhere('supporter_id', $userId);
            });
        }

        foreach (['status', 'priority', 'supporter_id', 'lead_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $requests = $query->orderBy('id', 'desc')->paginate(min((int) $request->input('per_page', 20), 100));

        $stats = [
            'total_requested' => SupportRequest::where('requester_id', $userId)->count(),
            'total_assigned' => SupportRequest::where('supporter_id', $userId)->count(),
            'pending_assigned' => SupportRequest::where('supporter_id', $userId)->where('status', 'pending')->count(),
            'in_progress_assigned' => SupportRequest::where('supporter_id', $userId)->where('status', 'in_progress')->count(),
            'completed_assigned' => SupportRequest::where('supporter_id', $userId)->where('status', 'completed')->count(),
        ];

        return $this->success([
            'support_requests' => $requests->items(),
            'stats' => $stats,
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ], 'Support requests retrieved.');
    }

    public function createSupportRequest(Request $request)
    {
        if (!Auth::user()->can('create support request')) {
            return $this->error('Permission denied.', 403);
        }

        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|integer',
            'supporter_id' => 'required|integer',
            'notes' => 'required|string|min:10',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if ($request->supporter_id == Auth::user()->id) {
            return $this->error('You cannot request support from yourself.', 422);
        }
        if ($error = $this->checkLeadAndSupporter($request->lead_id, $request->supporter_id)) {
            return $this->error($error, 422);
        }

        $supportRequest = new SupportRequest();
        $supportRequest->lead_id = $request->lead_id;
        $supportRequest->requester_id = Auth::user()->id;
        $supportRequest->supporter_id = $request->supporter_id;
        $supportRequest->notes = $request->notes;
        $supportRequest->priority = $request->priority;
        $supportRequest->status = 'pending';
        $supportRequest->save();

        $this->notify($request->supporter_id, 'support_request_created', $supportRequest);

        return $this->success(['support_request' => $supportRequest->load(['lead:id,name', 'requester:id,name', 'supporter:id,name'])], 'Support request created successfully.', 201);
    }

    public function getSupportRequest($id)
    {
        if (!Auth::user()->can('manage support request')) {
            return $this->error('Permission denied.', 403);
        }

        $supportRequest = SupportRequest::with(['lead', 'requester:id,name,email', 'supporter:id,name,email'])->find($id);
        if (!$supportRequest || !$this->isParticipant($supportRequest)) {
            return $this->error('Support request not found.', 404);
        }

        return $this->success(['support_request' => $supportRequest], 'Support request retrieved.');
    }

    public function updateSupportRequest(Request $request, $id)
    {
        if (!Auth::user()->can('edit support request')) {
            return $this->error('Permission denied.', 403);
        }

        $supportRequest = SupportRequest::find($id);
        if (!$supportRequest || !$this->isParticipant($supportRequest)) {
            return $this->error('Support request not found.', 404);
        }

        $userId = Auth::user()->id;

        // The requester edits the request itself; the supporter answers it.
        if ($supportRequest->requester_id == $userId) {
            $validator = Validator::make($request->all(), [
                'lead_id' => 'required|integer',
                'supporter_id' => 'required|integer',
                'notes' => 'required|string|min:10',
                'priority' => 'required|in:low,normal,high,urgent',
                'status' => 'nullable|in:pending,cancelled',
            ]);
            if ($validator->fails()) {
                return $this->error($validator->errors()->first(), 422);
            }
            if ($request->supporter_id == $userId) {
                return $this->error('You cannot request support from yourself.', 422);
            }
            if ($error = $this->checkLeadAndSupporter($request->lead_id, $request->supporter_id)) {
                return $this->error($error, 422);
            }

            $supportRequest->lead_id = $request->lead_id;
            $supportRequest->supporter_id = $request->supporter_id;
            $supportRequest->notes = $request->notes;
            $supportRequest->priority = $request->priority;
            if ($request->status === 'cancelled') {
                $supportRequest->status = 'cancelled';
            }
            $supportRequest->save();

            return $this->success(['support_request' => $supportRequest], 'Support request updated successfully.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'response_notes' => 'nullable|string',
            'client_subscribed' => 'nullable|boolean',
            'subscription_amount' => 'nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $oldStatus = $supportRequest->status;
        $oldResponseNotes = $supportRequest->response_notes;

        $this->applyStatus($supportRequest, $request->status);
        if ($request->response_notes) {
            $supportRequest->response_notes = $request->response_notes;
        }
        if ($request->has('client_subscribed')) {
            $supportRequest->client_subscribed = $request->boolean('client_subscribed');
        }
        if ($request->has('subscription_amount')) {
            $supportRequest->subscription_amount = $request->subscription_amount;
        }
        $supportRequest->save();

        if ($oldStatus != $request->status) {
            $this->notify($supportRequest->requester_id, 'support_request_status_updated', $supportRequest);
        }
        if ($request->response_notes && $oldResponseNotes != $request->response_notes) {
            $this->notify($supportRequest->requester_id, 'support_request_response_added', $supportRequest);
        }
        if ($request->status == 'completed' && $oldStatus != 'completed') {
            $this->notify($supportRequest->requester_id, 'support_request_completed', $supportRequest, [
                'client_subscribed' => $supportRequest->client_subscribed,
                'subscription_amount' => $supportRequest->subscription_amount,
            ]);
        }

        return $this->success(['support_request' => $supportRequest], 'Support request updated successfully.');
    }

    public function updateSupportRequestStatus(Request $request, $id)
    {
        if (!Auth::user()->can('edit support request')) {
            return $this->error('Permission denied.', 403);
        }

        $supportRequest = SupportRequest::find($id);
        if (!$supportRequest || !$this->isParticipant($supportRequest)) {
            return $this->error('Support request not found.', 404);
        }
        if ($supportRequest->supporter_id != Auth::user()->id) {
            return $this->error('Only the assigned supporter can update the status.', 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);
        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $oldStatus = $supportRequest->status;
        $this->applyStatus($supportRequest, $request->status);
        $supportRequest->save();

        if ($oldStatus != $request->status) {
            $this->notify($supportRequest->requester_id, 'support_request_status_updated', $supportRequest);
        }

        return $this->success(['support_request' => $supportRequest], 'Status updated successfully.');
    }

    private function applyStatus(SupportRequest $supportRequest, $status)
    {
        if ($supportRequest->status != 'in_progress' && $status == 'in_progress' && !$supportRequest->responded_at) {
            $supportRequest->responded_at = now();
        }
        if ($status == 'completed' && !$supportRequest->completed_at) {
            $supportRequest->completed_at = now();
        }
        $supportRequest->status = $status;
    }

    private function isParticipant(SupportRequest $supportRequest)
    {
        $userId = Auth::user()->id;

        return $supportRequest->requester_id == $userId || $supportRequest->supporter_id == $userId;
    }

    private function checkLeadAndSupporter($leadId, $supporterId)
    {
        $ownerId = Auth::user()->ownerId();

        if (!Lead::where('id', $leadId)->where('created_by', $ownerId)->exists()) {
            return 'The selected lead is invalid.';
        }
        if (!User::where('id', $supporterId)->where('created_by', $ownerId)->where('type', '!=', 'client')->exists()) {
            return 'The selected supporter is invalid.';
        }

        return null;
    }

    private function notify($userId, $type, SupportRequest $supportRequest, array $extra = [])
    {
        $lead = $supportRequest->lead;

        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'data' => json_encode(array_merge([
                'support_request_id' => $supportRequest->id,
                'lead_name' => $lead ? $lead->name : 'N/A',
                'updated_by' => Auth::user()->id,
                'status' => $supportRequest->status,
            ], $extra)),
            'is_read' => 0,
        ]);
    }
}

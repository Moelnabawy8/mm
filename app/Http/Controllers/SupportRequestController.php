<?php

namespace App\Http\Controllers;

use App\Models\SupportRequest;
use App\Models\Lead;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class SupportRequestController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::user()->can('manage support request')) {
            $userId = Auth::user()->id;

            $query = SupportRequest::with(['lead', 'requester', 'supporter']);

            if ($request->view_type === 'requested') {
                $query->where('requester_id', $userId);
            } elseif ($request->view_type === 'assigned') {
                $query->where('supporter_id', $userId);
            } else {
                $query->where(function ($q) use ($userId) {
                    $q->where('requester_id', $userId)
                        ->orWhere('supporter_id', $userId);
                });
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }
            if ($request->priority) {
                $query->where('priority', $request->priority);
            }
            if ($request->supporter_id) {
                $query->where('supporter_id', $request->supporter_id);
            }
            if ($request->lead_id) {
                $query->where('lead_id', $request->lead_id);
            }
            if ($request->date_from && $request->date_to) {
                $query->whereBetween('created_at', [$request->date_from, $request->date_to]);
            } elseif ($request->date_from) {
                $query->where('created_at', '>=', $request->date_from);
            } elseif ($request->date_to) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $supportRequests = $query->orderBy('created_at', 'desc')
                ->paginate(20)
                ->appends($request->query());

            $stats = [
                'total_requested' => SupportRequest::where('requester_id', $userId)->count(),
                'total_assigned' => SupportRequest::where('supporter_id', $userId)->count(),
                'pending_requested' => SupportRequest::where('requester_id', $userId)->where('status', 'pending')->count(),
                'pending_assigned' => SupportRequest::where('supporter_id', $userId)->where('status', 'pending')->count(),
                'in_progress_requested' => SupportRequest::where('requester_id', $userId)->where('status', 'in_progress')->count(),
                'in_progress_assigned' => SupportRequest::where('supporter_id', $userId)->where('status', 'in_progress')->count(),
                'completed_requested' => SupportRequest::where('requester_id', $userId)->where('status', 'completed')->count(),
                'completed_assigned' => SupportRequest::where('supporter_id', $userId)->where('status', 'completed')->count(),
            ];

            $teammates = User::where('created_by', Auth::user()->ownerId())
                ->where('id', '!=', $userId)
                ->where('type', '!=', 'client')
                ->get();

            // The Lead/Client filter used to be built from every lead of the
            // company. It searches the server now, so nothing is loaded here.

            if ($request->ajax()) {
                return view('support-requests._table', compact('supportRequests', 'stats'));
            }

            return view('support-requests.index', compact('supportRequests', 'stats', 'teammates'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function create(Request $request)
    {
        if (Auth::user()->can('create support request')) {
            $usr = Auth::user();

            // Filter teammates to only Sales Employee, Alaa,Saba,aber and fadi roles
            $teammates = User::where('created_by', $usr->ownerId())
                ->where('id', '!=', $usr->id)
                ->where('type', '!=', 'client')
                ->where(function ($q) {
                    $q->whereHas('roles', function ($roleQuery) {
                        $roleQuery->whereIn('name', ['Sales Employee', 'sales employee', 'Alaa,Saba,aber', 'fadi']);
                    });
                })
                ->get();

            // Only the lead that was asked for is loaded. This used to fetch every
            // lead the user may see -- tens of thousands of rows turned into
            // <option> elements, each of which Choices.js then wrapped in its own
            // DOM node. The same shape locked the browser up on /deals until that
            // page was converted to this picker.
            $leadId = $request->query('lead_id');
            $selectedLead = $leadId ? $this->visibleLeadsQuery($usr)->find($leadId) : null;
            $selectedLeadName = $selectedLead ? $selectedLead->name : '';

            return view('support-requests.create', compact('teammates', 'leadId', 'selectedLeadName'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * The leads a user is allowed to attach a support request to: sales people
     * see the ones assigned to them, everyone else sees the company's.
     *
     * Lifted out of create() so the picker's search endpoint cannot drift away
     * from the rule the form itself applies.
     */
    private function visibleLeadsQuery($usr)
    {
        $query = Lead::where('created_by', $usr->ownerId());

        if (
            $usr->hasRole('Sales Employee') || $usr->hasRole('sales employee') ||
            $usr->hasRole('Alaa,Saba,aber') || $usr->hasRole('fadi')
        ) {
            $query->whereIn('id', \App\Models\UserLead::where('user_id', $usr->id)->pluck('lead_id'));
        }

        return $query;
    }

    /**
     * Feeds the lead picker on the index filter and both forms. Returns at most
     * 25 rows, which is the whole point: the three <select> elements it replaced
     * held one <option> per lead in the company.
     */
    public function leadsSearch(Request $request)
    {
        $usr = Auth::user();

        if (
            !$usr->can('manage support request') && !$usr->can('create support request')
            && !$usr->can('edit support request')
        ) {
            return response()->json([], 401);
        }

        $query = $this->visibleLeadsQuery($usr);

        $term = trim((string) $request->get('q'));
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                    ->orWhere('phone', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            });
        }

        $leads = $query->orderBy('id', 'desc')->limit(25)->get(['id', 'name', 'phone']);

        // name and phone travel apart so the picker can put them on separate
        // lines; joined into one string an Arabic name and a Latin-digit phone
        // swap round and read as if the number came first.
        return response()->json(
            $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => (string) $lead->phone,
                    'text' => $lead->name . ($lead->phone ? ' - ' . $lead->phone : ''),
                ];
            })->values()
        );
    }

    public function store(Request $request)
    {
        if (Auth::user()->can('create support request')) {
            $validator = Validator::make($request->all(), [
                'lead_id' => 'required|exists:leads,id',
                'supporter_id' => 'required|exists:users,id',
                'notes' => 'required|string|min:10',
                'priority' => 'required|in:low,normal,high,urgent',
            ]);

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first())->withInput();
            }

            if ($request->supporter_id == Auth::user()->id) {
                return redirect()->back()->with('error', __('You cannot request support from yourself.'))->withInput();
            }

            $supportRequest = new SupportRequest();
            $supportRequest->lead_id = $request->lead_id;
            $supportRequest->requester_id = Auth::user()->id;
            $supportRequest->supporter_id = $request->supporter_id;
            $supportRequest->notes = $request->notes;
            $supportRequest->priority = $request->priority;
            $supportRequest->status = 'pending';
            $supportRequest->save();

            // Create notification for supporter
            $lead = Lead::find($request->lead_id);
            $notificationData = [
                'support_request_id' => $supportRequest->id,
                'lead_name' => $lead ? $lead->name : 'N/A',
                'updated_by' => Auth::user()->id,
            ];

            Notification::create([
                'user_id' => $request->supporter_id,
                'type' => 'support_request_created',
                'data' => json_encode($notificationData),
                'is_read' => 0,
            ]);

            return redirect()->route('support-requests.index')->with('success', __('Support request created successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show($id)
    {
        if (Auth::user()->can('manage support request')) {
            $supportRequest = SupportRequest::with(['lead', 'requester', 'supporter'])->findOrFail($id);

            $userId = Auth::user()->id;
            if ($supportRequest->requester_id != $userId && $supportRequest->supporter_id != $userId) {
                return redirect()->back()->with('error', __('You do not have access to this support request.'));
            }

            return view('support-requests.show', compact('supportRequest'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function edit($id, Request $request)
    {
        if (Auth::user()->can('edit support request')) {
            $supportRequest = SupportRequest::findOrFail($id);

            $userId = Auth::user()->id;
            if ($supportRequest->requester_id != $userId && $supportRequest->supporter_id != $userId) {
                return redirect()->back()->with('error', __('You do not have access to this support request.'));
            }

            $teammates = User::where('created_by', Auth::user()->ownerId())
                ->where('id', '!=', Auth::user()->id)
                ->where('type', '!=', 'client')
                ->get();

            // see the note in create(): one lead, not the whole table
            $leadId = $request->query('lead_id');
            $currentLead = Lead::where('created_by', Auth::user()->ownerId())
                ->find($supportRequest->lead_id);
            $selectedLeadName = $currentLead ? $currentLead->name : '';

            return view('support-requests.edit', compact('supportRequest', 'teammates', 'leadId', 'selectedLeadName'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    public function update(Request $request, $id)
    {
        if (Auth::user()->can('edit support request')) {
            $supportRequest = SupportRequest::findOrFail($id);

            $userId = Auth::user()->id;
            if ($supportRequest->requester_id != $userId && $supportRequest->supporter_id != $userId) {
                return redirect()->back()->with('error', __('You do not have access to this support request.'));
            }

            $isRequester = $supportRequest->requester_id == $userId;
            $isSupporter = $supportRequest->supporter_id == $userId;

            if ($isRequester) {
                $validator = Validator::make($request->all(), [
                    'lead_id' => 'required|exists:leads,id',
                    'supporter_id' => 'required|exists:users,id',
                    'notes' => 'required|string|min:10',
                    'priority' => 'required|in:low,normal,high,urgent',
                    'status' => 'nullable|in:pending,cancelled',
                ]);

                if ($validator->fails()) {
                    return redirect()->back()->with('error', $validator->errors()->first())->withInput();
                }

                $supportRequest->lead_id = $request->lead_id;
                $supportRequest->supporter_id = $request->supporter_id;
                $supportRequest->notes = $request->notes;
                $supportRequest->priority = $request->priority;

                // Allow requester to cancel the request
                if ($request->has('status') && $request->status == 'cancelled') {
                    $supportRequest->status = 'cancelled';
                }
            }

            if ($isSupporter) {
                $validator = Validator::make($request->all(), [
                    'status' => 'required|in:pending,in_progress,completed,cancelled',
                    'response_notes' => 'nullable|string',
                    'client_subscribed' => 'nullable|boolean',
                    'subscription_amount' => 'nullable|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    return redirect()->back()->with('error', $validator->errors()->first())->withInput();
                }

                $oldStatus = $supportRequest->status;
                $oldResponseNotes = $supportRequest->response_notes;
                $supportRequest->status = $request->status;

                if ($request->response_notes) {
                    $supportRequest->response_notes = $request->response_notes;
                }

                if ($request->has('client_subscribed')) {
                    $supportRequest->client_subscribed = $request->client_subscribed;
                }

                if ($request->has('subscription_amount')) {
                    $supportRequest->subscription_amount = $request->subscription_amount;
                }

                if ($oldStatus != 'in_progress' && $request->status == 'in_progress' && !$supportRequest->responded_at) {
                    $supportRequest->responded_at = now();
                }

                if ($request->status == 'completed' && !$supportRequest->completed_at) {
                    $supportRequest->completed_at = now();
                }

                // Send notification to requester when supporter updates the request
                $lead = $supportRequest->lead;
                $notificationData = [
                    'support_request_id' => $supportRequest->id,
                    'lead_name' => $lead ? $lead->name : 'N/A',
                    'updated_by' => Auth::user()->id,
                    'status' => $request->status,
                ];

                // Notify requester about status change
                if ($oldStatus != $request->status) {
                    Notification::create([
                        'user_id' => $supportRequest->requester_id,
                        'type' => 'support_request_status_updated',
                        'data' => json_encode($notificationData),
                        'is_read' => 0,
                    ]);
                }

                // Notify requester about response notes
                if ($request->response_notes && $oldResponseNotes != $request->response_notes) {
                    Notification::create([
                        'user_id' => $supportRequest->requester_id,
                        'type' => 'support_request_response_added',
                        'data' => json_encode($notificationData),
                        'is_read' => 0,
                    ]);
                }

                // Notify requester about completion
                if ($request->status == 'completed' && $oldStatus != 'completed') {
                    Notification::create([
                        'user_id' => $supportRequest->requester_id,
                        'type' => 'support_request_completed',
                        'data' => json_encode(array_merge($notificationData, [
                            'client_subscribed' => $supportRequest->client_subscribed,
                            'subscription_amount' => $supportRequest->subscription_amount,
                        ])),
                        'is_read' => 0,
                    ]);
                }
            }

            $supportRequest->save();

            return redirect()->route('support-requests.index')->with('success', __('Support request updated successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy($id)
    {
        if (Auth::user()->can('delete support request')) {
            $supportRequest = SupportRequest::findOrFail($id);

            $userId = Auth::user()->id;
            // Only requester can delete
            if ($supportRequest->requester_id != $userId) {
                return redirect()->back()->with('error', __('Only the requester can delete this support request.'));
            }

            $supportRequest->delete();

            return redirect()->route('support-requests.index')->with('success', __('Support request deleted successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function updateStatus(Request $request, $id)
    {
        if (Auth::user()->can('edit support request')) {
            $supportRequest = SupportRequest::findOrFail($id);

            if ($supportRequest->supporter_id != Auth::user()->id) {
                return response()->json(['error' => __('Only the assigned supporter can update the status.')], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,in_progress,completed,cancelled',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 422);
            }

            $oldStatus = $supportRequest->status;
            $supportRequest->status = $request->status;

            if ($oldStatus != 'in_progress' && $request->status == 'in_progress' && !$supportRequest->responded_at) {
                $supportRequest->responded_at = now();
            }

            if ($request->status == 'completed' && !$supportRequest->completed_at) {
                $supportRequest->completed_at = now();
            }

            $supportRequest->save();

            // Send notification to requester about status change
            if ($oldStatus != $request->status) {
                $lead = $supportRequest->lead;
                $notificationData = [
                    'support_request_id' => $supportRequest->id,
                    'lead_name' => $lead ? $lead->name : 'N/A',
                    'updated_by' => Auth::user()->id,
                    'status' => $request->status,
                ];

                Notification::create([
                    'user_id' => $supportRequest->requester_id,
                    'type' => 'support_request_status_updated',
                    'data' => json_encode($notificationData),
                    'is_read' => 0,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('Status updated successfully.'),
                'status' => $supportRequest->status,
            ]);
        } else {
            return response()->json(['error' => __('Permission denied.')], 403);
        }
    }
}

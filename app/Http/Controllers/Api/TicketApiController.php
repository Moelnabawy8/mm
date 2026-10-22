<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Support;
use App\Models\SupportReply;
use App\Models\TicketAttachment;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class TicketApiController extends Controller
{
    use ApiResponser;

    public function getTickets()
    {
        $usr = Auth::user();
        if ($usr->type == 'client') {
            // Assuming client is identified by user_id in support or email if strictly public tickets
            // Support model usually has 'created_by' for user who created it (user_id field sometimes)
            // Let's check Support model structure usage in TicketManagementController
            // It uses created_by for company scope.
            // For client, it might be 'user' field or based on 'created_by' if client created it.
            $tickets = Support::where('created_by', $usr->creatorId())->where('user', $usr->id)->get();
        } else {
            $tickets = Support::where('created_by', $usr->creatorId())->get();
        }
        return $this->success(['tickets' => $tickets], 'Tickets retrieved.');
    }

    public function createTicket(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'subject'  => 'required|string|max:255',
                'end_date' => 'required|date',
                'priority' => 'required|in:Low,Medium,High,Critical',
                'status'   => 'nullable|in:Open,Close,On Hold',
                'user_id'  => 'nullable|integer|exists:users,id',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $ticket = new Support();
        $ticket->subject     = $request->subject;
        $ticket->priority    = $request->priority;
        $ticket->end_date    = $request->end_date;
        $ticket->status      = $request->status ?? 'Open';
        $ticket->description = $request->description;
        $ticket->user        = $request->user_id ?? Auth::user()->id;
        $ticket->ticket_code = time();
        $ticket->created_by  = Auth::user()->creatorId();

        if ($request->hasFile('attachment')) {
            $fileName = time() . "_" . $request->file('attachment')->getClientOriginalName();
            $request->file('attachment')->storeAs('uploads/supports', $fileName);
            $ticket->attachment = $fileName;
        }

        $ticket->save();

        return $this->success(['ticket' => $ticket], 'Ticket created successfully.');
    }

    public function getTicket($id)
    {
        $ticket = Support::where('id', $id)->where('created_by', Auth::user()->creatorId())->with(['replies'])->first();
        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }
        return $this->success(['ticket' => $ticket], 'Ticket details.');
    }

    public function updateTicket(Request $request, $id)
    {
        $ticket = Support::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'subject'  => 'nullable|string|max:255',
                'end_date' => 'nullable|date',
                'priority' => 'nullable|in:Low,Medium,High,Critical',
                'status'   => 'nullable|in:Open,Close,On Hold',
                'user_id'  => 'nullable|integer|exists:users,id',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        if ($request->has('subject'))     $ticket->subject     = $request->subject;
        if ($request->has('priority'))    $ticket->priority    = $request->priority;
        if ($request->has('end_date'))    $ticket->end_date    = $request->end_date;
        if ($request->has('status'))      $ticket->status      = $request->status;
        if ($request->has('description')) $ticket->description = $request->description;
        if ($request->has('user_id'))     $ticket->user        = $request->user_id;

        if ($request->hasFile('attachment')) {
            $fileName = time() . "_" . $request->file('attachment')->getClientOriginalName();
            $request->file('attachment')->storeAs('uploads/supports', $fileName);
            $ticket->attachment = $fileName;
        }

        $ticket->save();

        return $this->success(['ticket' => $ticket], 'Ticket updated successfully.');
    }

    public function deleteTicket($id)
    {
        $ticket = Support::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $reply = SupportReply::where('support_id', $id)->delete();
        $ticket->delete();

        return $this->success([], 'Ticket deleted successfully.');
    }

    public function addTicketReply(Request $request, $id)
    {
        $ticket = Support::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'description' => 'required',
            ]
        );

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $reply = new SupportReply();
        $reply->support_id = $id;
        $reply->user = Auth::user()->id;
        $reply->description = $request->description;
        $reply->created_by = Auth::user()->creatorId();
        $reply->save();

        return $this->success(['reply' => $reply], 'Reply added successfully.');
    }

    public function getTicketReplies($id)
    {
        $replies = SupportReply::where('support_id', $id)->get();
        return $this->success(['replies' => $replies], 'Ticket replies retrieved.');
    }

    public function closeTicket($id)
    {
        $ticket = Support::where('id', $id)->where('created_by', Auth::user()->creatorId())->first();
        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $ticket->status = 'Closed';
        $ticket->save();

        return $this->success(['ticket' => $ticket], 'Ticket closed successfully.');
    }
}

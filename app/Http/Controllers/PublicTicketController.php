<?php

namespace App\Http\Controllers;

use App\Models\Support;
use App\Models\SupportReply;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketCustomField;
use App\Models\TicketSetting;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicTicketController extends Controller
{
    public function showPortal($slug)
    {
        $settings = TicketSetting::where('portal_slug', $slug)->first();

        if (!$settings || !$settings->portal_enabled) {
            abort(404, __('Portal not found or disabled'));
        }

        $categories = TicketCategory::where('created_by', $settings->created_by)
            ->active()
            ->ordered()
            ->get();

        $customFields = TicketCustomField::where('created_by', $settings->created_by)
            ->active()
            ->ordered()
            ->get();

        $priorities = Support::$priority;

        return view('ticket_portal.create', compact('settings', 'categories', 'customFields', 'priorities'));
    }

    public function submitTicket(Request $request, $slug)
    {
        $settings = TicketSetting::where('portal_slug', $slug)->first();

        if (!$settings || !$settings->portal_enabled) {
            return response()->json(['error' => __('Portal not found or disabled')], 404);
        }

        $rules = [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'name' => 'required|string|max:255',
            'priority' => 'required|integer|min:0|max:3',
        ];

        if ($settings->require_email) {
            $rules['email'] = 'required|email|max:255';
        } else {
            $rules['email'] = 'nullable|email|max:255';
        }

        if ($settings->require_phone) {
            $rules['phone'] = 'required|string|max:20';
        } else {
            $rules['phone'] = 'nullable|string|max:20';
        }

        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customFields = TicketCustomField::where('created_by', $settings->created_by)
            ->active()
            ->get();

        $customFieldsData = [];
        foreach ($customFields as $field) {
            $fieldKey = 'custom_field_' . $field->id;
            if ($field->is_required && empty($request->input($fieldKey))) {
                return response()->json(['errors' => [$fieldKey => [__('This field is required')]]], 422);
            }
            if ($request->has($fieldKey)) {
                $customFieldsData[$field->id] = $request->input($fieldKey);
            }
        }

        $ticket = new Support();
        $ticket->subject = $request->subject;
        $ticket->description = $request->description;
        $ticket->submitter_name = $request->name;
        $ticket->submitter_email = $request->email;
        $ticket->submitter_phone = $request->phone;
        $ticket->category_id = $request->category_id;
        $ticket->priority = $request->priority;
        $ticket->status = 'New';
        $ticket->ticket_code = Support::generateTicketCode();
        $ticket->tracking_token = Support::generateTrackingToken();
        $ticket->is_public_ticket = true;
        $ticket->custom_fields_data = $customFieldsData;
        $ticket->created_by = $settings->created_by;
        $ticket->end_date = now()->addDays(7);
        $ticket->save();

        if ($settings->allow_attachments && $request->hasFile('attachments')) {
            $files = $request->file('attachments');
            $allowedTypes = $settings->allowed_file_types_array;
            $maxSize = $settings->max_file_size * 1024 * 1024;

            foreach ($files as $index => $file) {
                if ($index >= $settings->max_attachments) {
                    break;
                }

                $extension = strtolower($file->getClientOriginalExtension());
                if (!in_array($extension, $allowedTypes)) {
                    continue;
                }

                if ($file->getSize() > $maxSize) {
                    continue;
                }

                $fileName = time() . '_' . Str::random(10) . '.' . $extension;
                $filePath = $file->storeAs('ticket_attachments/' . $ticket->id, $fileName, 'public');

                TicketAttachment::create([
                    'support_id' => $ticket->id,
                    'file_name' => $fileName,
                    'original_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'file_path' => $filePath,
                    'is_public_upload' => true,
                ]);
            }
        }

        if ($settings->email_notifications && $ticket->submitter_email) {
            $this->sendTicketConfirmationEmail($ticket, $settings);
        }

        return response()->json([
            'success' => true,
            'message' => $settings->localized_success_message,
            'ticket_code' => $ticket->ticket_code,
            'tracking_url' => $ticket->tracking_url,
        ]);
    }

    public function showTrackingPage($slug)
    {
        $settings = TicketSetting::where('portal_slug', $slug)->first();

        if (!$settings || !$settings->portal_enabled) {
            abort(404, __('Portal not found or disabled'));
        }

        return view('ticket_portal.track', compact('settings'));
    }

    public function trackTicket(Request $request, $slug)
    {
        $settings = TicketSetting::where('portal_slug', $slug)->first();

        if (!$settings || !$settings->portal_enabled) {
            return response()->json(['error' => __('Portal not found or disabled')], 404);
        }

        $validator = \Validator::make($request->all(), [
            'ticket_code' => 'required_without:email',
            'email' => 'required_without:ticket_code|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Support::where('created_by', $settings->created_by)
            ->where('is_public_ticket', true);

        if ($request->ticket_code) {
            $query->where('ticket_code', $request->ticket_code);
        }

        if ($request->email) {
            $query->where('submitter_email', $request->email);
        }

        $tickets = $query->with([
            'category',
            'replies' => function ($q) {
                $q->where('is_internal_note', false)->orderBy('created_at', 'desc');
            }
        ])->orderBy('created_at', 'desc')->get();

        if ($tickets->isEmpty()) {
            return response()->json(['error' => __('No tickets found')], 404);
        }

        return response()->json([
            'success' => true,
            'tickets' => $tickets->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_code' => $ticket->ticket_code,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'status_badge_class' => $ticket->status_badge_class,
                    'priority' => Support::$priority[$ticket->priority] ?? 'Unknown',
                    'priority_badge_class' => $ticket->priority_badge_class,
                    'category' => $ticket->category ? $ticket->category->localized_name : null,
                    'created_at' => $ticket->created_at->format('Y-m-d H:i'),
                    'tracking_token' => $ticket->tracking_token,
                ];
            }),
        ]);
    }

    public function viewTicket($token)
    {
        $ticket = Support::where('tracking_token', $token)
            ->where('is_public_ticket', true)
            ->with([
                'category',
                'attachments',
                'replies' => function ($q) {
                    $q->where('is_internal_note', false)->orderBy('created_at', 'asc');
                }
            ])
            ->first();

        if (!$ticket) {
            abort(404, __('Ticket not found'));
        }

        $settings = TicketSetting::where('created_by', $ticket->created_by)->first();

        $customFields = TicketCustomField::where('created_by', $ticket->created_by)
            ->active()
            ->ordered()
            ->get();

        return view('ticket_portal.view', compact('ticket', 'settings', 'customFields'));
    }

    public function addReply(Request $request, $token)
    {
        $ticket = Support::where('tracking_token', $token)
            ->where('is_public_ticket', true)
            ->first();

        if (!$ticket) {
            return response()->json(['error' => __('Ticket not found')], 404);
        }

        if (in_array($ticket->status, ['Closed', 'Close'])) {
            return response()->json(['error' => __('This ticket is closed and cannot receive new replies')], 403);
        }

        $validator = \Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $settings = TicketSetting::where('created_by', $ticket->created_by)->first();

        $reply = new SupportReply();
        $reply->support_id = $ticket->id;
        $reply->description = $request->message;
        $reply->created_by = $ticket->created_by;
        $reply->is_public_reply = true;
        $reply->reply_from = 'customer';
        $reply->is_read = 0;
        $reply->save();

        if ($settings && $settings->allow_attachments && $request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $allowedTypes = $settings->allowed_file_types_array;
            $maxSize = $settings->max_file_size * 1024 * 1024;

            $extension = strtolower($file->getClientOriginalExtension());
            if (in_array($extension, $allowedTypes) && $file->getSize() <= $maxSize) {
                $fileName = time() . '_' . Str::random(10) . '.' . $extension;
                $filePath = $file->storeAs('ticket_attachments/' . $ticket->id, $fileName, 'public');

                TicketAttachment::create([
                    'support_id' => $ticket->id,
                    'reply_id' => $reply->id,
                    'file_name' => $fileName,
                    'original_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'file_path' => $filePath,
                    'is_public_upload' => true,
                ]);

                $reply->attachment = $fileName;
                $reply->save();
            }
        }

        $ticket->last_response_at = now();
        if ($ticket->status === 'Resolved') {
            $ticket->status = 'Open';
        }
        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => __('Reply added successfully'),
        ]);
    }

    private function sendTicketConfirmationEmail($ticket, $settings)
    {
        try {
            $creator = User::find($settings->created_by);
            if (!$creator) {
                return;
            }

            $emailSettings = Utility::settings($settings->created_by);

            if (isset($emailSettings['mail_driver']) && $emailSettings['mail_driver'] != '') {
                config([
                    'mail.mailers.smtp.host' => $emailSettings['mail_host'] ?? '',
                    'mail.mailers.smtp.port' => $emailSettings['mail_port'] ?? '',
                    'mail.mailers.smtp.username' => $emailSettings['mail_username'] ?? '',
                    'mail.mailers.smtp.password' => $emailSettings['mail_password'] ?? '',
                    'mail.mailers.smtp.encryption' => $emailSettings['mail_encryption'] ?? '',
                    'mail.from.address' => $emailSettings['mail_from_address'] ?? '',
                    'mail.from.name' => $emailSettings['mail_from_name'] ?? '',
                ]);

                Mail::send('emails.ticket_confirmation', [
                    'ticket' => $ticket,
                    'settings' => $settings,
                ], function ($message) use ($ticket, $emailSettings) {
                    $message->to($ticket->submitter_email, $ticket->submitter_name)
                        ->subject(__('Ticket Confirmation') . ' - ' . $ticket->ticket_code);
                });
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send ticket confirmation email: ' . $e->getMessage());
        }
    }

    /**
     * Serve portal logo from storage/app directory
     */
    public function servePortalLogo($filename)
    {
        // Try multiple possible paths
        $paths = [
            storage_path('app/ticket_portal/' . $filename),
            storage_path('app/' . $filename),
            storage_path('app/public/ticket_portal/' . $filename),
        ];

        $path = null;
        foreach ($paths as $possiblePath) {
            if (file_exists($possiblePath)) {
                $path = $possiblePath;
                break;
            }
        }

        if (!$path) {
            abort(404);
        }

        $mimeType = mime_content_type($path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

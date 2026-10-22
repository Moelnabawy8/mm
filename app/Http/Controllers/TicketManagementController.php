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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TicketManagementController extends Controller
{
    /**
     * Check if the current user has access to ticket management
     * Allows 'company' and 'Ticket system' types
     */
    private function hasTicketAccess()
    {
        return Auth::check() && in_array(Auth::user()->type, ['company', 'Ticket system']);
    }

    /**
     * Check if the current user is company type only
     * Used for create/delete operations that should be restricted
     */
    private function isCompanyOnly()
    {
        return Auth::check() && Auth::user()->type == 'company';
    }

    private function checkTablesExist()
    {
        try {
            return Schema::hasTable('ticket_categories') &&
                Schema::hasTable('ticket_settings') &&
                Schema::hasTable('ticket_custom_fields') &&
                Schema::hasColumn('supports', 'is_public_ticket');
        } catch (\Exception $e) {
            return false;
        }
    }

    public function index(Request $request)
    {
        try {
            if (!$this->hasTicketAccess()) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            if (!$this->checkTablesExist()) {
                return view('ticket_management.setup_required');
            }

            $categories = TicketCategory::where('created_by', Auth::user()->creatorId())->where('is_active', true)->get();
            $statuses = Support::publicStatus();
            $priorities = Support::$priority;

            $query = Support::where('created_by', Auth::user()->creatorId())
                ->where('is_public_ticket', true)
                ->with(['category', 'assignUser']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('ticket_code', 'like', "%{$search}%")
                        ->orWhere('submitter_name', 'like', "%{$search}%")
                        ->orWhere('submitter_email', 'like', "%{$search}%");
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $tickets = $query->orderBy('created_at', 'desc')->paginate(20);

            $countTotal = Support::where('created_by', Auth::user()->creatorId())->where('is_public_ticket', true)->count();
            $countNew = Support::where('created_by', Auth::user()->creatorId())->where('is_public_ticket', true)->where('status', 'New')->count();
            $countOpen = Support::where('created_by', Auth::user()->creatorId())->where('is_public_ticket', true)->where('status', 'Open')->count();
            $countInProgress = Support::where('created_by', Auth::user()->creatorId())->where('is_public_ticket', true)->where('status', 'In Progress')->count();
            $countResolved = Support::where('created_by', Auth::user()->creatorId())->where('is_public_ticket', true)->where('status', 'Resolved')->count();
            $countClosed = Support::where('created_by', Auth::user()->creatorId())->where('is_public_ticket', true)->where('status', 'Closed')->count();

            return view('ticket_management.index', compact(
                'tickets',
                'categories',
                'statuses',
                'priorities',
                'countTotal',
                'countNew',
                'countOpen',
                'countInProgress',
                'countResolved',
                'countClosed'
            ));
        } catch (\Exception $e) {
            Log::error('Ticket Management Error: ' . $e->getMessage());
            return view('ticket_management.setup_required');
        }
    }

    public function show($id)
    {
        if (!$this->hasTicketAccess()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $ticket = Support::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->with(['category', 'attachments', 'replies.users', 'assignUser'])
            ->firstOrFail();

        $customFields = TicketCustomField::where('created_by', Auth::user()->creatorId())
            ->active()
            ->ordered()
            ->get();

        $users = User::where('created_by', Auth::user()->creatorId())
            ->where('type', '!=', 'client')
            ->get()
            ->pluck('name', 'id');

        $statuses = Support::publicStatus();

        foreach ($ticket->replies as $reply) {
            if ($reply->is_read == 0 && $reply->reply_from == 'customer') {
                $reply->is_read = 1;
                $reply->save();
            }
        }

        return view('ticket_management.show', compact('ticket', 'customFields', 'users', 'statuses'));
    }

    public function updateStatus(Request $request, $id)
    {
        if (!$this->hasTicketAccess()) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $ticket = Support::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $validator = \Validator::make($request->all(), [
            'status' => 'required|in:New,Open,In Progress,Resolved,Closed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $ticket->status;
        $ticket->status = $request->status;

        if ($request->status === 'Resolved' && !$ticket->resolved_at) {
            $ticket->resolved_at = now();
        }

        if ($request->status === 'Closed' && !$ticket->closed_at) {
            $ticket->closed_at = now();
        }

        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => __('Status updated successfully'),
            'new_status' => $ticket->status,
            'badge_class' => $ticket->status_badge_class,
        ]);
    }

    public function assignUser(Request $request, $id)
    {
        if (!$this->hasTicketAccess()) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $ticket = Support::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $ticket->user = $request->user_id;
        $ticket->save();

        $assignedUser = User::find($request->user_id);

        return response()->json([
            'success' => true,
            'message' => __('User assigned successfully'),
            'assigned_user' => $assignedUser ? $assignedUser->name : null,
        ]);
    }

    public function addReply(Request $request, $id)
    {
        if (!$this->hasTicketAccess()) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $ticket = Support::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $validator = \Validator::make($request->all(), [
            'message' => 'required|string',
            'is_internal' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reply = new SupportReply();
        $reply->support_id = $ticket->id;
        $reply->user = Auth::user()->id;
        $reply->description = $request->message;
        $reply->created_by = Auth::user()->creatorId();
        $reply->is_internal_note = $request->is_internal ?? false;
        $reply->is_public_reply = !($request->is_internal ?? false);
        $reply->reply_from = 'staff';
        $reply->is_read = 1;
        $reply->save();

        if ($request->hasFile('attachment')) {
            $settings = TicketSetting::getSettings(Auth::user()->creatorId());
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
                    'uploaded_by' => Auth::user()->id,
                    'is_public_upload' => false,
                ]);

                $reply->attachment = $fileName;
                $reply->save();
            }
        }

        $ticket->last_response_at = now();
        $ticket->save();

        if (!$reply->is_internal_note && $ticket->submitter_email) {
            $this->sendReplyNotification($ticket, $reply);
        }

        return response()->json([
            'success' => true,
            'message' => __('Reply added successfully'),
        ]);
    }

    public function settings()
    {
        try {
            if (!$this->hasTicketAccess()) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            if (!$this->checkTablesExist()) {
                return view('ticket_management.setup_required');
            }

            $settings = TicketSetting::getSettings(Auth::user()->creatorId());

            return view('ticket_management.settings', compact('settings'));
        } catch (\Exception $e) {
            Log::error('Ticket Settings Error: ' . $e->getMessage());
            return view('ticket_management.setup_required');
        }
    }

    public function updateSettings(Request $request)
    {
        if (!$this->hasTicketAccess()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'portal_title' => 'required|string|max:255',
            'portal_description' => 'nullable|string',
            'max_attachments' => 'required|integer|min:1|max:10',
            'max_file_size' => 'required|integer|min:1|max:50',
            'allowed_file_types' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $settings = TicketSetting::getSettings(Auth::user()->creatorId());

        $settings->portal_enabled = $request->has('portal_enabled');
        $settings->portal_title = $request->portal_title;
        $settings->portal_title_ar = $request->portal_title_ar;
        $settings->portal_description = $request->portal_description;
        $settings->portal_description_ar = $request->portal_description_ar;
        $settings->portal_primary_color = $request->portal_primary_color ?? '#6366f1';
        $settings->portal_secondary_color = $request->portal_secondary_color ?? '#8b5cf6';
        $settings->require_email = $request->has('require_email');
        $settings->require_phone = $request->has('require_phone');
        $settings->allow_attachments = $request->has('allow_attachments');
        $settings->max_attachments = $request->max_attachments;
        $settings->allowed_file_types = $request->allowed_file_types;
        $settings->max_file_size = $request->max_file_size;
        $settings->email_notifications = $request->has('email_notifications');
        $settings->whatsapp_notifications = $request->has('whatsapp_notifications');
        $settings->success_message = $request->success_message;
        $settings->success_message_ar = $request->success_message_ar;

        if ($request->hasFile('portal_logo')) {
            $file = $request->file('portal_logo');
            $fileName = time() . '_logo.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('ticket_portal', $fileName, 'public');
            $settings->portal_logo = $filePath;
        }

        $settings->save();

        return redirect()->back()->with('success', __('Settings updated successfully'));
    }

    public function regenerateSlug()
    {
        if (!$this->hasTicketAccess()) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $settings = TicketSetting::getSettings(Auth::user()->creatorId());
        $settings->portal_slug = Str::random(10);
        $settings->save();

        return response()->json([
            'success' => true,
            'new_slug' => $settings->portal_slug,
            'portal_url' => $settings->portal_url,
        ]);
    }

    public function categories()
    {
        try {
            if (!$this->hasTicketAccess()) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            if (!$this->checkTablesExist()) {
                return view('ticket_management.setup_required');
            }

            $categories = TicketCategory::where('created_by', Auth::user()->creatorId())
                ->ordered()
                ->withCount('tickets')
                ->get();

            return view('ticket_management.categories.index', compact('categories'));
        } catch (\Exception $e) {
            Log::error('Ticket Categories Error: ' . $e->getMessage());
            return view('ticket_management.setup_required');
        }
    }

    public function createCategory()
    {
        if (!$this->isCompanyOnly()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        return view('ticket_management.categories.create');
    }

    public function storeCategory(Request $request)
    {
        if (!$this->isCompanyOnly()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $maxOrder = TicketCategory::where('created_by', Auth::user()->creatorId())->max('order') ?? 0;

        TicketCategory::create([
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'description' => $request->description,
            'color' => $request->color,
            'icon' => $request->icon,
            'is_active' => $request->has('is_active'),
            'order' => $maxOrder + 1,
            'created_by' => Auth::user()->creatorId(),
        ]);

        return redirect()->route('ticket-management.categories')->with('success', __('Category created successfully'));
    }

    public function editCategory($id)
    {
        if (!$this->hasTicketAccess()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $category = TicketCategory::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        return view('ticket_management.categories.edit', compact('category'));
    }

    public function updateCategory(Request $request, $id)
    {
        if (!$this->hasTicketAccess()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $category = TicketCategory::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $category->update([
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'description' => $request->description,
            'color' => $request->color,
            'icon' => $request->icon,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('ticket-management.categories')->with('success', __('Category updated successfully'));
    }

    public function deleteCategory($id)
    {
        if (!$this->isCompanyOnly()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $category = TicketCategory::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $category->delete();

        return redirect()->route('ticket-management.categories')->with('success', __('Category deleted successfully'));
    }

    public function customFields()
    {
        try {
            if (!$this->hasTicketAccess()) {
                return redirect()->back()->with('error', __('Permission denied.'));
            }

            if (!$this->checkTablesExist()) {
                return view('ticket_management.setup_required');
            }

            $fields = TicketCustomField::where('created_by', Auth::user()->creatorId())
                ->ordered()
                ->get();

            $fieldTypes = TicketCustomField::$fieldTypes;

            return view('ticket_management.custom_fields.index', compact('fields', 'fieldTypes'));
        } catch (\Exception $e) {
            Log::error('Ticket Custom Fields Error: ' . $e->getMessage());
            return view('ticket_management.setup_required');
        }
    }

    public function createCustomField()
    {
        if (!$this->isCompanyOnly()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $fieldTypes = TicketCustomField::$fieldTypes;

        return view('ticket_management.custom_fields.create', compact('fieldTypes'));
    }

    public function storeCustomField(Request $request)
    {
        if (!$this->isCompanyOnly()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', array_keys(TicketCustomField::$fieldTypes)),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $options = null;
        if (in_array($request->type, ['select', 'radio', 'checkbox']) && $request->options) {
            $options = array_filter(array_map('trim', explode("\n", $request->options)));
        }

        $maxOrder = TicketCustomField::where('created_by', Auth::user()->creatorId())->max('order') ?? 0;

        TicketCustomField::create([
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'type' => $request->type,
            'options' => $options,
            'placeholder' => $request->placeholder,
            'placeholder_ar' => $request->placeholder_ar,
            'is_required' => $request->has('is_required'),
            'is_active' => $request->has('is_active'),
            'order' => $maxOrder + 1,
            'created_by' => Auth::user()->creatorId(),
        ]);

        return redirect()->route('ticket-management.custom-fields')->with('success', __('Custom field created successfully'));
    }

    public function editCustomField($id)
    {
        if (!$this->hasTicketAccess()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $field = TicketCustomField::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $fieldTypes = TicketCustomField::$fieldTypes;

        return view('ticket_management.custom_fields.edit', compact('field', 'fieldTypes'));
    }

    public function updateCustomField(Request $request, $id)
    {
        if (!$this->hasTicketAccess()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $field = TicketCustomField::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', array_keys(TicketCustomField::$fieldTypes)),
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $options = null;
        if (in_array($request->type, ['select', 'radio', 'checkbox']) && $request->options) {
            $options = array_filter(array_map('trim', explode("\n", $request->options)));
        }

        $field->update([
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'type' => $request->type,
            'options' => $options,
            'placeholder' => $request->placeholder,
            'placeholder_ar' => $request->placeholder_ar,
            'is_required' => $request->has('is_required'),
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('ticket-management.custom-fields')->with('success', __('Custom field updated successfully'));
    }

    public function deleteCustomField($id)
    {
        if (!$this->isCompanyOnly()) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $field = TicketCustomField::where('id', $id)
            ->where('created_by', Auth::user()->creatorId())
            ->firstOrFail();

        $field->delete();

        return redirect()->route('ticket-management.custom-fields')->with('success', __('Custom field deleted successfully'));
    }

    public function reorderFields(Request $request)
    {
        if (!$this->hasTicketAccess()) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $order = $request->order;
        foreach ($order as $index => $id) {
            TicketCustomField::where('id', $id)
                ->where('created_by', Auth::user()->creatorId())
                ->update(['order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    private function sendReplyNotification($ticket, $reply)
    {
        try {
            $settings = TicketSetting::getSettings($ticket->created_by);

            if (!$settings->email_notifications) {
                return;
            }

            $emailSettings = Utility::settings($ticket->created_by);

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

                \Mail::send('emails.ticket_reply', [
                    'ticket' => $ticket,
                    'reply' => $reply,
                    'settings' => $settings,
                ], function ($message) use ($ticket, $emailSettings) {
                    $message->to($ticket->submitter_email, $ticket->submitter_name)
                        ->subject(__('New Reply to Your Ticket') . ' - ' . $ticket->ticket_code);
                });
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send ticket reply notification: ' . $e->getMessage());
        }
    }
}

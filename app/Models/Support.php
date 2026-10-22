<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Support extends Model
{
    protected $fillable = [
        'subject',
        'user',
        'priority',
        'end_date',
        'ticket_code',
        'ticket_created',
        'status',
        'created_by',
        'description',
        'attachment',
        'submitter_name',
        'submitter_email',
        'submitter_phone',
        'category_id',
        'custom_fields_data',
        'tracking_token',
        'is_public_ticket',
        'last_response_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'is_public_ticket' => 'boolean',
        'custom_fields_data' => 'array',
        'last_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public static $priority = [
        'Low',
        'Medium',
        'High',
        'Critical',
    ];

    public static $publicStatus = [
        'New' => 'New',
        'Open' => 'Open',
        'In Progress' => 'In Progress',
        'Resolved' => 'Resolved',
        'Closed' => 'Closed',
    ];

    public function createdBy()
    {
        return $this->hasOne('App\Models\user', 'id', 'ticket_created');
    }

    public function assignUser()
    {
        return $this->hasOne('App\Models\user', 'id', 'user');
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function replies()
    {
        return $this->hasMany(SupportReply::class, 'support_id');
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'support_id');
    }

    public static $status = [
        'Open' => 'Open',
        'Close' => 'Close',
        'On Hold' =>  'On Hold',
    ];

    public static function status() {
        $status['Open'] = __ ('Open');
        $status['Close'] = __ ('Close');
        $status['On Hold'] = __ ('On Hold');
        return $status;
    }

    public static function publicStatus() {
        $status['New'] = __('New');
        $status['Open'] = __('Open');
        $status['In Progress'] = __('In Progress');
        $status['Resolved'] = __('Resolved');
        $status['Closed'] = __('Closed');
        return $status;
    }

    public function replyUnread()
    {

        if(\Auth::user()->type == 'Employee')
        {
            return SupportReply:: where('support_id', $this->id)->where('is_read', 0)->where('user', '!=', \Auth::user()->id)->count('id');
        }
        else
        {
            return SupportReply:: where('support_id', $this->id)->where('is_read', 0)->count('id');
        }
    }

    public static function generateTicketCode()
    {
        return 'TKT-' . strtoupper(Str::random(8));
    }

    public static function generateTrackingToken()
    {
        return Str::random(32);
    }

    public function getTrackingUrlAttribute()
    {
        if ($this->tracking_token) {
            return url('/ticket-portal/track/' . $this->tracking_token);
        }
        return null;
    }

    public function getStatusBadgeClassAttribute()
    {
        $classes = [
            'New' => 'bg-info',
            'Open' => 'bg-success',
            'In Progress' => 'bg-warning',
            'Resolved' => 'bg-primary',
            'Closed' => 'bg-secondary',
            'On Hold' => 'bg-warning',
            'Close' => 'bg-danger',
        ];

        return $classes[$this->status] ?? 'bg-secondary';
    }

    public function getPriorityBadgeClassAttribute()
    {
        $classes = [
            0 => 'bg-success',   // Low
            1 => 'bg-info',      // Medium
            2 => 'bg-warning',   // High
            3 => 'bg-danger',    // Critical
        ];

        return $classes[$this->priority] ?? 'bg-secondary';
    }

    public function getPublicReplies()
    {
        return $this->replies()->where('is_internal_note', false)->orderBy('created_at', 'asc')->get();
    }

    public function scopePublicTickets($query)
    {
        return $query->where('is_public_ticket', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        if ($categoryId) {
            return $query->where('category_id', $categoryId);
        }
        return $query;
    }

    public function scopeByStatus($query, $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }

    public function scopeByPriority($query, $priority)
    {
        if ($priority !== null && $priority !== '') {
            return $query->where('priority', $priority);
        }
        return $query;
    }

    public function getCustomFieldValue($fieldId)
    {
        $data = $this->custom_fields_data;
        if (is_array($data) && isset($data[$fieldId])) {
            return $data[$fieldId];
        }
        return null;
    }
}

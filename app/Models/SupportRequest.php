<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'requester_id',
        'supporter_id',
        'status',
        'notes',
        'response_notes',
        'responded_at',
        'completed_at',
        'priority',
        'client_subscribed',
        'subscription_amount',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'completed_at' => 'datetime',
        'client_subscribed' => 'boolean',
        'subscription_amount' => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id', 'id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }

    public function supporter()
    {
        return $this->belongsTo(User::class, 'supporter_id', 'id');
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
        ];

        return $badges[$this->status] ?? 'secondary';
    }

    public function getPriorityBadgeAttribute()
    {
        $badges = [
            'low' => 'secondary',
            'normal' => 'primary',
            'high' => 'warning',
            'urgent' => 'danger',
        ];

        return $badges[$this->priority] ?? 'primary';
    }

    public function scopeRequested($query, $userId)
    {
        return $query->where('requester_id', $userId);
    }

    public function scopeAssigned($query, $userId)
    {
        return $query->where('supporter_id', $userId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'approved_at',
        'approved_by',
        'admin_notes',
        'created_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public static $types = [
        'requirement' => 'Requirement',
        'request' => 'Request',
        'tool' => 'Tool',
        'resource' => 'Resource',
    ];

    public static $priorities = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public static $statuses = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'fulfilled' => 'Fulfilled',
    ];

    public static $priority_colors = [
        'low' => 'info',
        'medium' => 'warning',
        'high' => 'danger',
        'urgent' => 'dark',
    ];

    public static $status_colors = [
        'pending' => 'secondary',
        'approved' => 'success',
        'rejected' => 'danger',
        'fulfilled' => 'primary',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'job_title',
        'primary_role',
        'official_responsibilities',
        'daily_tasks',
        'periodic_tasks',
        'work_details',
        'task_execution_method',
        'department_connections',
        'collaboration_details',
        'created_by',
    ];

    protected $casts = [
        'official_responsibilities' => 'array',
        'daily_tasks' => 'array',
        'periodic_tasks' => 'array',
        'department_connections' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

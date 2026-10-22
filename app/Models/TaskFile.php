<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskFile extends Model
{
    protected $fillable = [
        'file',
        'name',
        'extension',
        'file_size',
        'task_id',
        'user_type',
        'created_by',
    ];

    protected $appends = ['file_url', 'size'];

    public function getFileUrlAttribute(): string
    {
        return config('app.url') . '/storage/uploads/tasks/' . $this->file;
    }

    public function getSizeAttribute()
    {
        return (int) str_replace([' MB', ' KB', ' B'], '', $this->file_size);
    }

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }
}

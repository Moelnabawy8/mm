<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BottleneckAlert extends Model
{
    protected $table = 'bottleneck_alerts';

    protected $fillable = [
        'type', 'reference_type', 'reference_id',
        'severity', 'message', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeOfSeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReport extends Model
{
    protected $fillable = [
        'user_id',
        'report_date',
        'status',
        'data_received',
        'sales_count',
        'monthly_target_total',
        'weekly_target_total',
        'collections_actual',
        'closed_deals',
        'calls_count',
        'presentations_count',
        'top_clients',
        'ai_usage_logs',
        'future_ideas',
        'sod_data',
        'eod_data',
        'sod_submitted_at',
        'eod_submitted_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'top_clients' => 'array',
        'ai_usage_logs' => 'array',
        'future_ideas' => 'array',
        'sod_data' => 'array',
        'eod_data' => 'array',
        'sod_submitted_at' => 'datetime',
        'eod_submitted_at' => 'datetime',
        'monthly_target_total' => 'float',
        'weekly_target_total' => 'float',
        'collections_actual' => 'float',
    ];

    /**
     * Get the user that owns the daily report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
